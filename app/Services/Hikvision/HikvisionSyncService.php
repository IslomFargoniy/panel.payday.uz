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
     * Sync all workers of a branch to its devices
     */
    public function syncAllBranchWorkers(Branch $branch): array
    {
        $branch->load(['workers' => function ($q) {
            $q->where('status', 1);
        }, 'branch_devices']);

        $results = [];
        foreach ($branch->workers as $worker) {
            $results[$worker->id] = $this->syncWorker($worker);
        }

        return $results;
    }
}
