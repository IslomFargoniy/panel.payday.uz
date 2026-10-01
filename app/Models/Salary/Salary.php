<?php

namespace App\Models\Salary;

use App\Models\User\User;
use App\Models\Worker\Worker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    /** @use HasFactory<\Database\Factories\Salary\SalaryFactory> */
    use HasFactory;


    protected $fillable = [
        'user_id',
        'worker_id',
        'amount',
        'worked_minute',
        'break_minute',
        'hour_price',
        'from',
        'to',
        'comment'
    ];

    protected $with = [
        'user',
        'worker'
    ];

    public function user(){
        return $this->belongsTo(User::class , 'user_id');
    }

    public function worker(){
        return $this->belongsTo(Worker::class , 'worker_id');
    }

    public function salary_firm_holidays()
    {
        return $this->hasMany(SalaryFirmHoliday::class , 'salary_id');
    }

    public function salary_branch_holidays()
    {
        return $this->hasMany(SalaryBranchHoliday::class , 'salary_id');
    }

    public function salary_branch_days()
    {
        return $this->hasMany(SalaryBranchDay::class , 'salary_id');
    }

    public function salary_worker_days()
    {
        return $this->hasMany(SalaryWorkerDay::class, 'salary_id');
    }

    public function salary_worker_holidays()
    {
        return $this->hasMany(SalaryWorkerHoliday::class, 'salary_id');
    }
}
