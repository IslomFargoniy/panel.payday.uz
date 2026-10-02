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

    protected $hidden = [
        'encryption_key',
    ];

    public function branch(){
        return $this->belongsTo(Branch::class , 'branch_id');
    }

    /**
     * Qurilma aloqada ekanini belgilaydi. ISUP qurilma uzoq vaqt aloqasiz bo'lib qaytgan bo'lsa,
     * offline davridagi eventlarni to'ldirish uchun catch-up job navbatga qo'yiladi.
     */
    public function markSeen(): void
    {
        $previousSeen = $this->last_seen_at;

        $this->is_online = true;
        $this->last_seen_at = now();
        $this->save();

        \App\Jobs\CatchUpDeviceEventsJob::dispatchIfGap($this, $previousSeen);
    }
}
