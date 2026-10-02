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
    protected $signature = 'hikvision:backfill-event-time {--dry-run : O\'zgarishlarni bazaga yozmasdan faqat hisobotni ko\'rsatish} {--force : O\'zgarishlarni to\'g\'ridan-to\'g\'ri bazaga yozish}
                            {--from= : Qurilma vaqti (dateTime) shu sanadan (Y-m-d) boshlab}
                            {--to= : Qurilma vaqti (dateTime) shu sanagacha (Y-m-d, shu kun ham kiradi)}
                            {--device= : Faqat shu qurilma (shortSerialNumber yoki macAddress)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kechikib yetkazilgan eventlar (created_at - dateTime > 600s) uchun created_at ni dateTime ga qayta o\'rnatish';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run') || !$this->option('force');

        if ($isDryRun) {
            $this->warn("REJIM: DRY-RUN (Bazaga o'zgartirish kiritilmaydi)");
        } else {
            $this->alert("DIQQAT: Haqiqiy yangilash rejimi! created_at qiymatlari dateTime bilan almashtiriladi.");
        }

        $this->info("Kechikib kelgan eventlar tahlil qilinmoqda...");

        $records = DB::table('hikvision_access_events')
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
                'hikvision_access_events.created_at',
                'hikvision_accesses.dateTime',
                'hikvision_accesses.shortSerialNumber',
                'hikvision_accesses.macAddress',
            ])
            ->get();

        $eligible = [];
        $deviceCounts = [];

        foreach ($records as $row) {
            $createdTs = Carbon::parse($row->created_at)->timestamp;
            $deviceTs = Carbon::parse($row->dateTime)->timestamp;
            $delay = $createdTs - $deviceTs;

            // Only fix events where delay > 600s and dateTime <= created_at (NEVER touch negative drift where device clock is ahead)
            if ($delay > 600 && $deviceTs <= $createdTs) {
                $device = $row->shortSerialNumber ?: ($row->macAddress ?: 'Noma\'lum');

                if (!isset($deviceCounts[$device])) {
                    $deviceCounts[$device] = 0;
                }
                $deviceCounts[$device]++;

                $eligible[] = [
                    'id' => $row->id,
                    'dateTime' => $row->dateTime,
                ];
            }
        }

        if (empty($eligible)) {
            $this->info("Kechikib yetkazilgan (created_at - dateTime > 600s) birorta ham event topilmadi.");
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($deviceCounts as $device => $count) {
            $tableData[] = [$device, $count];
        }

        $this->table(['Qurilma / Identifikator', 'Tuzatilishi kerak bo\'lgan eventlar'], $tableData);
        $totalCount = count($eligible);
        $this->info("Jami aniqlangan yozuvlar soni: {$totalCount}");

        if ($isDryRun) {
            $this->info("DRY-RUN yakunlandi. Baza o'zgartirilmadi. Ushbu o'zgarishlarni qo'llash uchun --force parametridan foydalaning.");
            return Command::SUCCESS;
        }

        $this->info("Bazani yangilash boshlandi...");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $chunks = array_chunk($eligible, 250);
        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $item) {
                    DB::table('hikvision_access_events')
                        ->where('id', $item['id'])
                        ->update(['created_at' => $item['dateTime']]);
                }
            });
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info("Muvaffaqiyatli yakunlandi! {$totalCount} ta event vaqti dateTime ga moslashtirildi.");

        return Command::SUCCESS;
    }
}
