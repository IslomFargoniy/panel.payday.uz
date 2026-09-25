<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    /**
     * Auth via Telegram Web App initData
     */
    public function authenticate(Request $request)
    {
        try {
            $telegram_id = $request->input('telegram_id');
            $worker = Worker::where('telegram_id', '=', $telegram_id)->first();
 
             if (!$worker) {
                 return response()->json([
                     'success' => false,
                     'message' => 'Sizning Telegram profilingiz xodimlar ro\'yxatidan topilmadi. Iltimos, adminstratorga murojaat qiling.'
                 ], 404);
             }
 
             // Return worker details and their current status for today
             $todayStr = Carbon::now()->toDateString();
             
             $todayCheckIn = HikvisionAccessEvent::where('employeeNoString', '=', $worker->employeeNoString)
                 ->whereDate('created_at', $todayStr)
                 ->whereIn('attendanceStatus', ['checkIn', 'keldi', 'entered'])
                 ->orderBy('created_at', 'asc')
                 ->first();
                 
             $todayCheckOut = HikvisionAccessEvent::where('employeeNoString', '=', $worker->employeeNoString)
                 ->whereDate('created_at', $todayStr)
                 ->whereIn('attendanceStatus', ['checkOut', 'ketdi', 'exited'])
                 ->orderBy('created_at', 'desc')
                 ->first();

            return response()->json([
                'success' => true,
                'worker' => $worker,
                'status' => [
                    'has_checked_in' => !!$todayCheckIn,
                    'has_checked_out' => !!$todayCheckOut,
                    'check_in_time' => $todayCheckIn ? $todayCheckIn->created_at->format('H:i') : null,
                    'check_out_time' => $todayCheckOut ? $todayCheckOut->created_at->format('H:i') : null,
                ]
            ]);
        } catch (\Exception $e) {
            if (function_exists('telegramlog')) {
                telegramlog("Auth xatosi: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record Attendance (Check-in / Check-out)
     */
    public function recordAttendance(Request $request)
    {
        try {
            $request->validate([
                'telegram_id' => 'required|numeric',
                'type' => 'required|in:checkIn,checkOut',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'picture' => 'required|image|max:5120', 
            ]);

            $telegram_id = $request->input('telegram_id');
            $status = $request->input('type');

            // 1. Validate Worker AND Firm validity (mirroring HikvisionController)
            $worker = Worker::where('telegram_id', '=', $telegram_id)
                ->where('status', '=', 1)
                ->whereHas('branch', function ($query) {
                    $query->whereHas('firm', function ($query) {
                        $query->where('status', '=', 1)
                              ->where('valid_date', '>=', date('Y-m-d'));
                    });
                })->first();

            if (!$worker) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Xodim topilmadi yoki firma muddati tugagan. Iltimos adminstratorga murojaat qiling.'
                ], 404);
            }

            // 2. Location validation (Mandatory 50m radius)
            $lat1 = $request->input('latitude');
            $lon1 = $request->input('longitude');

            if (!$worker->branch || !$worker->branch->latitude || !$worker->branch->longitude) {
                return response()->json(['success' => false, 'message' => 'Filial geolokatsiyasi kiritilmagan.'], 400);
            }

            $lat2 = $worker->branch->latitude;
            $lon2 = $worker->branch->longitude;

            $earthRadius = 6371000; 
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earthRadius * $c;

            if ($distance > 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siz filialdan uzoqdasiz (' . round($distance) . ' metr). Davomat uchun filialga 50 metr yaqinlashing.'
                ], 400);
            }

            // 3. Sequential Status Validation (mirroring HikvisionController)
            $lastHikvisionAccessEvent = \App\Models\Hikvision\HikvisionAccessEvent::where('employeeNoString', '=', $worker->employeeNoString)
                ->whereDate('created_at', Carbon::today())
                ->latest()
                ->first();

            $lastStatus = $lastHikvisionAccessEvent ? $lastHikvisionAccessEvent->attendanceStatus : null;

            switch ($status) {
                case 'checkIn':
                    if (!is_null($lastStatus) && $lastStatus !== 'checkOut') {
                        return response()->json(['success' => false, 'message' => 'Siz allaqachon ishga kelgansiz.'], 400);
                    }
                    break;
                case 'checkOut':
                    if ($lastStatus !== 'checkIn') {
                        return response()->json(['success' => false, 'message' => 'Siz hali ishga kelmagansiz.'], 400);
                    }
                    break;
                default:
                    return response()->json(['success' => false, 'message' => 'Noto\'g\'ri holat.'], 400);
            }

            // 4. Handle Picture Upload (Store in hikvision/telegram folder)
            $filename = '';
            if ($request->hasFile('picture')) {
                $picture = $request->file('picture');
                $filename = time() . '_' . rand(1, 50) . '_' . $picture->getClientOriginalName();
                $picture->storeAs("hikvision/TELEGRAM", $filename, 'public');
            }

            // 5. Save HikvisionAccess (Aligning with Controller)
            $hikvisionAccess = \App\Models\Hikvision\HikvisionAccess::create([
                'ipAddress' => $request->ip(),
                'portNo' => 0,
                'protocol' => 'Telegram',
                'macAddress' => 'TELEGRAM_BOT',
                'channelId' => 1,
                'dateTime' => now()->format('Y-m-d H:i:s'),
                'activePostCount' => 1,
                'eventType' => 'telegram_attendance',
                'eventDescription' => 'Telegram Mini App Attendance',
                'shortSerialNumber' => 'TELEGRAM',
            ]);

            // 6. Save HikvisionAccessEvent (Aligning with Controller)
            $event = $hikvisionAccess->hikvisionAccessEvent()->create([
                'deviceName' => 'Telegram_Mini_App',
                'majorEventType' => 5,
                'subEventType' => 75,
                'name' => $worker->name,
                'cardReaderNo' => 1,
                'employeeNoString' => $worker->employeeNoString,
                'serialNo' => 'TMA_' . time(),
                'userType' => 'normal',
                'currentVerifyMode' => 'face',
                'attendanceStatus' => $status,
                'label' => $status === 'checkIn' ? 'Keldi' : 'Ketdi',
                'mask' => 'unknown',
                'picturesNumber' => 1,
                'purePwdVerifyEnable' => false,
                'picture' => $filename,
                'work_time' => $worker->work_time,
                'end_time' => $worker->end_time,
            ]);

            // 7. Webhook Trigger (mirroring HikvisionController)
            $webhookUrl = optional($worker->branch->firm->firm_setting)->webhook_url;
            if ($webhookUrl) {
                try {
                    \Illuminate\Support\Facades\Http::post($webhookUrl, [
                        'telegram_id' => $telegram_id,
                        'name' => $worker->name,
                        'status' => $status,
                        'time' => now()->toDateTimeString(),
                        'device' => 'Telegram'
                    ]);
                } catch (\Exception $e) {
                    Log::error("Webhook error: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => $status === 'checkIn' ? 'Davomat qabul qilindi (Ishga kelindi).' : 'Davomat qabul qilindi (Ishdan ketildi).',
                'time' => $event->created_at->format('H:i')
            ]);

        } catch (\Exception $e) {
            $errorMsg = "Telegram attendance error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            Log::error($errorMsg);
            if (function_exists('telegramlog')) telegramlog($errorMsg);
            
            return response()->json([
                'success' => false,
                'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
            ], 500);
        }
    }
}
