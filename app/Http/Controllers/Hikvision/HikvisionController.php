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
                    ->whereIn('attendanceStatus', ["checkIn", "keldi", "CheckIn", "entered"]);
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
        $per_page = $request->per_page ? (int)$request->per_page : 15;
        $date = $request->date ?: date('Y-m-d');

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($date) {
                $query->whereBetween('created_at', ["{$date} 00:00:00", "{$date} 23:59:59"])
                    ->whereIn('attendanceStatus', ["checkIn", "checkOut", "keldi", "ketdi", "entered", "exited"]);
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

        // Standardize calculation with salary_report and monthly_attendance via ReportController
        $reportController = new \App\Http\Controllers\ReportController();
        $subReq = clone $request;
        $subReq->merge([
            'branch_id' => $branch->id,
            'from' => $date,
            'to' => $date,
        ]);
        $pairedEvents = $reportController->buildPairedEventsQuery($subReq, $date, $date)->get();
        $pairedByWorker = $pairedEvents->groupBy('worker_id');

        foreach ($workers as $worker) {
            $workerPairs = $pairedByWorker->get($worker->id, collect());
            $worker->paired_events = $workerPairs->values()->toArray();
            $worker->worked_minutes = (int) $workerPairs->sum('worked_minutes');
            $worker->late_minutes = (int) ($workerPairs->first()->late_minutes ?? 0);
        }

        return Inertia::render('daily_attendance/index', [
            'worker' => $workers,
            'branch' => $branch,
        ]);
    }

    private function batchGetHolidays(array $workers, string $month): array
    {
        if (empty($workers)) {
            return [];
        }

        $from = Carbon::parse($month)->startOfMonth();
        $to = Carbon::parse($month)->endOfMonth();
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $workerIds = array_map(fn($w) => $w->id, $workers);
        $branchIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch_id, $workers))));
        $firmIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch?->firm_id, $workers))));

        // 1. Worker days & Branch days
        $workerDaysMap = \App\Models\Worker\WorkerDay::whereIn('worker_id', $workerIds)
            ->with('day')
            ->get()
            ->groupBy('worker_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->toArray());

        $branchDaysMap = \App\Models\Branch\BranchDay::whereIn('branch_id', $branchIds)
            ->with('day')
            ->get()
            ->groupBy('branch_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->toArray());

        // 2. Holidays
        $workerHolidaysMap = \App\Models\Worker\WorkerHoliday::whereIn('worker_id', $workerIds)
            ->whereDate('from', '<=', $toStr)
            ->whereDate('to', '>=', $fromStr)
            ->get(['worker_id', 'from', 'to'])
            ->groupBy('worker_id');

        $branchHolidaysMap = \App\Models\Branch\BranchHoliday::whereIn('branch_id', $branchIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['branch_id', 'date'])
            ->groupBy('branch_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $firmHolidaysMap = \App\Models\Firm\FirmHoliday::whereIn('firm_id', $firmIds)
            ->whereBetween('date', [$fromStr, $toStr])
            ->get(['firm_id', 'date'])
            ->groupBy('firm_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $dates = iterator_to_array(\Carbon\CarbonPeriod::create($from, $to));
        $result = [];

        foreach ($workers as $worker) {
            $workingDayIndexes = $workerDaysMap->get($worker->id)
                ?? $branchDaysMap->get($worker->branch_id)
                ?? range(1, 7);

            $weekendIndexes = array_diff(range(1, 7), $workingDayIndexes);
            $wHolidays = $workerHolidaysMap->get($worker->id) ?? collect();
            $bHolidays = $branchHolidaysMap->get($worker->branch_id) ?? [];
            $fHolidays = $firmHolidaysMap->get($worker->branch?->firm_id) ?? [];

            $offDayIndexes = [];
            foreach ($dates as $date) {
                $currentDayIndex = $date->dayOfWeek + 1;
                $dateStr = $date->toDateString();

                $isWeekend = in_array($currentDayIndex, $weekendIndexes);
                $isBranchHoliday = isset($bHolidays[$dateStr]);
                $isFirmHoliday = isset($fHolidays[$dateStr]);

                $isWorkerHoliday = false;
                foreach ($wHolidays as $wh) {
                    if ($dateStr >= $wh->from && $dateStr <= $wh->to) {
                        $isWorkerHoliday = true;
                        break;
                    }
                }

                if ($isWeekend || $isWorkerHoliday || $isBranchHoliday || $isFirmHoliday) {
                    $offDayIndexes[] = (int)$date->format('j');
                }
            }
            $result[$worker->id] = $offDayIndexes;
        }

        return $result;
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

            $rawEvent = $request->AccessControllerEvent ?? $request->event_log ?? $request->all();
            $eventData = is_string($rawEvent) ? json_decode($rawEvent) : json_decode(json_encode($rawEvent));

            if (isset($eventData->AccessControllerEvent)) {
                if (!isset($eventData->AccessControllerEvent->attendanceStatus) || $eventData->AccessControllerEvent->attendanceStatus === 'undefined' || $eventData->AccessControllerEvent->attendanceStatus === 'CheckIn') {
                    $eventData->AccessControllerEvent->attendanceStatus = 'checkIn';
                    if (empty($eventData->AccessControllerEvent->label)) {
                        $eventData->AccessControllerEvent->label = 'Keldi';
                    }
                }
                $macAddress = $eventData->macAddress ?? ($eventData->AccessControllerEvent->macAddress ?? null);
                $deviceId = $eventData->device_id ?? null;
                $shortSerial = $eventData->shortSerialNumber ?? null;

                // Resolve matching BranchDevice
                $branchDevice = null;
                if ($macAddress || $shortSerial || $deviceId) {
                    $branchDevice = \App\Models\Branch\BranchDevice::where('status', 1)
                        ->where(function ($q) use ($macAddress, $shortSerial, $deviceId) {
                            if ($macAddress) {
                                $q->orWhere('mac_address', '=', $macAddress);
                            }
                            if ($shortSerial && $shortSerial !== 'default') {
                                $q->orWhere('device_id', '=', $shortSerial);
                            }
                            if ($deviceId) {
                                $q->orWhere('device_id', '=', $deviceId);
                            }
                        })->first();
                }

                if ($branchDevice) {
                    $shortSerial = $branchDevice->device_id ?: ($shortSerial ?: 'default');
                    $branchDevice->update([
                        'is_online' => true,
                        'last_seen_at' => now(),
                    ]);
                } elseif (empty($shortSerial) || $shortSerial === 'default') {
                    $shortSerial = $eventData->AccessControllerEvent->serialNo ?? 'default';
                }

                $accessEventData = $eventData->AccessControllerEvent;

                $filename = '';
                if ($request->hasFile('Picture')) {
                    $picture = $request->file('Picture');
                    $filename = time() . '_' . rand(1, 50) . '_' . $picture->getClientOriginalName();
                    $savedPath = $picture->storeAs("hikvision/$shortSerial", $filename, 'public');

                    $caption = 'Foydalanuvchi: ' . ($accessEventData->name ?? 'Noma\'lum') .
                        "\nHolati: " . ($accessEventData->attendanceStatus ?? 'Noma\'lum') .
                        "\nPath : $savedPath" .
                        "\nEmployeeNo : " . ($accessEventData->employeeNoString ?? 'yo\'q');

                    telegramlog($caption);
                } elseif ($request->hasFile('picture')) {
                    $picture = $request->file('picture');
                    $filename = time() . '_' . rand(1, 50) . '_' . $picture->getClientOriginalName();
                    $picture->storeAs("hikvision/$shortSerial", $filename, 'public');
                } elseif ($request->filled('picture')) {
                    $filename = $request->input('picture');
                } elseif (!empty($eventData->picture)) {
                    $filename = $eventData->picture;
                } elseif (!empty($accessEventData->picture)) {
                    $filename = $accessEventData->picture;
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

                    $lastHikvisionAccessEvent = HikvisionAccessEvent::with([])
                        ->where('employeeNoString', '=', $accessEventData->employeeNoString)
                        ->whereHas('hikvisionAccess', function ($query) {
                            $query->whereRaw("date(dateTime) = current_date");
                        })
                        ->latest('id')
                        ->first();

                    $lastStatus = $lastHikvisionAccessEvent ? $lastHikvisionAccessEvent->attendanceStatus : null;

                    $status = $accessEventData->attendanceStatus ?? null;
                    if (empty($status) || $status === 'undefined') {
                        $status = ($lastStatus === 'checkIn') ? 'checkOut' : 'checkIn';
                    }

                    $label = ($status === 'checkIn') ? 'Keldi' : 'Ketdi';

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

                        // 2. Save HikvisionAccessEvent
                        $hikvisionAccessEvent = $hikvisionAccess->hikvisionAccessEvent()->create([
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
            if ($request->hasFile('Picture')) {
                telegramlog('Xatolik: ' . $e->getMessage() . ' line: ' . $e->getLine());
            }
            return response()->json(['error' => $e->getMessage()]);
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

        // Fallback default format PayDay{branch_id}2026 or PayDay142026
        return response()->json([
            'success' => true,
            'device_id' => $deviceId,
            'encryption_key' => 'PayDay142026',
        ]);
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
                    'status' => 1,
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

