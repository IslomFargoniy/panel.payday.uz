<?php

namespace App\Models\Hikvision;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HikvisionAccess extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'ipAddress',
        'portNo',
        'protocol',
        'macAddress',
        'channelId',
        'dateTime',
        'activePostCount',
        'eventType',
        'eventState',
        'eventDescription',
        'shortSerialNumber'
    ];


    public function hikvisionAccessEvent()
    {
        return $this->hasOne(HikvisionAccessEvent::class , 'hikvision_access_id');
    }
}
