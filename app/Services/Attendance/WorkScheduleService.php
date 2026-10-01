<?php

namespace App\Services\Attendance;

use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\FirmHoliday;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class WorkScheduleService
{
    /**
     * Convert Carbon date to 1-based day of week index:
     * 1 = Sunday, 2 = Monday, ..., 7 = Saturday (matching SQL DAYOFWEEK() and days.index).
     */
    public static function dayIndex(Carbon|string $date): int
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $carbon->dayOfWeek + 1;
    }

    /**
     * Get the active working day indexes (1..7) for a worker:
     * Hierarchy: worker_days -> branch_days -> all 7 days (1..7).
     *
     * @return int[]
     */
    public function getWorkingDayIndexes(Worker $worker): array
    {
        // 1. Worker specific days
        if ($worker->relationLoaded('worker_days')) {
            $indexes = $worker->worker_days->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray();
        } else {
            $indexes = WorkerDay::where('worker_id', $worker->id)
                ->with('day')
                ->get()
                ->pluck('day.index')
                ->filter()
                ->map(fn($v) => (int)$v)
                ->toArray();
        }

        if (!empty($indexes)) {
            return array_values(array_unique($indexes));
        }

        // 2. Branch specific days
        if ($worker->branch_id) {
            $branchDays = BranchDay::where('branch_id', $worker->branch_id)
                ->with('day')
                ->get();
            $indexes = $branchDays->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray();
            if (!empty($indexes)) {
                return array_values(array_unique($indexes));
            }
        }

        // 3. Fallback: all 7 days
        return range(1, 7);
    }

    /**
     * Count actual working days for a worker in a date range, taking into account:
     * - Schedule working days (worker_days -> branch_days -> 1..7)
     * - Branch holidays
     * - Firm holidays
     * - Worker holidays (leaves / vacations)
     */
    public function countWorkingDays(Worker $worker, Carbon|string $from, Carbon|string $to): int
    {
        $fromDate = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from)->startOfDay();
        $toDate = $to instanceof Carbon ? $to->copy()->endOfDay() : Carbon::parse($to)->endOfDay();
        $fromStr = $fromDate->toDateString();
        $toStr = $toDate->toDateString();

        $workingDayIndexes = $this->getWorkingDayIndexes($worker);

        // Branch holidays
        $branchHolidays = [];
        if ($worker->branch_id) {
            $branchHolidays = BranchHoliday::where('branch_id', $worker->branch_id)
                ->whereBetween('date', [$fromStr, $toStr])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->toArray();
        }

        // Firm holidays
        $firmHolidays = [];
        $firmId = $worker->branch?->firm_id;
        if ($firmId) {
            $firmHolidays = FirmHoliday::where('firm_id', $firmId)
                ->whereBetween('date', [$fromStr, $toStr])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->toArray();
        }

        // Worker holidays
        $workerHolidays = WorkerHoliday::where('worker_id', $worker->id)
            ->whereDate('from', '<=', $toStr)
            ->whereDate('to', '>=', $fromStr)
            ->get(['from', 'to']);

        $count = 0;
        foreach (CarbonPeriod::create($fromDate->toDateString(), $toDate->toDateString()) as $date) {
            $dayIdx = self::dayIndex($date);
            if (!in_array($dayIdx, $workingDayIndexes, true)) {
                continue;
            }

            $dateStr = $date->toDateString();
            if (isset($branchHolidays[$dateStr]) || isset($firmHolidays[$dateStr])) {
                continue;
            }

            $isWorkerHoliday = false;
            foreach ($workerHolidays as $wh) {
                if ($dateStr >= $wh->from && $dateStr <= $wh->to) {
                    $isWorkerHoliday = true;
                    break;
                }
            }
            if ($isWorkerHoliday) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Count actual working days for a branch (or default all days) in a date range.
     */
    public function countWorkingDaysForBranch(?int $branchId, Carbon|string $from, Carbon|string $to): int
    {
        $fromDate = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from)->startOfDay();
        $toDate = $to instanceof Carbon ? $to->copy()->endOfDay() : Carbon::parse($to)->endOfDay();
        $fromStr = $fromDate->toDateString();
        $toStr = $toDate->toDateString();

        $workingDayIndexes = range(1, 7);
        $branchHolidays = [];
        $firmHolidays = [];

        if ($branchId) {
            $branchDays = BranchDay::where('branch_id', $branchId)->with('day')->get();
            $indexes = $branchDays->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray();
            if (!empty($indexes)) {
                $workingDayIndexes = array_values(array_unique($indexes));
            }

            $branchHolidays = BranchHoliday::where('branch_id', $branchId)
                ->whereBetween('date', [$fromStr, $toStr])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->toArray();

            $firmId = \App\Models\Branch\Branch::where('id', $branchId)->value('firm_id');
            if ($firmId) {
                $firmHolidays = FirmHoliday::where('firm_id', $firmId)
                    ->whereBetween('date', [$fromStr, $toStr])
                    ->pluck('date')
                    ->map(fn($d) => Carbon::parse($d)->toDateString())
                    ->flip()
                    ->toArray();
            }
        }

        $count = 0;
        foreach (CarbonPeriod::create($fromStr, $toStr) as $date) {
            $dayIdx = self::dayIndex($date);
            if (!in_array($dayIdx, $workingDayIndexes, true)) {
                continue;
            }

            $dateStr = $date->toDateString();
            if (isset($branchHolidays[$dateStr]) || isset($firmHolidays[$dateStr])) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Get off-day numbers (1..31) for a specific month for calendar rendering.
     *
     * @return int[]
     */
    public function getOffDayNumbers(Worker $worker, Carbon|string $month): array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = Carbon::parse($month)->endOfMonth();

        $batch = $this->batchGetHolidays([$worker], $start->format('Y-m'));
        return $batch[$worker->id] ?? [];
    }

    /**
     * Batch calculate off-day numbers (1..31) for multiple workers in a month.
     *
     * @param iterable<Worker> $workers
     * @return array<int, int[]> Map of worker_id => [1..31 off days]
     */
    public function batchGetHolidays(iterable $workers, string $month): array
    {
        $workerList = is_array($workers) ? $workers : iterator_to_array($workers);
        if (empty($workerList)) {
            return [];
        }

        $from = Carbon::parse($month)->startOfMonth();
        $to = Carbon::parse($month)->endOfMonth();
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $workerIds = array_map(fn($w) => $w->id, $workerList);
        $branchIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch_id, $workerList))));
        $firmIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch?->firm_id, $workerList))));

        // 1. Worker days & Branch days
        $workerDaysMap = WorkerDay::whereIn('worker_id', $workerIds)
            ->with('day')
            ->get()
            ->groupBy('worker_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray());

        $branchDaysMap = BranchDay::whereIn('branch_id', $branchIds)
            ->with('day')
            ->get()
            ->groupBy('branch_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray());

        // 2. Holidays
        $workerHolidaysMap = WorkerHoliday::whereIn('worker_id', $workerIds)
            ->whereDate('from', '<=', $toStr)
            ->whereDate('to', '>=', $fromStr)
            ->get(['worker_id', 'from', 'to'])
            ->groupBy('worker_id');

        $branchHolidaysMap = BranchHoliday::whereIn('branch_id', $branchIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['branch_id', 'date'])
            ->groupBy('branch_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $firmHolidaysMap = FirmHoliday::whereIn('firm_id', $firmIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['firm_id', 'date'])
            ->groupBy('firm_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $dates = iterator_to_array(CarbonPeriod::create($from, $to));
        $result = [];

        foreach ($workerList as $worker) {
            $workingDayIndexes = $workerDaysMap->get($worker->id)
                ?? $branchDaysMap->get($worker->branch_id)
                ?? range(1, 7);

            $weekendIndexes = array_diff(range(1, 7), $workingDayIndexes);
            $wHolidays = $workerHolidaysMap->get($worker->id) ?? collect();
            $bHolidays = $branchHolidaysMap->get($worker->branch_id) ?? [];
            $fHolidays = $firmHolidaysMap->get($worker->branch?->firm_id) ?? [];

            $offDayIndexes = [];
            foreach ($dates as $date) {
                $currentDayIndex = self::dayIndex($date);
                $dateStr = $date->toDateString();

                $isWeekend = in_array($currentDayIndex, $weekendIndexes, true);
                $isBranchHoliday = isset($bHolidays[$dateStr]);
                $isFirmHoliday = isset($fHolidays[$dateStr]);

                $isWorkerHoliday = false;
                foreach ($wHolidays as $wh) {
                    if ($dateStr >= $wh->from && $dateStr <= $wh->to) {
                        $isWorkerHoliday = true;
                        break;
                    }
                }

                if ($isWeekend || $isWorkerHoliday || $isBranchHoliday || $isFirmHoliday) {
                    $offDayIndexes[] = (int) $date->format('j');
                }
            }

            $result[$worker->id] = $offDayIndexes;
        }

        return $result;
    }

    /**
     * Batch calculate actual working days count for multiple workers in a date range.
     *
     * @param iterable<Worker> $workers
     * @return array<int, int> Map of worker_id => working_days_count
     */
    public function batchCalculateWorkingDays(iterable $workers, Carbon|string $from, Carbon|string $to): array
    {
        $workerList = is_array($workers) ? $workers : iterator_to_array($workers);
        if (empty($workerList)) {
            return [];
        }

        $fromDate = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from)->startOfDay();
        $toDate = $to instanceof Carbon ? $to->copy()->endOfDay() : Carbon::parse($to)->endOfDay();
        $fromStr = $fromDate->toDateString();
        $toStr = $toDate->toDateString();

        $workerIds = array_map(fn($w) => $w->id, $workerList);
        $branchIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch_id, $workerList))));
        $firmIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch?->firm_id, $workerList))));

        // 1. Worker days & Branch days
        $workerDaysMap = WorkerDay::whereIn('worker_id', $workerIds)
            ->with('day')
            ->get()
            ->groupBy('worker_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray());

        $branchDaysMap = BranchDay::whereIn('branch_id', $branchIds)
            ->with('day')
            ->get()
            ->groupBy('branch_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->map(fn($v) => (int)$v)->toArray());

        // 2. Holidays
        $workerHolidaysMap = WorkerHoliday::whereIn('worker_id', $workerIds)
            ->whereDate('from', '<=', $toStr)
            ->whereDate('to', '>=', $fromStr)
            ->get(['worker_id', 'from', 'to'])
            ->groupBy('worker_id');

        $branchHolidaysMap = BranchHoliday::whereIn('branch_id', $branchIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['branch_id', 'date'])
            ->groupBy('branch_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $firmHolidaysMap = FirmHoliday::whereIn('firm_id', $firmIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['firm_id', 'date'])
            ->groupBy('firm_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $dates = iterator_to_array(CarbonPeriod::create($fromStr, $toStr));
        $result = [];

        foreach ($workerList as $worker) {
            $workingDayIndexes = $workerDaysMap->get($worker->id)
                ?? $branchDaysMap->get($worker->branch_id)
                ?? range(1, 7);

            $wHolidays = $workerHolidaysMap->get($worker->id) ?? collect();
            $bHolidays = $branchHolidaysMap->get($worker->branch_id) ?? [];
            $fHolidays = $firmHolidaysMap->get($worker->branch?->firm_id) ?? [];

            $count = 0;
            foreach ($dates as $date) {
                $dayIdx = self::dayIndex($date);
                if (!in_array($dayIdx, $workingDayIndexes, true)) {
                    continue;
                }

                $dateStr = $date->toDateString();
                if (isset($bHolidays[$dateStr]) || isset($fHolidays[$dateStr])) {
                    continue;
                }

                $isWHoliday = false;
                foreach ($wHolidays as $wh) {
                    if ($dateStr >= $wh->from && $dateStr <= $wh->to) {
                        $isWHoliday = true;
                        break;
                    }
                }
                if ($isWHoliday) {
                    continue;
                }

                $count++;
            }

            $result[$worker->id] = $count;
        }

        return $result;
    }
}
