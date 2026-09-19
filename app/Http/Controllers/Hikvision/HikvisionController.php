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
        if ($request->per_page) {
            $per_page = $request->per_page;
        } else {
            $per_page = 10;
        }

        if ($request->month) {
            $month = $request->month;
            $monthNumber = Carbon::parse($month)->month;
            $year = Carbon::parse($month)->year;
        } else {
            $month = date('Y-m'); // '2025-05'
            $monthNumber = date('m'); // '05'
            $year = date('Y'); // '2025'
        }

        $currentMonth = Carbon::now()->format('Y-m'); // '2025-05'
        $currentDay = Carbon::now()->day; // Get the current day of the month (e.g., 12 for May 12)

        if ($month === $currentMonth || empty($month)) {
            $daysInMonth = $currentDay;
        } else {
            $daysInMonth = Carbon::create($year, $monthNumber, 1)->daysInMonth;
        }

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($monthNumber, $year) {
                $query->whereMonth('created_at', $monthNumber)
                    ->whereYear('created_at', $year)
                    ->where('attendanceStatus', "checkIn");
            }
        ])
            ->select(
                'workers.*'
            );

        if ($request->firm_id) {
            $workers = $workers->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        if ($request->branch_id) {
            $workers = $workers->where('branch_id', $request->branch_id);
        }


        $firms = Firm::with([]);
        $branches = Branch::with([]);

        if (!Auth::user()->hasRole('Admin')) {
            $firms->whereHas('user_firms', function ($query) {
                $query->where('user_id', Auth::id());
            });

            $branches = $branches->whereHas('firm', function ($query) {
                $query->whereHas('user_firms', function ($query) {
                    $query->where('user_id', Auth::id());
                });
            });

            $workers = $workers->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        $firms = $firms->get();
        $branches = $branches->get();
        $workers = $workers->paginate($per_page);

        $workers->getCollection()->transform(function ($worker) use ($month) {
            $worker->holidays = $worker->getHoliday($month);
            return $worker;
        });

        return Inertia::render('attendance/index', [
            'worker' => $workers,
            'daysInMonth' => $daysInMonth,
            'firms' => $firms,
            'branches' => $branches,
        ]);
    }

    public function daily_attendance(Request $request, Branch $branch)
    {
        if ($request->per_page) {
            $per_page = $request->per_page;
        } else {
            $per_page = 10;
        }

        if ($request->date) {
            $date = $request->date;
        } else {
            $date = date('Y-m-d'); // '2025-05-12'
        }

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($date) {
                $query->whereDate('created_at', $date)
                    ->whereIn('attendanceStatus', ["checkIn", "checkOut"]);
            }
        ])
            ->where('branch_id', '=', $branch->id)
            ->select(
                'workers.*'
            );

        if (!Auth::user()->hasRole('Admin')) {
            $workers = $workers->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        $workers = $workers->paginate($per_page);

//        dd($workers,$request->all());

        return Inertia::render('daily_attendance/index', [
            'worker' => $workers,
            'branch' => $branch,
        ]);
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
            $rawEvent = $request->AccessControllerEvent ?? $request->event_log;
            $eventData = is_string($rawEvent) ? json_decode($rawEvent) : json_decode(json_encode($rawEvent));

            if (isset($eventData->AccessControllerEvent)) {
                if (!isset($eventData->AccessControllerEvent->attendanceStatus) || $eventData->AccessControllerEvent->attendanceStatus === 'undefined' || $eventData->AccessControllerEvent->attendanceStatus === 'CheckIn') {
                    $eventData->AccessControllerEvent->attendanceStatus = 'checkIn';
                    if (empty($eventData->AccessControllerEvent->label)) {
                        $eventData->AccessControllerEvent->label = 'Keldi';
                    }
                }
                $filename = '';
                if ($request->hasFile('Picture')) {
                    $picture = $request->file('Picture');

                    // Fayl nomini generatsiya qilish (ixtiyoriy)
                    $filename = time() . '_' . rand(1, 50) . '_' . $picture->getClientOriginalName();

                    // Saqlash
                    $savedPath = $picture->storeAs("hikvision/$eventData->shortSerialNumber", $filename, 'public'); // 3-chi parametr: 'public'

                    // Matnli xabar yuborish
                    $caption = 'Foydalanuvchi: ' . ($eventData->AccessControllerEvent->name ?? 'Noma\'lum') .
                        "\nHolati: " . ($eventData->AccessControllerEvent->attendanceStatus ?? 'Noma\'lum') .
                        "\nPath : $savedPath" .
                        "\nEmployeeNo : {$eventData->AccessControllerEvent->employeeNoString}";

                    telegramlog($caption);
                }


                $accessEventData = $eventData->AccessControllerEvent;

                $checkWorker = Worker::with([])
                    ->where('employeeNoString', '=', $accessEventData->employeeNoString)
                    ->where('status', '=', 1)
                    ->whereHas('branch', function ($query) use ($eventData) {
                        $query->whereHas('firm', function ($query) use ($eventData) {
                            $query->where('status', '=', 1)
                                ->where('valid_date', '>=', date('Y-m-d'));
                        })
                            ->whereHas('branch_devices', function ($query) use ($eventData) {
                                $query->where('mac_address', '=', $eventData->macAddress)
                                    ->where('status', '=', 1);
                            });
                    })->first();

                if ($checkWorker) {

                    $lastHikvisionAccessEvent = HikvisionAccessEvent::with([])
                        ->where('employeeNoString', '=', $accessEventData->employeeNoString)
                        ->whereHas('hikvisionAccess', function ($query) use ($accessEventData) {
                            $query->whereRaw("date(dateTime) = current_date");
                        })
                        ->latest()
                        ->first();

                    $lastStatus = $lastHikvisionAccessEvent ? $lastHikvisionAccessEvent->attendanceStatus : null;

                    $status = $accessEventData->attendanceStatus;

                    switch ($status) {
                        case 'checkIn':
                            if (is_null($lastStatus) || $lastStatus === 'checkOut') {
                                // checkIn allowed
                            } else {
                                throw new \Exception('Kelgansiz.');
                            }
                            break;

                        case 'checkOut':
                            if ($lastStatus === 'checkIn' || $lastStatus === 'breakIn') {
                                // checkOut allowed
                            } else {
                                throw new \Exception('Kelmagansiz yoki Abetdasiz.');
                            }
                            break;

                        case 'breakIn':
                            if ($lastStatus === 'breakOut') {
                                // breakIn allowed
                            } else {
                                throw new \Exception('Abetda emassiz.');
                            }
                            break;

                        case 'breakOut':
                            if ($lastStatus === 'breakIn' || $lastStatus === 'checkIn') {
                                // breakOut allowed
                            } else {
                                throw new \Exception('Abetdasiz.');
                            }
                            break;

                        default:
                            throw new \Exception('Noto‘g‘ri attendance holati.');
                    }


                    // 1. Save HikvisionAccess
                    $hikvisionAccess = HikvisionAccess::create([
                        'ipAddress' => $eventData->ipAddress ?? null,
                        'portNo' => $eventData->portNo ?? null,
                        'protocol' => $eventData->protocol ?? null,
                        'macAddress' => $eventData->macAddress ?? null,
                        'channelId' => $eventData->channelID ?? null,
//                        'dateTime' => date('Y-m-d H:i:s'),
                        'dateTime' => isset($eventData->dateTime)
                            ? Carbon::parse($eventData->dateTime)->timezone('Asia/Tashkent')->format('Y-m-d H:i:s')
                            : null,
                        'activePostCount' => $eventData->activePostCount ?? null,
                        'eventType' => $eventData->eventType ?? null,
                        'eventState' => $eventData->eventState ?? null,
                        'eventDescription' => $eventData->eventDescription ?? null,
                        'shortSerialNumber' => $eventData->shortSerialNumber ?? null,
                    ]);

                    // 2. Save HikvisionAccessEvent
                    $hikvisionAccessEvent = $hikvisionAccess->hikvisionAccessEvent()->create([
                        'deviceName' => $accessEventData->deviceName ?? null,
                        'majorEventType' => $accessEventData->majorEventType ?? null,
                        'subEventType' => $accessEventData->subEventType ?? null,
                        'name' => $accessEventData->name ?? null,
                        'cardReaderNo' => $accessEventData->cardReaderNo ?? null,
                        'employeeNoString' => $accessEventData->employeeNoString ?? null,
                        'serialNo' => $accessEventData->serialNo ?? null,
                        'userType' => $accessEventData->userType ?? null,
                        'currentVerifyMode' => $accessEventData->currentVerifyMode ?? null,
                        'frontSerialNo' => $accessEventData->frontSerialNo ?? null,
                        'attendanceStatus' => $accessEventData->attendanceStatus ?? null,
                        'label' => $accessEventData->label ?? null,
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
    public function destroy(FaceRect $faceRect)
    {
        //
    }
}
