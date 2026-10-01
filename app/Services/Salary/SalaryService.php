<?php

namespace App\Services\Salary;

use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\FirmHoliday;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryBranchDay;
use App\Models\Salary\SalaryBranchHoliday;
use App\Models\Salary\SalaryFirmHoliday;
use App\Models\Salary\SalaryWorkerDay;
use App\Models\Salary\SalaryWorkerHoliday;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    /**
     * Create a salary record and snapshot firm/branch/worker schedules and holidays.
     */
    public function createSalary(array $validated, int $userId): Salary
    {
        return DB::transaction(function () use ($validated, $userId) {
            $validated['user_id'] = $userId;
            $salary = Salary::create($validated);

            $branch = $salary->worker->branch;
            $firm_id = $branch->firm_id;
            $branch_id = $branch->id;

            // 1. Snapshot firm holidays in period
            $firm_holidays = FirmHoliday::select(
                'name',
                'date',
                'comment',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('firm_id', $firm_id)
                ->whereBetween('date', [$salary->from, $salary->to])
                ->get();

            if ($firm_holidays->isNotEmpty()) {
                SalaryFirmHoliday::insert($firm_holidays->toArray());
            }

            // 2. Snapshot branch holidays in period
            $branch_holidays = BranchHoliday::select(
                'name',
                'date',
                'comment',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('branch_id', $branch_id)
                ->whereBetween('date', [$salary->from, $salary->to])
                ->get();

            if ($branch_holidays->isNotEmpty()) {
                SalaryBranchHoliday::insert($branch_holidays->toArray());
            }

            // 3. Snapshot branch working days
            $branch_days = BranchDay::select(
                'day_id',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('branch_id', $branch_id)
                ->get();

            if ($branch_days->isNotEmpty()) {
                SalaryBranchDay::insert($branch_days->toArray());
            }

            // 4. Snapshot worker custom days
            $worker_days = WorkerDay::select(
                'day_id',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('worker_id', $salary->worker_id)
                ->get();

            if ($worker_days->isNotEmpty()) {
                SalaryWorkerDay::insert($worker_days->toArray());
            }

            // 5. Snapshot worker holidays in period
            $worker_holidays = WorkerHoliday::select(
                'from',
                'to',
                'comment',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('worker_id', $salary->worker_id)
                ->where(function ($q) use ($salary) {
                    $q->where('from', '<=', $salary->to)
                      ->where('to', '>=', $salary->from);
                })
                ->get();

            if ($worker_holidays->isNotEmpty()) {
                SalaryWorkerHoliday::insert($worker_holidays->toArray());
            }

            return $salary;
        });
    }
}
