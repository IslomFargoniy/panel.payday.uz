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
        if ($request->per_page) {
            $per_page = $request->per_page;
        } else {
            $per_page = 10;
        }

        $workers = Worker::with([])
            ->select(
                'workers.*',
                DB::raw("getBalance(workers.id) as balance")
            );

        if ($request->search) {
            $workers->where(function ($query) use ($request) {
                $query->where('workers.name', 'like', "%{$request->search}%")
                    ->orWhere('workers.phone', 'like', "%{$request->search}%")
                    ->orWhere('workers.address', 'like', "%{$request->search}%")
                    ->orWhere('workers.comment', 'like', "%{$request->search}%");
            });
        }

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

        return Inertia::render('worker/index', [
            'worker' => $workers,
            'firms' => $firms,
            'branches' => $branches,
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
            $per_page = 10;
        }

        $hikvision_access_events = HikvisionAccessEvent::with([
            'hikvisionAccess'
        ])
            ->where('employeeNoString', '=', $worker->employeeNoString)
            ->orderBy('id', 'desc');

        if ($request->from && $request->to) {
            $hikvision_access_events->whereBetween('created_at', [$request->from, $request->to . " 23:59:59"]);
        }

        if ($request->search) {
            $hikvision_access_events->whereLike('label', "%$request->search%");
        }

        $hikvision_access_events = $hikvision_access_events->paginate($per_page);

        $days = Day::query()
            ->whereNotIn('id', $worker->worker_days()->pluck('day_id'))
            ->get();

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
        $filename = 'avatars/' . uniqid('avatar_', true) . '.jpg';
        $destinationPath = storage_path('app/public/' . $filename);

        if (!file_exists(storage_path('app/public/avatars'))) {
            @mkdir(storage_path('app/public/avatars'), 0775, true);
        }

        if (extension_loaded('gd')) {
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo) {
                $mime = $imageInfo['mime'];
                $src = null;
                if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                    $src = @imagecreatefromjpeg($file->getRealPath());
                    // Auto-rotate according to EXIF orientation metadata from smartphones
                    if ($src && function_exists('exif_read_data')) {
                        $exif = @exif_read_data($file->getRealPath());
                        if (!empty($exif['Orientation'])) {
                            switch ($exif['Orientation']) {
                                case 8:
                                    $src = imagerotate($src, 90, 0);
                                    break;
                                case 3:
                                    $src = imagerotate($src, 180, 0);
                                    break;
                                case 6:
                                    $src = imagerotate($src, -90, 0);
                                    break;
                            }
                        }
                    }
                } elseif ($mime === 'image/png') {
                    $src = @imagecreatefrompng($file->getRealPath());
                } elseif ($mime === 'image/webp') {
                    $src = @imagecreatefromwebp($file->getRealPath());
                }

                if ($src) {
                    $width = imagesx($src);
                    $height = imagesy($src);

                    // Max dimensions 800px for optimal face recognition and small payload size
                    $maxDim = 800;
                    if ($width > $maxDim || $height > $maxDim) {
                        if ($width > $height) {
                            $newWidth = $maxDim;
                            $newHeight = (int)($height * ($maxDim / $width));
                        } else {
                            $newHeight = $maxDim;
                            $newWidth = (int)($width * ($maxDim / $height));
                        }
                    } else {
                        $newWidth = $width;
                        $newHeight = $height;
                    }

                    $dst = imagecreatetruecolor($newWidth, $newHeight);
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $white);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                    imagejpeg($dst, $destinationPath, 85);
                    imagedestroy($src);
                    imagedestroy($dst);

                    return $filename;
                }
            }
        }

        return $file->store('avatars', 'public');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Worker $worker)
    {
        if ($worker->avatar && is_file(public_path('storage/' . $worker->avatar))) {
            unlink(public_path('storage/' . $worker->avatar));
        }

        $worker->delete();

        return back()->with('success', 'Worker deleted successfully.');
    }

}
