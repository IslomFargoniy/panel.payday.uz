<?php

namespace App\Models\Branch;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchDevice extends Model
{
    /** @use HasFactory<\Database\Factories\Branch\BranchDeviceFactory> */
    use HasFactory;


    protected $fillable = [
        'branch_id',
        'name',
        'mac_address',
        'device_id',
        'connection_type',
        'status',
        'is_online',
        'last_seen_at',
        'encryption_key',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function branch(){
        return $this->belongsTo(Branch::class , 'branch_id');
    }
}
