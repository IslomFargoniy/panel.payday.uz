<?php

namespace App\Models\Hikvision;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HikvisionAccessEvent extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'hikvision_access_id',
        'deviceName',
        'majorEventType',
        'subEventType',
        'name',
        'cardReaderNo',
        'employeeNoString',
        'serialNo',
        'userType',
        'currentVerifyMode',
        'frontSerialNo',
        'attendanceStatus',
        'label',
        'mask',
        'picturesNumber',
        'purePwdVerifyEnable',
        'picture',
        'work_time',
        'end_time'
    ];

    public function hikvisionAccess()
    {
        return $this->belongsTo(HikvisionAccess::class, 'hikvision_access_id');
    }


    public function faceReact()
    {
        return $this->hasOne(FaceRect::class, 'hikvision_access_event_id');
    }


}
