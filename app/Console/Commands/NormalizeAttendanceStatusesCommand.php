<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeAttendanceStatusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:normalize-statuses {--dry-run : O\'zgarishlarni bazaga yozmasdan faqat hisobotni ko\'rsatish} {--force : O\'zgarishlarni to\'g\'ridan-to\'g\'ri bazaga yozish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'hikvision_access_events jadvalidagi nostandart statuslarni (CheckIn, keldi, breakOut va h.k.) checkIn/checkOut ga normallashtirish';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run') || !$this->option('force');

        if ($isDryRun) {
            $this->warn("REJIM: DRY-RUN (Bazaga o'zgartirish kiritilmaydi)");
        } else {
            $this->alert("DIQQAT: Haqiqiy yangilash rejimi! Status va label'lar normallashtiriladi.");
        }

        $records = DB::table('hikvision_access_events')
            ->select('id', 'attendanceStatus', 'label')
            ->whereNotNull('attendanceStatus')
            ->get();

        $stats = [];
        $updates = [];

        foreach ($records as $record) {
            $oldStatus = $record->attendanceStatus;
            $normalizedStatus = AttendanceStatus::normalize($oldStatus);

            if ($normalizedStatus === null) {
                continue;
            }

            $expectedLabel = AttendanceStatus::label($normalizedStatus);

            if ($oldStatus !== $normalizedStatus || $record->label !== $expectedLabel) {
                $key = "{$oldStatus} -> {$normalizedStatus}";
                if (!isset($stats[$key])) {
                    $stats[$key] = [
                        'old_status' => $oldStatus,
                        'new_status' => $normalizedStatus,
                        'new_label' => $expectedLabel,
                        'count' => 0,
                        'ids' => [],
                    ];
                }
                $stats[$key]['count']++;
                $stats[$key]['ids'][] = $record->id;
            }
        }

        if (empty($stats)) {
            $this->info("Normallashtirilishi kerak bo'lgan nostandart statuslar topilmadi. Barcha yozuvlar standart holatda.");
            return Command::SUCCESS;
        }

        $tableData = [];
        $totalCount = 0;
        foreach ($stats as $item) {
            $tableData[] = [$item['old_status'], $item['new_status'], $item['new_label'], $item['count']];
            $totalCount += $item['count'];
        }

        $this->table(['Eski status', 'Yangi status', 'Yangi label', 'Qatorlar soni'], $tableData);
        $this->info("Jami normallashtiriladigan yozuvlar soni: {$totalCount}");

        if ($isDryRun) {
            $this->info("DRY-RUN yakunlandi. Baza o'zgartirilmadi. Ushbu o'zgarishlarni qo'llash uchun --force parametridan foydalaning.");
            return Command::SUCCESS;
        }

        $this->info("Statuslarni yangilash boshlandi...");
        foreach ($stats as $item) {
            $chunks = array_chunk($item['ids'], 250);
            foreach ($chunks as $chunk) {
                DB::table('hikvision_access_events')
                    ->whereIn('id', $chunk)
                    ->update([
                        'attendanceStatus' => $item['new_status'],
                        'label' => $item['new_label'],
                    ]);
            }
        }

        $this->info("Muvaffaqiyatli yakunlandi! {$totalCount} ta yozuv normallashtirildi.");
        return Command::SUCCESS;
    }
}
