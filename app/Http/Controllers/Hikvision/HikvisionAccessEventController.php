<?php

namespace App\Http\Controllers\Hikvision;

use App\Http\Controllers\Controller;
use App\Models\Hikvision\HikvisionAccessEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HikvisionAccessEventController extends Controller
{
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(HikvisionAccessEvent $hikvisionAccessEvent)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HikvisionAccessEvent $hikvisionAccessEvent)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HikvisionAccessEvent $hikvisionAccessEvent)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HikvisionAccessEvent $hikvisionAccessEvent)
    {
        $user = auth()->user();
        if ($user && !$user->hasRole('Admin')) {
            $userFirmIds = $user->user_firms()->pluck('firm_id')->toArray();
            $eventFirmId = $hikvisionAccessEvent->worker?->branch?->firm_id;
            if (!$eventFirmId || !in_array($eventFirmId, $userFirmIds)) {
                abort(403, 'Unauthorized access to this event.');
            }
        }

        try {
            DB::beginTransaction();

            if ($hikvisionAccessEvent) {
                // Delete Picture safely via Storage disk to prevent path traversal
                $picture = $hikvisionAccessEvent->picture;
                if ($picture) {
                    $shortSerial = $hikvisionAccessEvent->hikvisionAccess->shortSerialNumber ?? 'UNKNOWN';
                    $basePicture = basename($picture);
                    $cleanSerial = preg_replace('/[^a-zA-Z0-9_\-]/', '', $shortSerial);
                    $relativePath = "hikvision/{$cleanSerial}/{$basePicture}";

                    $disk = \Illuminate\Support\Facades\Storage::disk('public');
                    if ($disk->exists($relativePath)) {
                        $fullPath = $disk->path($relativePath);
                        $realPath = realpath($fullPath);
                        $baseDir = realpath($disk->path('hikvision'));
                        if ($realPath && $baseDir && str_starts_with($realPath, $baseDir)) {
                            $disk->delete($relativePath);
                        }
                    }
                }

                $hikvisionAccessEvent->faceReact()->delete();
                $hikvisionAccessEvent->delete();

                // Only delete the parent HikvisionAccess if it's NOT the shared Telegram dummy record
                $shortSerial = $hikvisionAccessEvent->hikvisionAccess->shortSerialNumber ?? '';
                if ($shortSerial !== 'TELEGRAM') {
                    $hikvisionAccessEvent->hikvisionAccess()->delete();
                }
            }

            DB::commit();

            return back()->with('success', 'HikvisionAccessEvent deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack(); // 💡 Make sure to rollback on error

            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }
}
