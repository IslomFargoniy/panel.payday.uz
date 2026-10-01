<?php

namespace App\Models\Branch;

use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    /** @use HasFactory<\Database\Factories\Branch\BranchFactory> */
    use HasFactory;


    protected $fillable = [
        'firm_id',
        'name',
        'address',
        'comment',
        'work_time',
        'end_time',
        'hour_price',
        'fine_price',
        'status',
        'telegram_group_id',
        'latitude',
        'longitude'
    ];

    protected $with = [
        'firm'
    ];

    public function firm()
    {
        return $this->belongsTo(Firm::class , 'firm_id');
    }

    public function branch_days()
    {
        return $this->hasMany(BranchDay::class , 'branch_id');
    }

    public function days()
    {
        return $this->branch_days();
    }

    public function branch_holidays()
    {
        return $this->hasMany(BranchHoliday::class , 'branch_id');
    }

    public function holidays()
    {
        return $this->branch_holidays();
    }

    public function branch_devices()
    {
        return $this->hasMany(BranchDevice::class , 'branch_id');
    }

    public function devices()
    {
        return $this->branch_devices();
    }

    public function workers()
    {
        return $this->hasMany(Worker::class , 'branch_id');
    }
}