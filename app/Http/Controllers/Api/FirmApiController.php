<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirmApiController extends Controller
{
    public function firms(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Firm::with(['branches', 'user_firms.user']);

        if (!$user->hasRole('Admin')) {
            $query->whereHas('user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('name', 'like', "%{$search}%");
        }

        $firms = $query->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $firms,
        ]);
    }

    public function branches(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Branch::with(['firm', 'devices', 'days', 'holidays']);

        if (!$user->hasRole('Admin')) {
            $query->whereHas('firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('firm_id')) {
            $query->where('firm_id', $request->firm_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('name', 'like', "%{$search}%");
        }

        $branches = $query->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    public function devices(Request $request): JsonResponse
    {
        $query = BranchDevice::with(['branch.firm']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $devices = $query->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }
}
