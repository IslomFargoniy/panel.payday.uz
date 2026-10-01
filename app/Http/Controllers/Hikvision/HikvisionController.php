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
    public function attendance(Request $request)
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;

        if ($request->month) {
            $month = $request->month;
            $monthNumber = Carbon::parse($month)->month;
            $year = Carbon::parse($month)->year;
        } else {
            $month = date('Y-m'); // '2025-05'
            $monthNumber = (int)date('m'); // '05'
            $year = (int)date('Y'); // '2025'
        }

        $monthStart = Carbon::create($year, $monthNumber, 1)->startOfMonth()->toDateTimeString();
        $monthEnd = Carbon::create($year, $monthNumber, 1)->endOfMonth()->toDateTimeString();

        $currentMonth = Carbon::now()->format('Y-m');
        $currentDay = Carbon::now()->day;

        if ($month === $currentMonth || empty($month)) {
            $daysInMonth = $currentDay;
        } else {
            $daysInMonth = Carbon::create($year, $monthNumber, 1)->daysInMonth;
        }

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->whereIn('attendanceStatus', \App\Enums\AttendanceStatus::inValues());
            }
        ]);

        if ($request->firm_id) {
            $workers = $workers->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        if ($request->branch_id) {
            $workers = $workers->where('branch_id', $request->branch_id);
        }

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $workers = $workers->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $workers = $workers->paginate($per_page);

        // Batch calculate holidays for paginated workers
        $holidaysMap = $this->batchGetHolidays($workers->items(), $month);
        foreach ($workers as $worker) {
            $worker->holidays = $holidaysMap[$worker->id] ?? [];
        }

        // Lean dropdown filters
        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return Inertia::render('attendance/index', [
            'worker' => $workers,
            'daysInMonth' => $daysInMonth,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ]);
    }

    public function daily_attendance(Request $request, Branch $branch)
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();
        if ($user && !$user->hasRole('Admin') && !$user->hasBranchAccess($branch)) {
            abort(403, 'Unauthorized access to this branch.');
        }

        $per_page = $request->per_page ? (int)$request->per_page : 15;
        $date = $request->date ?: date('Y-m-d');

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($date) {
                $query->whereBetween('created_at', ["{$date} 00:00:00", "{$date} 23:59:59"])
                    ->whereIn('attendanceStatus', \App\Enums\AttendanceStatus::allValues());
            }
        ])
            ->where('branch_id', '=', $branch->id);

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $workers = $workers->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $workers = $workers->paginate($per_page);

        // Standardize calculation with salary_report and monthly_attendance via AttendancePairingService
        $subReq = clone $request;
        $nextDay = Carbon::parse($date)->addDay()->format('Y-m-d');
        $subReq->merge([
            'branch_id' => $branch->id,
            'from' => $date,
            'to' => $nextDay,
        ]);
        $pairedEvents = app(\App\Services\Attendance\AttendancePairingService::class)
            ->buildPairedEventsQuery($subReq, $date, $nextDay)
            ->whereRaw("DATE(pe.from_time) = ?", [$date])
            ->get();
        $pairedByWorker = $pairedEvents->groupBy('worker_id');

        foreach ($workers as $worker) {
            $workerPairs = $pairedByWorker->get($worker->id, collect());
            $worker->paired_events = $workerPairs->values()->toArray();
            $worker->worked_minutes = (int) $workerPairs->sum('worked_minutes');
            $worker->late_minutes = (int) ($workerPairs->max('late_minutes') ?? 0);
        }

        return Inertia::render('daily_attendance/index', [
            'worker' => $workers,
            'branch' => $branch,
        ]);
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
                return response()->json(['error' => 'Device not recognized or inactive'], 403);
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
                    $status = \App\Enums\AttendanceStatus::normalize($rawStatus);

                    if (empty($status)) {
                        $lastHikvisionAccessEvent = HikvisionAccessEvent::where('employeeNoString', '=', $accessEventData->employeeNoString)
                            ->whereHas('hikvisionAccess', function ($query) use ($dateTimeStr) {
                                $query->where('dateTime', '<', $dateTimeStr);
                            })
                            ->join('hikvision_accesses', 'hikvision_access_events.hikvision_access_id', '=', 'hikvision_accesses.id')
                            ->orderBy('hikvision_accesses.dateTime', 'desc')
                            ->select('hikvision_access_events.*')
                            ->first();

                        $lastStatus = $lastHikvisionAccessEvent ? \App\Enums\AttendanceStatus::normalize($lastHikvisionAccessEvent->attendanceStatus) : null;
                        $status = ($lastStatus === \App\Enums\AttendanceStatus::IN->value)
                            ? \App\Enums\AttendanceStatus::OUT->value
                            : \App\Enums\AttendanceStatus::IN->value;
                    }

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
                        telegramlog('Worker topilmadi (EmployeeNo: ' . ($accessEventData->employeeNoString ?? 'yo\'q') . ')');
                    }

                    return response()->json(['success' => false]);
                }

            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Hikvision Callback Error: ' . $e->getMessage() . ' line: ' . $e->getLine());
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

