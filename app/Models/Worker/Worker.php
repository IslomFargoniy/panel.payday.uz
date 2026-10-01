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
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\Worker\WorkerFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected $with = [
        'branch'
    ];

    public function getHoliday($month): array
    {
        return (new \App\Services\Attendance\WorkScheduleService())->getOffDayNumbers($this, $month);
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

    public function days()
    {
        return $this->worker_days();
    }

    public function holidays()
    {
        return $this->worker_holidays();
    }

    public function hikvision_access_events()
    {
        return $this->HikvisionAccessEvents();
    }

    public function setAvatarAttribute($value): void
    {
        if ($value && is_string($value)) {
            $value = ltrim(str_replace('/storage/', '', $value), '/');
            if (str_starts_with($value, 'storage/')) {
                $value = substr($value, 8);
            }
        }
        $this->attributes['avatar'] = $value ?: null;
    }

    /**
     * Calculate current balance for a worker using Eloquent sum() with row locking.
     */
    public static function getWorkerBalance(int $workerId, bool $lockForUpdate = false): float
    {
        $workerQuery = self::withTrashed()->where('id', $workerId);
        if ($lockForUpdate) {
            $workerQuery->lockForUpdate();
        }
        $worker = $workerQuery->first();
        if (!$worker) {
            return 0.0;
        }

        $salaryQuery = Salary::where('worker_id', $workerId);
        $paymentQuery = SalaryPayment::where('worker_id', $workerId);

        if ($lockForUpdate) {
            $salaryQuery->lockForUpdate();
            $paymentQuery->lockForUpdate();
        }

        $totalSalaries = (float) $salaryQuery->sum('amount');
        $totalPayments = (float) $paymentQuery->sum('amount');

        return $totalSalaries - $totalPayments;
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
