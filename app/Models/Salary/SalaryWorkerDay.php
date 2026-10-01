<?php

namespace App\Models\Salary;

use App\Models\Day;
use Illuminate\Database\Eloquent\Model;

class SalaryWorkerDay extends Model
{
    protected $fillable = [
        'salary_id',
        'day_id',
    ];

    public function salary()
    {
        return $this->belongsTo(Salary::class, 'salary_id');
    }

    public function day()
    {
        return $this->belongsTo(Day::class, 'day_id');
    }
}
