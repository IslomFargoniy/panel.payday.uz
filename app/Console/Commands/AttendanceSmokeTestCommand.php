<?php

namespace App\Console\Commands;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\FirmHoliday;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerHoliday;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Attendance\WorkScheduleService;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class AttendanceSmokeTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:smoke-test {--date= : Tekshirish sanasi (YYYY-MM-DD), standart joriy sana}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploydan keyin asosiy davomat, hisobot va hisob-kitob so\'rovlarini faqat o\'qish rejimida tekshiradi';

    /**
     * Execute the console command.
     */
    public function handle(DashboardService $dashboardService, AttendanceReportService $reportService): int
    {
        $dateStr = $this->option('date') ?: date('Y-m-d');

        try {
            $parsedDate = Carbon::parse($dateStr);
        } catch (\Exception $e) {
            $this->error("Noto'g'ri sana formati kiritildi: {$dateStr}");
            return Command::FAILURE;
        }

        $monthStr = $parsedDate->format('Y-m');
        $this->info("🔍 Attendance Smoke Test boshlanmoqda... (Sana: {$dateStr}, Oy: {$monthStr})");
        $this->line("Faqat o'qish (read-only) rejimida joriy ma'lumotlar bazasida so'rovlar bajarilmoqda.\n");

        $results = [];
        $hasFailure = false;

        // 1. Dashboard statistikasi
        $results[] = $this->runCheck('dashboard', function () use ($dashboardService, $monthStr) {
            $request = new Request(['month' => $monthStr]);
            $data = $dashboardService->getDashboardData($request);
            $rowsCount = count($data['resultsForHisobot'] ?? []);
            return [
                'rows' => $rowsCount,
                'detail' => 'All workers: ' . ($data['stats']['all_worker'] ?? 0) . ', On-time: ' . ($data['stats']['on_time'] ?? 0)
            ];
        });

        // 2. Salary report (Maosh hisoboti)
        $results[] = $this->runCheck('salary_report', function () use ($reportService, $parsedDate) {
            $from = $parsedDate->copy()->startOfMonth()->toDateString();
            $to = $parsedDate->copy()->endOfMonth()->toDateString();
            $request = new Request([
                'from' => $from,
                'to' => $to,
                'per_page' => 10,
            ]);
            $data = $reportService->getSalaryReportData($request);
            $paginator = $data['attendance'] ?? null;
            $count = $paginator ? $paginator->count() : 0;
            $total = $paginator ? $paginator->total() : 0;
            $report = $data['report'] ?? null;
            $workedMinutes = $report->worked_minutes ?? 0;
            $workingDays = $report->working_days ?? 0;
            $lateMinutes = $report->late_minutes ?? 0;

            return [
                'rows' => $total,
                'detail' => "Sahifada: {$count}, Jami: {$total} | Ishlangan: {$workedMinutes} daq, Ish kunlari: {$workingDays}, Kechikish: {$lateMinutes} daq",
            ];
        });

        // 3. Monthly attendance (Oylik davomat)
        $results[] = $this->runCheck('monthly_attendance', function () use ($reportService, $monthStr) {
            $request = new Request(['month' => $monthStr, 'per_page' => 10]);
            $data = $reportService->getMonthlyAttendanceData($request);
            $paginator = $data['worker'] ?? null;
            $count = $paginator ? $paginator->count() : 0;
            $total = $paginator ? $paginator->total() : 0;
            return [
                'rows' => $total,
                'detail' => "Sahifada: {$count}, Jami: {$total} xodim",
            ];
        });

        // 4. Attendance grid
        $results[] = $this->runCheck('attendance_grid', function () use ($reportService, $dateStr, $monthStr) {
            $request = new Request([
                'month' => $monthStr,
                'from' => $dateStr,
                'to' => $dateStr,
                'per_page' => 10,
            ]);
            $data = $reportService->getAttendanceGridData($request);
            $paginator = $data['worker'] ?? null;
            $count = $paginator ? $paginator->count() : 0;
            $total = $paginator ? $paginator->total() : 0;
            $daysInMonth = $data['daysInMonth'] ?? 0;
            return [
                'rows' => $total,
                'detail' => "Sahifada: {$count}, Jami: {$total} xodim (Kunlar: {$daysInMonth})",
            ];
        });

        // 5. Daily attendance (har bir faol filial bo'yicha)
        $branches = Branch::where('status', 1)->limit(10)->get();
        if ($branches->isEmpty()) {
            $results[] = [
                'name' => 'daily_attendance',
                'status' => 'OK',
                'time' => '0.00ms',
                'rows' => 0,
                'detail' => 'Faol filiallar topilmadi (0 branch)'
            ];
        } else {
            foreach ($branches as $branch) {
                $checkName = "daily_attendance (Filial: {$branch->name})";
                $results[] = $this->runCheck($checkName, function () use ($reportService, $dateStr, $branch) {
                    $request = new Request(['date' => $dateStr, 'per_page' => 10]);
                    $data = $reportService->getDailyAttendanceData($request, $branch);
                    $paginator = $data['worker'] ?? null;
                    $count = $paginator ? $paginator->count() : 0;
                    $total = $paginator ? $paginator->total() : 0;
                    return [
                        'rows' => $total,
                        'detail' => "Filial ID {$branch->id} bo'yicha sahifada: {$count}, jami: {$total} xodim",
                    ];
                });
            }
        }

        // 6. Mobile portal myAttendance (bitta xodim uchun)
        $sampleWorker = Worker::where('status', 1)->first();
        if ($sampleWorker) {
            $results[] = $this->runCheck("mobile_myAttendance (Xodim #{$sampleWorker->id})", function () use ($sampleWorker, $monthStr) {
                $start = Carbon::parse($monthStr)->startOfMonth();
                $end = Carbon::parse($monthStr)->endOfMonth();

                $events = HikvisionAccessEvent::where('employeeNoString', $sampleWorker->employeeNoString)
                    ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'asc')
                    ->limit(50)
                    ->get();

                $scheduleService = new WorkScheduleService();
                $workingDayIndexes = $scheduleService->getWorkingDayIndexes($sampleWorker);

                return [
                    'rows' => $events->count(),
                    'detail' => "Oy bo'yicha hodisalar: " . $events->count() . ", Ish kunlari: " . count($workingDayIndexes)
                ];
            });
        } else {
            $results[] = [
                'name' => 'mobile_myAttendance',
                'status' => 'OK',
                'time' => '0.00ms',
                'rows' => 0,
                'detail' => 'Faol xodimlar topilmadi (0 workers)'
            ];
        }

        // Display results table
        $tableRows = [];
        $okCount = 0;
        $failCount = 0;

        foreach ($results as $res) {
            if ($res['status'] === 'OK') {
                $okCount++;
            } else {
                $failCount++;
                $hasFailure = true;
            }

            $tableRows[] = [
                $res['name'],
                $res['status'],
                $res['time'],
                $res['rows'],
                $res['detail'],
            ];
        }

        $this->table(['Tekshiruv (Query)', 'Holat', 'Vaqt', 'Qatorlar soni', 'Izoh'], $tableRows);
        $this->line("Jami: " . count($results) . " ta so'rov tekshirildi (OK: {$okCount}, FAIL: {$failCount}).");

        if ($hasFailure) {
            $this->error("\n❌ Smoke testda xatoliklar aniqlandi! Iltimos yuqoridagi jadvalni ko'rib chiqing.");
            return Command::FAILURE;
        }

        $this->info("\n🎉 Barcha o'qish so'rovlari muvaffaqiyatli yakunlandi! Tizim deployga tayyor.");
        return Command::SUCCESS;
    }

    /**
     * Run a single read-only check and capture duration, rows and errors.
     */
    protected function runCheck(string $name, callable $callback): array
    {
        $startTime = microtime(true);

        try {
            $res = $callback();
            $duration = round((microtime(true) - $startTime) * 1000, 2) . 'ms';

            return [
                'name' => $name,
                'status' => 'OK',
                'time' => $duration,
                'rows' => $res['rows'] ?? 0,
                'detail' => $res['detail'] ?? '',
            ];
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2) . 'ms';

            return [
                'name' => $name,
                'status' => 'FAIL',
                'time' => $duration,
                'rows' => 0,
                'detail' => 'Xatolik: ' . $e->getMessage(),
            ];
        }
    }
}
