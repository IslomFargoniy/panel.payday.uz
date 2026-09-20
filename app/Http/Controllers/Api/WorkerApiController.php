<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WorkerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin');

        $query = Worker::with(['branch.firm', 'days', 'holidays']);

        if (!$isAdmin) {
            $query->whereHas('branch.firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('firm_id') && $request->firm_id > 0) {
            $query->whereHas('branch', function ($q) use ($request) {
                $q->where('firm_id', $request->firm_id);
            });
        }

        if ($request->filled('branch_id') && $request->branch_id > 0) {
            $query->where('branch_id', $request->branch_id);
        }

        $perPage = $request->input('per_page', 15);
        $workers = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $workers,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $worker = Worker::with([
            'branch.firm',
            'days',
            'holidays',
            'hikvision_access_events' => function ($q) {
                $q->latest()->limit(50);
            },
            'salaries' => function ($q) {
                $q->latest()->limit(12);
            },
            'salary_payments' => function ($q) {
                $q->latest()->limit(12);
            }
        ])->find($id);

        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Xodim topilmadi.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $worker,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'phone' => 'nullable|string|max:20|unique:workers,phone',
            'work_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'hour_price' => 'nullable|numeric|min:0',
            'fine_price' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|string',
            'comment' => 'nullable|string',
            'status' => 'nullable|integer',
        ]);

        $validated['hour_price'] = $validated['hour_price'] ?? 0;
        $validated['fine_price'] = $validated['fine_price'] ?? 0;

        $worker = Worker::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Xodim muvaffaqiyatli qo‘shildi.',
            'data' => $worker,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $worker = Worker::find($id);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'phone' => 'nullable|string|max:20|unique:workers,phone,' . $id,
            'work_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'hour_price' => 'nullable|numeric|min:0',
            'fine_price' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|string',
            'comment' => 'nullable|string',
            'status' => 'nullable|integer',
        ]);

        $validated['hour_price'] = $validated['hour_price'] ?? 0;
        $validated['fine_price'] = $validated['fine_price'] ?? 0;

        $worker->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Xodim ma’lumotlari muvaffaqiyatli yangilandi.',
            'data' => $worker,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $worker = Worker::find($id);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        $worker->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xodim muvaffaqiyatli o‘chirildi.',
        ]);
    }

    public function uploadAvatar(Request $request, int $id): JsonResponse
    {
        $worker = Worker::find($id);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        $request->validate([
            'avatar' => 'required|image|max:10240', // max 10MB
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $worker->update(['avatar' => '/storage/' . $path]);

            return response()->json([
                'success' => true,
                'message' => 'Rasm muvaffaqiyatli yuklandi.',
                'data' => [
                    'avatar_url' => $worker->avatar,
                ]
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Fayl yuklanmadi.'], 400);
    }
}
