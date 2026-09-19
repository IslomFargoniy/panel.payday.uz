<?php

namespace App\Services\Hikvision;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HikvisionSyncService
{
    /**
     * Sync single worker (and face photo) to all devices in worker's branch
     */
    public function syncWorker(Worker $worker): array
    {
        $results = [];

        if (!$worker->branch) {
            $worker->load('branch.branch_devices');
        }

        $devices = $worker->branch->branch_devices ?? [];

        foreach ($devices as $device) {
            $results[$device->id] = $this->syncWorkerToDevice($worker, $device);
        }

        return $results;
    }

    /**
     * Sync single worker to a specific branch device via ISAPI/ISUP
     */
    public function syncWorkerToDevice(Worker $worker, BranchDevice $device): array
    {
        if (!$device->status) {
            return ['success' => false, 'message' => 'Device is disabled'];
        }

        try {
            // 1. Prepare User Payload
            $employeeNo = (string)($worker->employeeNoString ?: $worker->id);
            $name = $worker->name ?: 'Worker ' . $employeeNo;

            $userData = [
                'UserInfo' => [
                    'employeeNo' => $employeeNo,
                    'name' => $name,
                    'userType' => 'normal',
                    'closeDelayEnabled' => false,
                    'Valid' => [
                        'enable' => true,
                        'beginTime' => Carbon::now()->format('Y-m-d\T00:00:00'),
                        'endTime' => Carbon::now()->addYears(10)->format('Y-m-d\T23:59:59'),
                        'timeType' => 'local',
                    ],
                    'doorRight' => '1',
                    'RightPlan' => [
                        [
                            'doorNo' => 1,
                            'planTemplateNo' => '1',
                        ]
                    ],
                    'gender' => 'male',
                ]
            ];

            // Route through ISUP Gateway if ISUP connection type or device_id is present
            if ($device->connection_type === 'isup' && !empty($device->device_id)) {
                $isupRes = \Illuminate\Support\Facades\Http::timeout(6)->post('http://127.0.0.1:7661/api/isapi', [
                    'device_id' => $device->device_id,
                    'method' => 'POST',
                    'url' => 'POST /ISAPI/AccessControl/UserInfo/Record?format=json',
                    'body' => json_encode($userData),
                ]);

                $status = $isupRes->json();

                // Upload face picture via ISUP using public FaceURL
                if ($worker->avatar && Storage::disk('public')->exists($worker->avatar)) {
                    $faceUrl = "https://panel.payday.uz/storage/" . ltrim($worker->avatar, '/');
                    $facePayload = [
                        'faceLibType' => 'blackFD',
                        'FDID' => '1',
                        'FPID' => $employeeNo,
                        'faceURL' => $faceUrl,
                    ];

                    $faceRes = \Illuminate\Support\Facades\Http::timeout(10)->post('http://127.0.0.1:7661/api/isapi', [
                        'device_id' => $device->device_id,
                        'method' => 'POST',
                        'url' => 'POST /ISAPI/Intelligent/FDLib/FaceDataRecord?format=json',
                        'body' => json_encode($facePayload),
                    ]);

                    Log::info("HikvisionSync [ISUP Face]: Worker {$employeeNo} face uploaded", [
                        'faceUrl' => $faceUrl,
                        'response' => $faceRes->json(),
                    ]);
                }

                Log::info("HikvisionSync [ISUP]: Worker {$employeeNo} synced to device {$device->device_id}", [
                    'status' => $status
                ]);

                return ['success' => true, 'response' => $status];
            }

            // Fallback to local network direct HTTP if on same network
            $deviceIp = $device->ip_address ?? '192.168.1.107';
            $username = $device->username ?? 'admin';
            $password = $device->password ?? 'hikvision1';

            $client = new Client([
                'base_uri' => "http://{$deviceIp}/",
                'auth' => [$username, $password, 'digest'],
                'timeout' => 5,
                'connect_timeout' => 3,
            ]);

            // Step 1: Create or Set Up User in Terminal
            $response = $client->post('ISAPI/AccessControl/UserInfo/Record?format=json', [
                'json' => $userData,
            ]);

            $status = json_decode($response->getBody()->getContents(), true);

            // Step 2: Upload Face if Worker has avatar/photo
            if ($worker->avatar && Storage::disk('public')->exists($worker->avatar)) {
                $photoPath = Storage::disk('public')->path($worker->avatar);
                $this->uploadFaceToDevice($client, $employeeNo, $photoPath);
            }

            Log::info("HikvisionSync [HTTP]: Worker {$employeeNo} synced to device {$device->id}", [
                'status' => $status
            ]);

            return ['success' => true, 'response' => $status];

        } catch (\Exception $e) {
            Log::warning("HikvisionSync: Failed to sync worker to device {$device->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload face photo to Hikvision terminal via ISAPI FDLib
     */
    protected function uploadFaceToDevice(Client $client, string $employeeNo, string $photoPath): bool
    {
        try {
            $faceRecord = json_encode([
                'faceLibType' => 'blackFD',
                'FDID' => '1',
                'FPID' => $employeeNo,
            ]);

            $client->put('ISAPI/Intelligent/FDLib/FaceDataRecord?format=json', [
                'multipart' => [
                    [
                        'name' => 'FaceDataRecord',
                        'contents' => $faceRecord,
                        'headers' => ['Content-Type' => 'application/json'],
                    ],
                    [
                        'name' => 'img',
                        'contents' => fopen($photoPath, 'r'),
                        'filename' => 'face.jpg',
                        'headers' => ['Content-Type' => 'image/jpeg'],
                    ],
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("HikvisionSync: Failed to upload face for worker {$employeeNo}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete worker from all devices in worker's branch
     */
    public function deleteWorker(Worker $worker): array
    {
        $results = [];

        if (!$worker->branch) {
            $worker->load('branch.branch_devices');
        }

        $devices = $worker->branch->branch_devices ?? [];

        foreach ($devices as $device) {
            try {
                $employeeNo = (string)($worker->employeeNoString ?: $worker->id);

                if ($device->connection_type === 'isup' && !empty($device->device_id)) {
                    $delRes = \Illuminate\Support\Facades\Http::timeout(6)->post('http://127.0.0.1:7661/api/isapi', [
                        'device_id' => $device->device_id,
                        'method' => 'PUT',
                        'url' => 'PUT /ISAPI/AccessControl/UserInfo/Delete?format=json',
                        'body' => json_encode([
                            'UserInfoDelCond' => [
                                'EmployeeNoList' => [
                                    ['employeeNo' => $employeeNo]
                                ]
                            ]
                        ]),
                    ]);
                    $results[$device->id] = $delRes->json();
                    continue;
                }

                $deviceIp = $device->ip_address ?? '192.168.1.107';
                $username = $device->username ?? 'admin';
                $password = $device->password ?? 'hikvision1';

                $client = new Client([
                    'base_uri' => "http://{$deviceIp}/",
                    'auth' => [$username, $password, 'digest'],
                    'timeout' => 5,
                    'connect_timeout' => 3,
                ]);

                $response = $client->put('ISAPI/AccessControl/UserInfo/Delete?format=json', [
                    'json' => [
                        'UserInfoDelCond' => [
                            'EmployeeNoList' => [
                                ['employeeNo' => $employeeNo]
                            ]
                        ]
                    ]
                ]);

                $results[$device->id] = json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                Log::warning("HikvisionSync: Failed to delete worker from device {$device->id}: " . $e->getMessage());
                $results[$device->id] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Pull attendance event logs from ISUP device
     */
    public function syncEventsFromDevice(BranchDevice $device, ?string $startTime = null, ?string $endTime = null): array
    {
        if ($device->connection_type !== 'isup' || empty($device->device_id)) {
            return ['success' => false, 'message' => 'Device is not connected via ISUP'];
        }

        try {
            $startTime = $startTime ?: Carbon::now()->subDays(1)->format('Y-m-d\T00:00:00+05:00');
            $endTime = $endTime ?: Carbon::now()->addHours(1)->format('Y-m-d\T23:59:59+05:00');

            $payload = [
                'AcsEventCond' => [
                    'searchID' => 'payday_' . time(),
                    'searchResultPosition' => 0,
                    'maxResults' => 100,
                    'major' => 5, // Access granted (Face, card, fingerprint)
                    'minor' => 0, // All
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                ]
            ];

            $res = \Illuminate\Support\Facades\Http::timeout(10)->post('http://127.0.0.1:7661/api/isapi', [
                'device_id' => $device->device_id,
                'method' => 'POST',
                'url' => 'POST /ISAPI/AccessControl/AcsEvent?format=json',
                'body' => json_encode($payload),
            ]);

            if (!$res->successful()) {
                return ['success' => false, 'error' => $res->body()];
            }

            $body = $res->json();
            $rawResponse = $body['response'] ?? '';
            $acsData = is_string($rawResponse) ? json_decode($rawResponse, true) : $rawResponse;
            $infoList = $acsData['AcsEvent']['InfoList'] ?? [];

            $syncedCount = 0;
            $device->loadMissing('branch.workers');

            foreach ($infoList as $event) {
                $employeeNo = $event['employeeNoString'] ?? null;
                if (empty($employeeNo)) {
                    continue;
                }

                $worker = $device->branch?->workers->firstWhere('employeeNoString', $employeeNo);
                if (!$worker) {
                    continue;
                }

                $rawTime = $event['time'] ?? null;
                if (!$rawTime) {
                    continue;
                }

                $eventDateTime = Carbon::parse($rawTime)->timezone('Asia/Tashkent');
                $eventDateStr = $eventDateTime->format('Y-m-d H:i:s');
                $serialNo = (string)($event['serialNo'] ?? '');

                // Check for duplicate
                $existing = \App\Models\Hikvision\HikvisionAccessEvent::where('employeeNoString', $employeeNo)
                    ->where(function ($q) use ($eventDateStr, $serialNo) {
                        $q->where('created_at', $eventDateStr);
                        if (!empty($serialNo)) {
                            $q->orWhere('serialNo', $serialNo);
                        }
                    })
                    ->exists();

                if ($existing) {
                    continue;
                }

                // Determine attendance status (checkIn vs checkOut)
                $lastEvent = \App\Models\Hikvision\HikvisionAccessEvent::where('employeeNoString', $employeeNo)
                    ->whereDate('created_at', $eventDateTime->format('Y-m-d'))
                    ->where('created_at', '<', $eventDateStr)
                    ->latest()
                    ->first();

                $lastStatus = $lastEvent ? $lastEvent->attendanceStatus : null;
                $attendanceStatus = ($lastStatus === 'checkIn') ? 'checkOut' : 'checkIn';

                $access = \App\Models\Hikvision\HikvisionAccess::create([
                    'ipAddress' => $device->ip_address,
                    'macAddress' => $device->mac_address,
                    'shortSerialNumber' => $device->serial_number ?: $device->device_id,
                    'dateTime' => $eventDateStr,
                    'eventType' => 'AccessControl',
                    'eventDescription' => 'ISUP AcsEvent Sync',
                ]);

                $eventModel = new \App\Models\Hikvision\HikvisionAccessEvent([
                    'deviceName' => $device->name ?: 'Hikvision Terminal',
                    'name' => $event['name'] ?? $worker->name,
                    'employeeNoString' => $employeeNo,
                    'serialNo' => $serialNo,
                    'attendanceStatus' => $attendanceStatus,
                    'currentVerifyMode' => $event['currentVerifyMode'] ?? 'face',
                    'work_time' => $worker->work_time,
                    'end_time' => $worker->end_time,
                ]);
                $eventModel->hikvision_access_id = $access->id;
                $eventModel->timestamps = false;
                $eventModel->created_at = $eventDateTime;
                $eventModel->updated_at = $eventDateTime;
                $eventModel->save();

                $syncedCount++;
            }

            Log::info("HikvisionSync [ISUP Events]: Device {$device->device_id} synced {$syncedCount} events.");
            return ['success' => true, 'synced_count' => $syncedCount];

        } catch (\Exception $e) {
            Log::warning("HikvisionSync: Event sync failed for device {$device->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync attendance events from all active ISUP devices
     */
    public function syncAllISUPDevicesEvents(): array
    {
        $devices = BranchDevice::where('connection_type', 'isup')
            ->where('status', 1)
            ->whereNotNull('device_id')
            ->get();

        $results = [];
        foreach ($devices as $device) {
            $results[$device->device_id] = $this->syncEventsFromDevice($device);
        }

        return $results;
    }
}
