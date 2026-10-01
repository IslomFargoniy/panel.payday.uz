<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendancePairingService
{
    /**
     * Build the 3-stage window-function pairing query for attendance events.
     *
     * @param Request $request
     * @param string $from Format: Y-m-d
     * @param string $to Format: Y-m-d
     * @return \Illuminate\Database\Query\Builder
     */
    public function buildPairedEventsQuery(Request $request, $from, $to)
    {
        $inSql = AttendanceStatus::inSqlList();
        $outSql = AttendanceStatus::outSqlList();
        $inValues = AttendanceStatus::inValues();
        $outValues = AttendanceStatus::outValues();

        // 1. Raw events with LAG / LEAD for duplicate elimination
        $rawEvents = DB::table('hikvision_access_events as hae')
            ->select(
                'hae.id',
                'hae.employeeNoString',
                'w.id as worker_id',
                'w.name as worker',
                'w.phone',
                'b.name as branch',
                'b.id as branch_id',
                DB::raw('COALESCE(hae.work_time, w.work_time) as work_time'),
                DB::raw('COALESCE(hae.end_time, w.end_time) as end_time'),
                'f.id as firm_id',
                'f.name as firm',
                'hae.attendanceStatus',
                'hae.label',
                'hae.created_at',
                DB::raw("LAG(hae.attendanceStatus) OVER (
                    PARTITION BY hae.employeeNoString, DATE(hae.created_at) 
                    ORDER BY hae.created_at
                ) AS prev_status"),
                DB::raw("LEAD(hae.attendanceStatus) OVER (
                    PARTITION BY hae.employeeNoString, DATE(hae.created_at) 
                    ORDER BY hae.created_at
                ) AS next_status")
            )
            ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
            ->join('branches as b', 'w.branch_id', '=', 'b.id')
            ->join('firms as f', 'b.firm_id', '=', 'f.id')
            ->whereNull('hae.deleted_at')
            ->whereNull('w.deleted_at')
            ->whereBetween('hae.created_at', [$from, $to . " 23:59:59"]);

        if ($request->worker_id) {
            $rawEvents->where('w.id', $request->worker_id);
        }
        if ($request->search) {
            $rawEvents->where(function ($query) use ($request) {
                $query->where('w.name', 'like', '%' . $request->search . '%')
                    ->orWhere('b.name', 'like', '%' . $request->search . '%')
                    ->orWhere('f.name', 'like', '%' . $request->search . '%')
                    ->orWhere('hae.label', 'like', '%' . $request->search . '%')
                    ->orWhere('w.phone', 'like', '%' . $request->search . '%')
                    ->orWhere('w.address', 'like', '%' . $request->search . '%')
                    ->orWhere('w.comment', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->branch_id) {
            $rawEvents->where('b.id', $request->branch_id);
        }
        if ($request->firm_id) {
            $rawEvents->where('f.id', $request->firm_id);
        }

        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            $firmIds = Auth::user()->user_firms()->pluck('firm_id');
            $rawEvents->whereIn('f.id', $firmIds);
        }

        // 2. Filter out consecutive duplicates:
        // When checking in repeatedly: keep ONLY the first check-in (prev_status is NOT check-in)
        // When checking out repeatedly: keep ONLY the last check-out (next_status is NOT check-out)
        $filteredEvents = DB::table(DB::raw("({$rawEvents->toSql()}) as re"))
            ->mergeBindings($rawEvents)
            ->where(function ($q) use ($inValues, $outValues) {
                $q->where(function ($sub) use ($inValues) {
                    $sub->whereIn('re.attendanceStatus', $inValues)
                        ->where(function ($c) use ($inValues) {
                            $c->whereNotIn('re.prev_status', $inValues)
                              ->orWhereNull('re.prev_status');
                        });
                })->orWhere(function ($sub) use ($outValues) {
                    $sub->whereIn('re.attendanceStatus', $outValues)
                        ->where(function ($c) use ($outValues) {
                            $c->whereNotIn('re.next_status', $outValues)
                              ->orWhereNull('re.next_status');
                        });
                });
            });

        // 3. Pair surviving events using LEAD()
        $pairedStage = DB::table(DB::raw("({$filteredEvents->toSql()}) as fe"))
            ->mergeBindings($filteredEvents)
            ->select(
                'fe.id',
                'fe.employeeNoString',
                'fe.worker_id',
                'fe.worker',
                'fe.phone',
                'fe.branch',
                'fe.branch_id',
                'fe.firm_id',
                'fe.work_time',
                'fe.end_time',
                'fe.firm',
                'fe.attendanceStatus as status_from',
                'fe.label as label_from',
                'fe.created_at as from_time',
                DB::raw("LEAD(fe.id) OVER (PARTITION BY fe.employeeNoString ORDER BY fe.created_at) AS to_id"),
                DB::raw("LEAD(fe.created_at) OVER (PARTITION BY fe.employeeNoString ORDER BY fe.created_at) AS to_time"),
                DB::raw("LEAD(fe.attendanceStatus) OVER (PARTITION BY fe.employeeNoString ORDER BY fe.created_at) AS status_to"),
                DB::raw("LEAD(fe.label) OVER (PARTITION BY fe.employeeNoString ORDER BY fe.created_at) AS label_to"),
                DB::raw("MIN(CASE WHEN fe.attendanceStatus IN ({$inSql}) AND (TIME(fe.created_at) >= '05:00:00' OR fe.work_time < '06:00:00') THEN fe.created_at END) OVER (PARTITION BY fe.employeeNoString, DATE(fe.created_at)) AS day_first_check_in"),
                DB::raw("MIN(CASE WHEN fe.attendanceStatus IN ({$inSql}) AND (TIME(fe.created_at) >= '05:00:00' OR fe.work_time < '06:00:00') THEN fe.work_time END) OVER (PARTITION BY fe.employeeNoString, DATE(fe.created_at)) AS day_first_work_time")
            );

        $isSqlite = DB::getDriverName() === 'sqlite';
        $nextDaySql = $isSqlite ? "date(pe.from_time, '+1 day')" : "DATE_ADD(DATE(pe.from_time), INTERVAL 1 DAY)";

        $minuteDiff = $isSqlite 
            ? "CAST((strftime('%s', pe.to_time) - strftime('%s', pe.from_time)) / 60 AS INTEGER)" 
            : "TIMESTAMPDIFF(MINUTE, pe.from_time, pe.to_time)";

        $lateDiff = $isSqlite
            ? "CAST((strftime('%s', pe.day_first_check_in) - (strftime('%s', DATE(pe.from_time) || ' ' || pe.day_first_work_time))) / 60 AS INTEGER)"
            : "TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE(pe.from_time), pe.day_first_work_time), pe.day_first_check_in)";

        // 4. Final paired query (only checkIn shifts)
        $pairedQuery = DB::table(DB::raw("({$pairedStage->toSql()}) as pe"))
            ->mergeBindings($pairedStage)
            ->whereIn('pe.status_from', $inValues)
            ->select(
                'pe.id',
                'pe.employeeNoString',
                'pe.worker_id',
                'pe.worker',
                'pe.phone',
                'pe.branch',
                'pe.branch_id',
                'pe.firm_id',
                'pe.work_time',
                'pe.end_time',
                'pe.firm',
                'pe.from_time',
                'pe.day_first_check_in',
                'pe.day_first_work_time',
                DB::raw("IF(pe.status_to IN ({$outSql}) AND (DATE(pe.to_time) = DATE(pe.from_time) OR (DATE(pe.to_time) = {$nextDaySql} AND TIME(pe.to_time) <= '12:00:00')), pe.to_time, NULL) as to_time"),
                DB::raw("IF(pe.status_to IN ({$outSql}) AND (DATE(pe.to_time) = DATE(pe.from_time) OR (DATE(pe.to_time) = {$nextDaySql} AND TIME(pe.to_time) <= '12:00:00')), pe.to_id, NULL) as to_id"),
                'pe.status_from',
                DB::raw("IF(pe.status_to IN ({$outSql}), CONCAT(pe.label_from, '/', pe.label_to), pe.label_from) as status"),
                DB::raw("CASE
                    WHEN ROW_NUMBER() OVER (
                        PARTITION BY pe.employeeNoString, DATE(pe.from_time) 
                        ORDER BY (TIME(pe.from_time) < '05:00:00'), pe.from_time
                    ) = 1 AND pe.day_first_check_in IS NOT NULL AND TIME(pe.day_first_check_in) > TIME(pe.day_first_work_time)
                    THEN GREATEST(1, {$lateDiff})
                    ELSE 0
                END as late_minutes"),
                DB::raw("IF(pe.status_to IN ({$outSql}) AND (DATE(pe.to_time) = DATE(pe.from_time) OR (DATE(pe.to_time) = {$nextDaySql} AND TIME(pe.to_time) <= '12:00:00')), {$minuteDiff}, 0) as worked_minutes"),
                DB::raw("0 as break_minutes")
            );

        return $pairedQuery;
    }
}
