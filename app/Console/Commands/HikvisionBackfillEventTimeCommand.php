<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HikvisionBackfillEventTimeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:backfill-event-time
                            {--dry-run : O\'zgarishlarni bazaga yozmasdan faqat hisobotni ko\'rsatish}
                            {--force : O\'zgarishlarni to\'g\'ridan-to\'g\'ri bazaga yozish}
                            {--from= : Qurilma vaqti (dateTime) shu sanadan (Y-m-d) boshlab}
                            {--to= : Qurilma vaqti (dateTime) shu sanagacha (Y-m-d, shu kun ham kiradi)}
                            {--device= : Faqat shu qurilma (shortSerialNumber yoki macAddress)}
                            {--threshold= : Kechikish chegarasi (soniya). Standart: hikvision.late_delivery_seconds yoki 600}
                            {--only-day-mismatch : Faqat qurilma kuni va yozilgan kun farq qiladigan eventlar}
                            {--include-paid : Maosh berilgan davrga tushadigan eventlarni ham tuzatish}
                            {--rollback= : Oldingi ishga tushirishda yaratilgan CSV fayl bo\'yicha created_at ni qaytarish (--force bilan qo\'llanadi)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kechikib yetkazilgan eventlar (created_at - dateTime > chegara) uchun created_at ni dateTime ga qayta o\'rnatish (maosh berilgan davrlar standart bo\'yicha himoyalangan)';

    /** @var array<int, array<int, object>> worker_id => salaries */
    protected array $salaryCache = [];

    /** @var array<string, int> device identifikatori => branch_id */
    protected array $deviceBranch = [];

    /** @var array<string, array<int>> branch|emp => worker_id lar */
    protected array $workerCache = [];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run') || !$this->option('force');

        if ($this->option('rollback')) {
            return $this->rollback((string) $this->option('rollback'), $isDryRun);
        }

        $threshold = (int) ($this->option('threshold') ?: config('hikvision.late_delivery_seconds', 600));
        if ($threshold <= 0) {
            $threshold = 600;
        }
        $includePaid = (bool) $this->option('include-paid');
        $onlyDayMismatch = (bool) $this->option('only-day-mismatch');

        if ($isDryRun) {
            $this->warn("REJIM: DRY-RUN (Bazaga o'zgartirish kiritilmaydi)");
        } else {
            $this->alert("DIQQAT: Haqiqiy yangilash rejimi! created_at qiymatlari dateTime bilan almashtiriladi.");
        }

        $this->loadDeviceMap();

        $this->info("Kechikib kelgan eventlar tahlil qilinmoqda (chegara: {$threshold}s)...");

        $query = DB::table('hikvision_access_events')
            ->join('hikvision_accesses', 'hikvision_access_events.hikvision_access_id', '=', 'hikvision_accesses.id')
            ->whereNull('hikvision_access_events.deleted_at')
            ->when($this->option('from'), fn ($q, $from) => $q->where('hikvision_accesses.dateTime', '>=', Carbon::parse($from)->startOfDay()->toDateTimeString()))
            ->when($this->option('to'), fn ($q, $to) => $q->where('hikvision_accesses.dateTime', '<=', Carbon::parse($to)->endOfDay()->toDateTimeString()))
            ->when($this->option('device'), fn ($q, $device) => $q->where(function ($q) use ($device) {
                $q->where('hikvision_accesses.shortSerialNumber', $device)
                    ->orWhere('hikvision_accesses.macAddress', $device);
            }))
            ->select([
                'hikvision_access_events.id',
                'hikvision_access_events.employeeNoString',
                'hikvision_access_events.created_at',
                'hikvision_accesses.dateTime',
                'hikvision_accesses.shortSerialNumber',
                'hikvision_accesses.macAddress',
            ]);

        $eligible = [];
        $skippedPaid = [];
        $deviceCounts = [];
        $dayChangeCount = 0;

        $query->chunkById(1000, function ($rows) use (&$eligible, &$skippedPaid, &$deviceCounts, &$dayChangeCount, $threshold, $onlyDayMismatch, $includePaid) {
            foreach ($rows as $row) {
                $createdTs = Carbon::parse($row->created_at)->timestamp;
                $deviceTs = Carbon::parse($row->dateTime)->timestamp;
                $delay = $createdTs - $deviceTs;

                // Faqat kechikkan eventlar (qurilma soati oldinda bo'lgan eventga HECH QACHON tegilmaydi)
                if ($delay <= $threshold || $deviceTs > $createdTs) {
                    continue;
                }

                $dayMismatch = Carbon::parse($row->dateTime)->toDateString() !== Carbon::parse($row->created_at)->toDateString();
                if ($onlyDayMismatch && !$dayMismatch) {
                    continue;
                }

                $paid = $this->findPaidSalary($row);
                if ($paid && !$includePaid) {
                    $skippedPaid[] = [
                        $paid['salary_id'],
                        $paid['worker'],
                        $paid['period'],
                        $row->id,
                        $row->created_at . ' → ' . $row->dateTime,
                    ];
                    continue;
                }

                $device = $row->shortSerialNumber ?: ($row->macAddress ?: 'Noma\'lum');
                $deviceCounts[$device] = ($deviceCounts[$device] ?? 0) + 1;
                if ($dayMismatch) {
                    $dayChangeCount++;
                }

                $eligible[] = [
                    'id' => $row->id,
                    'old' => $row->created_at,
                    'new' => $row->dateTime,
                ];
            }
        }, 'hikvision_access_events.id', 'id');

        if (!empty($skippedPaid)) {
            $this->warn(count($skippedPaid) . " ta event maosh berilgan davrga tushgani uchun O'TKAZIB YUBORILDI (tuzatish uchun --include-paid):");
            $this->table(['Salary ID', 'Xodim', 'Maosh davri', 'Event ID', 'created_at → dateTime'], $this->output->isVerbose() ? $skippedPaid : array_slice($skippedPaid, 0, 20));
            if (!$this->output->isVerbose() && count($skippedPaid) > 20) {
                $this->line('... qolganlarini ko\'rish uchun -v bilan ishga tushiring.');
            }
        }

        if (empty($eligible)) {
            $this->info("Tuzatilishi kerak bo'lgan kechikkan event topilmadi.");
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($deviceCounts as $device => $count) {
            $tableData[] = [$device, $count];
        }

        $this->table(['Qurilma / Identifikator', 'Tuzatilishi kerak bo\'lgan eventlar'], $tableData);
        $totalCount = count($eligible);
        $this->info("Jami aniqlangan yozuvlar soni: {$totalCount} (shulardan kuni o'zgaradigan: {$dayChangeCount})");

        if ($this->output->isVerbose()) {
            $this->table(['Event ID', 'Eski created_at', 'Yangi (dateTime)'], array_map(fn ($e) => [$e['id'], $e['old'], $e['new']], $eligible));
        }

        if ($isDryRun) {
            $this->info("DRY-RUN yakunlandi. Baza o'zgartirilmadi. Ushbu o'zgarishlarni qo'llash uchun --force parametridan foydalaning.");
            return Command::SUCCESS;
        }

        $csvPath = $this->writeBackup($eligible);
        $this->info("Zaxira (rollback uchun) CSV: {$csvPath}");

        $this->info("Bazani yangilash boshlandi...");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        foreach (array_chunk($eligible, 250) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $item) {
                    DB::table('hikvision_access_events')
                        ->where('id', $item['id'])
                        ->update(['created_at' => $item['new']]);
                }
            });
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info("Muvaffaqiyatli yakunlandi! {$totalCount} ta event vaqti dateTime ga moslashtirildi.");
        $this->line("Orqaga qaytarish: php artisan hikvision:backfill-event-time --rollback={$csvPath} --force");

        return Command::SUCCESS;
    }

    protected function loadDeviceMap(): void
    {
        foreach (DB::table('branch_devices')->get(['branch_id', 'device_id', 'mac_address']) as $d) {
            if ($d->device_id) {
                $this->deviceBranch[strtolower($d->device_id)] = (int) $d->branch_id;
            }
            if ($d->mac_address) {
                $this->deviceBranch[strtolower(str_replace('-', ':', $d->mac_address))] = (int) $d->branch_id;
            }
        }
    }

    /**
     * Event xodimining maosh davri (eski created_at yoki yangi dateTime sanasi) ichiga tushsa, shu maosh haqida ma'lumot qaytaradi.
     */
    protected function findPaidSalary(object $row): ?array
    {
        if (empty($row->employeeNoString)) {
            return null;
        }

        $branchId = null;
        foreach ([$row->shortSerialNumber, $row->macAddress] as $ident) {
            if ($ident && isset($this->deviceBranch[strtolower(str_replace('-', ':', $ident))])) {
                $branchId = $this->deviceBranch[strtolower(str_replace('-', ':', $ident))];
                break;
            }
        }

        $key = ($branchId ?? '*') . '|' . $row->employeeNoString;
        if (!isset($this->workerCache[$key])) {
            $this->workerCache[$key] = DB::table('workers')
                ->where('employeeNoString', $row->employeeNoString)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->pluck('id')
                ->all();
        }

        $dates = [
            Carbon::parse($row->created_at)->toDateString(),
            Carbon::parse($row->dateTime)->toDateString(),
        ];

        foreach ($this->workerCache[$key] as $workerId) {
            if (!isset($this->salaryCache[$workerId])) {
                $this->salaryCache[$workerId] = DB::table('salaries')
                    ->where('worker_id', $workerId)
                    ->get(['id', 'from', 'to'])
                    ->all();
            }

            foreach ($this->salaryCache[$workerId] as $salary) {
                $from = Carbon::parse($salary->from)->toDateString();
                $to = Carbon::parse($salary->to)->toDateString();
                foreach ($dates as $date) {
                    if ($date >= $from && $date <= $to) {
                        return [
                            'salary_id' => $salary->id,
                            'worker' => (string) (DB::table('workers')->where('id', $workerId)->value('name') ?? $workerId),
                            'period' => "{$from} — {$to}",
                        ];
                    }
                }
            }
        }

        return null;
    }

    protected function writeBackup(array $eligible): string
    {
        $dir = storage_path('app/backfill');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/backfill-' . now()->format('Ymd-His') . '.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, ['id', 'old_created_at', 'new_created_at']);
        foreach ($eligible as $item) {
            fputcsv($fh, [$item['id'], $item['old'], $item['new']]);
        }
        fclose($fh);

        return $path;
    }

    protected function rollback(string $csvPath, bool $isDryRun): int
    {
        if (!is_file($csvPath)) {
            $this->error("CSV fayl topilmadi: {$csvPath}");
            return Command::FAILURE;
        }

        $fh = fopen($csvPath, 'r');
        $header = fgetcsv($fh);
        if ($header !== ['id', 'old_created_at', 'new_created_at']) {
            fclose($fh);
            $this->error("CSV format noto'g'ri (kutilgan sarlavha: id,old_created_at,new_created_at)");
            return Command::FAILURE;
        }

        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) === 3 && ctype_digit($r[0])) {
                $rows[] = ['id' => (int) $r[0], 'old' => $r[1]];
            }
        }
        fclose($fh);

        $this->info('CSV da ' . count($rows) . ' ta yozuv bor.');

        if ($isDryRun) {
            $this->warn("DRY-RUN: baza o'zgartirilmadi. Qaytarish uchun --force qo'shing.");
            return Command::SUCCESS;
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $item) {
                    DB::table('hikvision_access_events')->where('id', $item['id'])->update(['created_at' => $item['old']]);
                }
            });
        }

        $this->info('Rollback yakunlandi: ' . count($rows) . ' ta event created_at qiymati qaytarildi.');

        return Command::SUCCESS;
    }
}
