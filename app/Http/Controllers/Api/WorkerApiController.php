<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Worker\Worker;
use App\Services\Worker\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkerApiController extends Controller
{
    protected AvatarService $avatarService;

    public function __construct(AvatarService $avatarService)
    {
        $this->avatarService = $avatarService;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin');

        $query = Worker::with(['branch.firm', 'worker_days', 'worker_holidays']);

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

        $perPage = (int) $request->input('per_page', 15);
        $workers = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $workers,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();

        $worker = Worker::with([
            'branch.firm',
            'worker_days',
            'worker_holidays',
            'HikvisionAccessEvents' => function ($q) {
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

        if (!$user->hasRole('Admin') && !$user->hasWorkerAccess($worker)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat berilmagan.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $worker,
        ]);
    }

    public function store(StoreWorkerRequest $request): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();

        $validated = $request->validated();

        if (!$user->hasRole('Admin') && !$user->hasBranchAccess($validated['branch_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat berilmagan.',
            ], 403);
        }

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $this->avatarService->saveOptimizedAvatar($request->file('avatar'));
        }

        $worker = Worker::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Xodim muvaffaqiyatli qo‘shildi.',
            'data' => $worker,
        ], 201);
    }

    public function update(UpdateWorkerRequest $request, int $id): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();

        $worker = Worker::with('branch')->find($id);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        if (!$user->hasRole('Admin') && !$user->hasWorkerAccess($worker)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat berilmagan.',
            ], 403);
        }

        $validated = $request->validated();

        if (isset($validated['branch_id']) && !$user->hasRole('Admin') && !$user->hasBranchAccess($validated['branch_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat berilmagan.',
            ], 403);
        }

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $this->avatarService->saveOptimizedAvatar($request->file('avatar'));
        }

        $worker->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Xodim ma’lumotlari muvaffaqiyatli yangilandi.',
            'data' => $worker,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();

        $worker = Worker::with('branch')->find($id);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        if (!$user->hasRole('Admin') && !$user->hasWorkerAccess($worker)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat berilmagan.',
            ], 403);
        }

        $worker->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xodim muvaffaqiyatli o‘chirildi.',
        ]);
    }

    public function uploadAvatar(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();

        $worker = Worker::with('branch')->find($id);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        if (!$user->hasRole('Admin') && !$user->hasWorkerAccess($worker)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruxsat berilmagan.',
            ], 403);
        }

        $request->validate([
            'avatar' => 'required|image|max:10240',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $this->avatarService->saveOptimizedAvatar($request->file('avatar'));
            $worker->update(['avatar' => $path]);

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
