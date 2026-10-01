<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Branch\Branch;
use App\Models\Day;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WorkerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;

        $workers = Worker::query()->with(['branch.firm']);

        if ($request->search) {
            $workers->where(function ($query) use ($request) {
                $query->where('workers.name', 'like', "%{$request->search}%")
                    ->orWhere('workers.phone', 'like', "%{$request->search}%")
                    ->orWhere('workers.address', 'like', "%{$request->search}%")
                    ->orWhere('workers.comment', 'like', "%{$request->search}%");
            });
        }

        if ($request->firm_id) {
            $workers->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        if ($request->branch_id) {
            $workers->where('branch_id', $request->branch_id);
        }

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $workers->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $paginatedWorkers = $workers->paginate($per_page);

        // Batch calculate balances for only the paginated workers
        $workerIds = $paginatedWorkers->pluck('id')->toArray();
        if (!empty($workerIds)) {
            $salaries = \App\Models\Salary\Salary::whereIn('worker_id', $workerIds)
                ->groupBy('worker_id')
                ->select('worker_id', DB::raw('SUM(amount) as total'))
                ->pluck('total', 'worker_id');

            $payments = \App\Models\Salary\SalaryPayment::whereIn('worker_id', $workerIds)
                ->groupBy('worker_id')
                ->select('worker_id', DB::raw('SUM(amount) as total'))
                ->pluck('total', 'worker_id');

            foreach ($paginatedWorkers as $workerItem) {
                $workerItem->balance = (float)(($salaries[$workerItem->id] ?? 0) - ($payments[$workerItem->id] ?? 0));
            }
        }

        // Lean dropdowns
        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return Inertia::render('worker/index', [
            'worker' => $paginatedWorkers,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ]);
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
    public function store(StoreWorkerRequest $request)
    {
        try {
            $data = $request->validated();
            $data['hour_price'] = isset($data['hour_price']) && $data['hour_price'] !== '' ? $data['hour_price'] : 0;
            $data['fine_price'] = isset($data['fine_price']) && $data['fine_price'] !== '' ? $data['fine_price'] : 0;
            $data['phone'] = !empty($data['phone']) ? trim($data['phone']) : null;
            $data['address'] = !empty($data['address']) ? trim($data['address']) : null;
            $data['comment'] = !empty($data['comment']) ? trim($data['comment']) : null;
            if ($request->filled('password')) {
                $data['password'] = $request->input('password');
            } else {
                unset($data['password']);
            }

            if ($request->hasFile('avatar')) {
                $data['avatar'] = $this->saveOptimizedAvatar($request->file('avatar'));
            }
            Worker::create($data);

            return back()->with('success', 'Worker created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Request $request, Worker $worker)
    {

        // Non-admin users must be part of the firm
        if (!Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $worker->branch->firm_id)
                ->firstOrFail(); // Throws if unauthorized
        }

        $worker->load([
            'salaries',
            'worker_days',
            'worker_holidays',
            'salary_payments',
        ]);

        if ($request->per_page) {
            $per_page = $request->per_page;
        } else {
            $per_page = 15;
        }

        $hikvision_access_events = HikvisionAccessEvent::with([
            'hikvisionAccess'
        ])
            ->where('employeeNoString', '=', $worker->employeeNoString)
            ->orderBy('id', 'desc');

        $from = $request->from ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $request->to ?: Carbon::now()->toDateString();

        $hikvision_access_events->whereBetween('created_at', [$from, $to . " 23:59:59"]);

        if ($request->search) {
            $hikvision_access_events->whereLike('label', "%$request->search%");
        }

        $hikvision_access_events = $hikvision_access_events->paginate($per_page);

        $days = Day::all();

        return Inertia::render('worker/show', [
            'worker' => $worker,
            'hikvision_access_events' => $hikvision_access_events,
            'days' => $days
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show_history(Request $request, Worker $worker)
    {

        // Non-admin users must be part of the firm
        if (!Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $worker->branch->firm_id)
                ->firstOrFail(); // Throws if unauthorized
        }


        // Subquery 1: Salaries with user join
        $salaries = DB::table('salaries as s')
            ->join('users as u', 's.user_id', '=', 'u.id')
            ->where('s.worker_id', $worker->id)
            ->select([
                's.id',
                's.amount',
                's.comment',
                DB::raw("CONCAT(u.name, ' ', COALESCE(u.phone, '')) as user_name"),
                's.created_at',
                's.worker_id',
                DB::raw('s.id as salary_id'),
                DB::raw('NULL as salary_payment_id'),
            ]);

        // Subquery 2: Salary Payments with user join
        $payments = DB::table('salary_payments as sp')
            ->join('users as u', 'sp.user_id', '=', 'u.id')
            ->where('sp.worker_id', $worker->id)
            ->select([
                'sp.id',
                DB::raw('-1 * sp.amount as amount'),
                'sp.comment',
                DB::raw("CONCAT(u.name, ' ', COALESCE(u.phone, '')) as user_name"),
                'sp.created_at',
                'sp.worker_id',
                DB::raw('NULL as salary_id'),
                DB::raw('sp.id as salary_payment_id'),
            ]);

        // Combine both with UNION ALL
        $history = $salaries->unionAll($payments);

        // Wrap union query to calculate running balance
        $historyWithBalance = DB::table(DB::raw("({$history->toSql()}) as history"))
            ->mergeBindings($history)
            ->selectRaw('
            history.*,
            ROUND(SUM(history.amount) OVER (PARTITION BY history.worker_id ORDER BY history.created_at), 2) as balance
        ')
            ->orderByDesc('history.created_at')
            ->get();

        // Return as part of Inertia response
        return Inertia::render('worker/show_history', [
            'worker' => $worker,
            'history' => $historyWithBalance,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Worker $worker)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkerRequest $request, Worker $worker)
    {
        try {
            $data = $request->validated();
            $data['hour_price'] = isset($data['hour_price']) && $data['hour_price'] !== '' ? $data['hour_price'] : 0;
            $data['fine_price'] = isset($data['fine_price']) && $data['fine_price'] !== '' ? $data['fine_price'] : 0;
            $data['phone'] = !empty($data['phone']) ? trim($data['phone']) : null;
            $data['address'] = !empty($data['address']) ? trim($data['address']) : null;
            $data['comment'] = !empty($data['comment']) ? trim($data['comment']) : null;
            if ($request->filled('password')) {
                $data['password'] = $request->input('password');
            } else {
                unset($data['password']);
            }

            if ($request->hasFile('avatar')) {
                if ($worker->avatar && is_file(public_path('storage/' . $worker->avatar))) {
                    @unlink(public_path('storage/' . $worker->avatar));
                }
                $data['avatar'] = $this->saveOptimizedAvatar($request->file('avatar'));
            } else {
                unset($data['avatar']);
            }
            $worker->update($data);
            return back()->with('success', 'Worker updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Resize and optimize avatar image to safe dimensions and size for Hikvision terminals
     */
    protected function saveOptimizedAvatar($file): string
    {
        return (new \App\Services\Worker\AvatarService())->saveOptimizedAvatar($file);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Worker $worker)
    {
        $worker->delete();

        return back()->with('success', 'Worker deleted successfully.');
    }

}
