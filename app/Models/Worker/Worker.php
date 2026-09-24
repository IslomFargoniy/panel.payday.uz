<?php

namespace App\Models\Worker;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\FirmHoliday;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryPayment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Laravel\Sanctum\HasApiTokens;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\Worker\WorkerFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'branch_id',
        'work_time',
        'end_time',
        'hour_price',
        'fine_price',
        'name',
        'phone',
        'password',
        'address',
        'comment',
        'employeeNoString',
        'status',
        'telegram_id',
        'avatar'
    ];

    protected $hidden = [
        'password',
    ];

    protected $with = [
        'branch'
    ];

    public function getHoliday($month): array
    {
        $from = Carbon::parse($month)->startOfMonth();
        $to = Carbon::parse($month)->endOfMonth();

        /** WorkerHoliday */
        $workerHolidays = WorkerHoliday::where('worker_id', $this->id)
            ->whereDate('from', '<=', $to)
            ->whereDate('to', '>=', $from)
            ->get();

        /** BranchHoliday */
        $branchHolidays = BranchHoliday::where('branch_id', $this->branch_id)
            ->whereBetween('date', [$from, $to])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->flip();

        /** FirmHoliday */
        $firmId = optional($this->branch)->firm_id;
        $firmHolidays = $firmId
            ? FirmHoliday::where('firm_id', $firmId)
                ->whereBetween('date', [$from, $to])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->flip()
            : collect();

        /** Ish kunlari (Worker -> Branch -> Default) */
        $workingDays = $this->worker_days()->with('day')->get();
        $workingDayIndexes = $workingDays->pluck('day.index')->filter()->toArray();

        if (empty($workingDayIndexes)) {
            $workingDays = BranchDay::where('branch_id', $this->branch_id)
                ->with('day')
                ->get();
            $workingDayIndexes = $workingDays->pluck('day.index')->filter()->toArray();
        }

        if (empty($workingDayIndexes)) {
            // Agar umuman topilmasa — haftaning barcha kunlari ish kuni
            $workingDayIndexes = range(1, 7);
        }

        // Dam kunlari
        $weekendIndexes = array_diff(range(1, 7), $workingDayIndexes);

        /** Oy bo‘ylab ishlamaydigan kunlar (1–31 formatda) */
        $offDayIndexes = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dateStr = $date->toDateString();

            /**
             * MUHIM:
             * Carbon dayOfWeek:
             * 0 = yakshanba
             * 1 = dushanba
             * ...
             * 6 = shanba
             *
             * Bizda:
             * 1 = yakshanba
             * 2 = dushanba
             * ...
             * 7 = shanba
             */
            $currentDayIndex = $date->dayOfWeek + 1;

            $isWeekend = in_array($currentDayIndex, $weekendIndexes);

            $isWorkerHoliday = $workerHolidays->contains(function ($h) use ($date) {
                return $date->between(
                    Carbon::parse($h->from),
                    Carbon::parse($h->to)
                );
            });

            $isBranchHoliday = isset($branchHolidays[$dateStr]);
            $isFirmHoliday = isset($firmHolidays[$dateStr]);

            if ($isWeekend || $isWorkerHoliday || $isBranchHoliday || $isFirmHoliday) {
                $offDayIndexes[] = (int)$date->format('j');
            }
        }

        return $offDayIndexes;
    }


    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class, 'worker_id');
    }

    public function worker_days()
    {
        return $this->hasMany(WorkerDay::class, 'worker_id')
            ->with('day');
    }

    public function worker_holidays()
    {
        return $this->hasMany(WorkerHoliday::class, 'worker_id');
    }

    public function salary_payments()
    {
        return $this->hasMany(SalaryPayment::class, 'worker_id');
    }

    public function HikvisionAccessEvents()
    {
        return $this->hasMany(HikvisionAccessEvent::class, 'employeeNoString', 'employeeNoString');
    }

    public function checkIn()
    {
        return $this->hasMany(HikvisionAccessEvent::class, 'employeeNoString', 'employeeNoString')
            ->where('attendanceStatus', '=', 'checkIn');
    }

    public function checkOut()
    {
        return $this->hasMany(HikvisionAccessEvent::class, 'employeeNoString', 'employeeNoString')
            ->where('attendanceStatus', '=', 'checkOut');
    }
}
