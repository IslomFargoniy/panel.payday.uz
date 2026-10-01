<?php

namespace App\Http\Controllers\Hikvision;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFaceRectRequest;
use App\Http\Requests\UpdateFaceRectRequest;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Hikvision\FaceRect;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class HikvisionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function attendance(Request $request, \App\Services\Attendance\AttendanceReportService $reportService)
    {
        $data = $reportService->getAttendanceGridData($request);

        return Inertia::render('attendance/index', $data);
    }

    public function daily_attendance(Request $request, Branch $branch, \App\Services\Attendance\AttendanceReportService $reportService)
    {
        $data = $reportService->getDailyAttendanceData($request, $branch);

        return Inertia::render('daily_attendance/index', $data);
    }

    private function batchGetHolidays(array $workers, string $month): array
    {
        return (new \App\Services\Attendance\WorkScheduleService())->batchGetHolidays($workers, $month);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFaceRectRequest $request)
    {
        try {
            \Illuminate\Support\Facades\Log::info('Hikvision Callback Incoming:', [
                'all' => $request->all(),
                'files' => array_keys($request->allFiles())
            ]);

            $inputs = $request->except(['Picture', 'picture', 'file', 'image']);
            // Normalize incoming payload
            if ($request->has('AccessControllerEvent') && is_string($request->AccessControllerEvent)) {
                $decoded = json_decode($request->AccessControllerEvent);
                if (isset($decoded->AccessControllerEvent)) {
                    $eventData = $decoded;
                } else {
                    $eventData = json_decode(json_encode($inputs)) ?: new \stdClass();
                    $eventData->AccessControllerEvent = $decoded;
                }
            } elseif ($request->has('event_log') && is_string($request->event_log)) {
                $decoded = json_decode($request->event_log);
                if (isset($decoded->AccessControllerEvent)) {
                    $eventData = $decoded;
                } else {
                    $eventData = json_decode(json_encode($inputs)) ?: new \stdClass();
                    $eventData->AccessControllerEvent = $decoded;
                }
            } else {
                $eventData = json_decode(json_encode($inputs)) ?: new \stdClass();
            }

            if (!isset($eventData->AccessControllerEvent) && isset($eventData->employeeNoString)) {
                $eventData->AccessControllerEvent = clone $eventData;
            }

            $rawMac = $eventData->macAddress ?? ($eventData->AccessControllerEvent->macAddress ?? $request->input('macAddress'));
            $deviceId = $eventData->device_id ?? ($eventData->AccessControllerEvent->device_id ?? $request->input('device_id'));
            $shortSerial = $eventData->shortSerialNumber ?? ($eventData->AccessControllerEvent->shortSerialNumber ?? $request->input('shortSerialNumber'));
            $serialNo = $eventData->AccessControllerEvent->serialNo ?? ($eventData->serialNo ?? $request->input('serialNo'));

            $macAddress = $rawMac ? strtolower(str_replace(['-', ' '], ':', trim($rawMac))) : null;

            // Resolve matching BranchDevice with status = 1
            $branchDevice = null;
            if ($macAddress || $shortSerial || $deviceId || $serialNo) {
                $branchDevice = \App\Models\Branch\BranchDevice::where('status', 1)
                    ->where(function ($q) use ($macAddress, $shortSerial, $deviceId, $serialNo) {
                        if ($macAddress) {
                            $q->orWhereRaw("LOWER(REPLACE(mac_address, '-', ':')) = ?", [$macAddress]);
                        }
                        if ($shortSerial && $shortSerial !== 'default') {
                            $q->orWhere('device_id', '=', $shortSerial);
                        }
                        if ($deviceId) {
                            $q->orWhere('device_id', '=', $deviceId);
                        }
                        if ($serialNo && $serialNo !== 'default') {
                            $q->orWhere('device_id', '=', $serialNo);
                        }
                    })->first();
            }

            if (!$branchDevice) {
                \Illuminate\Support\Facades\Log::warning('Hikvision callback rejected: No active BranchDevice matched', [
                    'ip' => $request->ip(),
                    'mac' => $rawMac,
                    'norm_mac' => $macAddress,
                    'device_id' => $deviceId,
                    'short_serial' => $shortSerial,
                    'serial_no' => $serialNo,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Device not recognized',
                ], 200);
            }

            $shortSerial = $branchDevice->device_id ?: ($shortSerial ?: 'default');
            $branchDevice->update([
                'is_online' => true,
                'last_seen_at' => now(),
            ]);

            if (isset($eventData->AccessControllerEvent)) {
                $rawStatus = $eventData->AccessControllerEvent->attendanceStatus ?? null;
                $normalized = \App\Enums\AttendanceStatus::normalize($rawStatus);
                if ($normalized) {
                    $eventData->AccessControllerEvent->attendanceStatus = $normalized;
                    $eventData->AccessControllerEvent->label = \App\Enums\AttendanceStatus::label($normalized);
                } else {
                    $eventData->AccessControllerEvent->attendanceStatus = null;
                }

                $accessEventData = $eventData->AccessControllerEvent;

                $filename = '';
                $cleanSerial = preg_replace('/[^a-zA-Z0-9_\-]/', '', $shortSerial) ?: 'default';
                if ($request->hasFile('Picture') || $request->hasFile('picture')) {
                    $picture = $request->file('Picture') ?? $request->file('picture');
                    $ext = strtolower($picture->getClientOriginalExtension());
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $ext = 'jpg';
                    }
                    $filename = \Illuminate\Support\Str::uuid()->toString() . '.' . $ext;
                    $picture->storeAs("hikvision/{$cleanSerial}", $filename, 'public');
                } elseif ($request->filled('picture') || !empty($eventData->picture) || !empty($accessEventData->picture)) {
                    $rawPic = (string) ($request->input('picture') ?: ($eventData->picture ?? $accessEventData->picture));
                    $basePic = basename($rawPic);
                    $ext = strtolower(pathinfo($basePic, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $filename = $basePic;
                    }
                }

                if (empty($accessEventData->employeeNoString)) {
                    return response()->json(['success' => true, 'message' => 'No employeeNoString']);
                }

                $deviceId = $eventData->device_id ?? null;

                $checkWorker = Worker::with([])
                    ->where('employeeNoString', '=', $accessEventData->employeeNoString)
                    ->where('status', '=', 1)
                    ->whereHas('branch', function ($query) use ($macAddress, $shortSerial, $deviceId) {
                        $query->whereHas('firm', function ($query) {
                            $query->where('status', '=', 1)
                                ->where('valid_date', '>=', date('Y-m-d'));
                        })
                            ->whereHas('branch_devices', function ($query) use ($macAddress, $shortSerial, $deviceId) {
                                $query->where(function ($q) use ($macAddress, $shortSerial, $deviceId) {
                                    if ($macAddress) {
                                        $q->orWhere('mac_address', '=', $macAddress);
                                    }
                                    if ($shortSerial && $shortSerial !== 'default') {
                                        $q->orWhere('device_id', '=', $shortSerial);
                                    }
                                    if ($deviceId) {
                                        $q->orWhere('device_id', '=', $deviceId);
                                    }
                                });
                                $query->where('status', '=', 1);
                            });
                    })->first();

                if ($checkWorker) {
                    $dateTimeStr = isset($eventData->dateTime)
                        ? Carbon::parse($eventData->dateTime)->timezone('Asia/Tashkent')->format('Y-m-d H:i:s')
                        : date('Y-m-d H:i:s');

                    $rawStatus = $accessEventData->attendanceStatus ?? null;
                    $status = \App\Services\Hikvision\AttendanceStatusResolver::resolve(
                        $rawStatus,
                        (string) $accessEventData->employeeNoString,
                        $dateTimeStr
                    );
                    $label = \App\Enums\AttendanceStatus::label($status);

                    // Check if an event already exists at this exact second (e.g. from ISUP sync or soft-deleted)
                    $existingEvent = HikvisionAccessEvent::withTrashed()
                        ->where('employeeNoString', '=', $accessEventData->employeeNoString)
                        ->whereHas('hikvisionAccess', function ($query) use ($dateTimeStr) {
                            $query->withTrashed()->where('dateTime', $dateTimeStr);
                        })
                        ->first();

                    if ($existingEvent) {
                        // If existing event is not soft-deleted and has no picture, attach the picture now!
                        if (!$existingEvent->trashed() && !empty($filename) && empty($existingEvent->picture)) {
                            $existingEvent->picture = $filename;
                            $existingEvent->save();
                            if ($existingEvent->hikvisionAccess && !empty($shortSerial)) {
                                $existingEvent->hikvisionAccess->shortSerialNumber = $shortSerial;
                                $existingEvent->hikvisionAccess->save();
                            }
                        }
                    } else {
                        // 1. Save HikvisionAccess
                        $hikvisionAccess = HikvisionAccess::create([
                            'ipAddress' => $eventData->ipAddress ?? null,
                            'portNo' => $eventData->portNo ?? null,
                            'protocol' => $eventData->protocol ?? null,
                            'macAddress' => $macAddress,
                            'channelId' => $eventData->channelID ?? null,
                            'dateTime' => $dateTimeStr,
                            'activePostCount' => $eventData->activePostCount ?? null,
                            'eventType' => $eventData->eventType ?? null,
                            'eventState' => $eventData->eventState ?? null,
                            'eventDescription' => $eventData->eventDescription ?? null,
                            'shortSerialNumber' => $shortSerial,
                        ]);

                        $resolvedEventTime = app(\App\Services\Hikvision\EventTimeResolver::class)->resolve(
                            $dateTimeStr,
                            Carbon::now('Asia/Tashkent'),
                            $shortSerial ?: ($macAddress ?: $deviceId)
                        );

                        // 2. Save HikvisionAccessEvent
                        $hikvisionAccessEvent = new HikvisionAccessEvent([
                            'deviceName' => $accessEventData->deviceName ?? null,
                            'majorEventType' => $accessEventData->majorEventType ?? null,
                            'subEventType' => $accessEventData->subEventType ?? null,
                            'name' => $accessEventData->name ?? $checkWorker->name,
                            'cardReaderNo' => $accessEventData->cardReaderNo ?? null,
                            'employeeNoString' => $accessEventData->employeeNoString ?? null,
                            'serialNo' => (string)($accessEventData->serialNo ?? ''),
                            'userType' => $accessEventData->userType ?? null,
                            'currentVerifyMode' => $accessEventData->currentVerifyMode ?? null,
                            'frontSerialNo' => $accessEventData->frontSerialNo ?? null,
                            'attendanceStatus' => $status,
                            'label' => $label,
                            'mask' => $accessEventData->mask ?? null,
                            'picturesNumber' => $accessEventData->picturesNumber ?? null,
                            'purePwdVerifyEnable' => $accessEventData->purePwdVerifyEnable ?? null,
                            'picture' => $filename,
                            'work_time' => $checkWorker->work_time,
                            'end_time' => $checkWorker->end_time,
                        ]);
                        $hikvisionAccessEvent->hikvision_access_id = $hikvisionAccess->id;
                        $hikvisionAccessEvent->created_at = $resolvedEventTime;
                        $hikvisionAccessEvent->updated_at = $resolvedEventTime;
                        $hikvisionAccessEvent->save();

                        // 3. Save FaceRect
                        if (isset($accessEventData->FaceRect)) {
                            $hikvisionAccessEvent->faceReact()->create([
                                'height' => $accessEventData->FaceRect->height ?? null,
                                'width' => $accessEventData->FaceRect->width ?? null,
                                'x' => $accessEventData->FaceRect->x ?? null,
                                'y' => $accessEventData->FaceRect->y ?? null,
                            ]);
                        }
                    }

                    $webhookUrl = optional($checkWorker->branch->firm->firm_setting)->webhook_url;
                    if ($webhookUrl) {
                        try {
                            Http::post($webhookUrl, $request->all());
                        } catch (\Exception $e) {
                            telegramlog('Xatolik webhookUrl: ' . $e->getMessage() . $e->getLine());
                        }
                    }

                } else {
                    if ($request->hasFile('Picture')) {
                        \Illuminate\Support\Facades\Log::warning('Worker topilmadi (EmployeeNo: ' . ($accessEventData->employeeNoString ?? 'yo\'q') . ')');
                    }

                    return response()->json(['success' => false]);
                }

            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Hikvision Callback Error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'mac' => $rawMac ?? ($request->input('macAddress') ?? null),
                'serial' => $shortSerial ?? ($request->input('shortSerialNumber') ?? null),
                'employeeNoString' => (isset($accessEventData) && isset($accessEventData->employeeNoString))
                    ? $accessEventData->employeeNoString
                    : ($request->input('AccessControllerEvent.employeeNoString') ?? $request->input('employeeNoString')),
                'dateTime' => $dateTimeStr ?? ($request->input('dateTime') ?? null),
            ]);
            return response()->json(['error' => 'Server error occurred'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(FaceRect $faceRect)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FaceRect $faceRect)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFaceRectRequest $request, FaceRect $faceRect)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function getDeviceKey(Request $request)
    {
        $deviceId = $request->query('device_id');
        if (!$deviceId) {
            return response()->json(['error' => 'Device ID required'], 400);
        }

        $device = \App\Models\Branch\BranchDevice::where('device_id', $deviceId)->first();
        if ($device && !empty($device->encryption_key)) {
            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'encryption_key' => $device->encryption_key,
            ]);
        }

        return response()->json(['error' => 'Device not found or encryption key not set'], 404);
    }

    public function updateDeviceStatus(Request $request)
    {
        $deviceId = $request->input('device_id');
        $status = $request->input('status'); // 'online' or 'offline'
        $ip = $request->input('ip');
        $serial = $request->input('serial');

        if ($deviceId) {
            $device = \App\Models\Branch\BranchDevice::where('device_id', $deviceId)->first();
            if ($device) {
                $device->update([
                    'is_online' => ($status === 'online'),
                    'last_seen_at' => now(),
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function syncDeviceEvents(\App\Models\Branch\BranchDevice $device, \App\Services\Hikvision\HikvisionSyncService $syncService)
    {
        $res = $syncService->syncEventsFromDevice($device);
        return response()->json($res);
    }
}

