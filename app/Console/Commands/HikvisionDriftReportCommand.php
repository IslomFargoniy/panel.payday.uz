<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HikvisionDriftReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:drift-report {--days=30 : So\'nggi necha kunlik eventlarni tahlil qilish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Qurilmalar soatlari drifti va kechikish taqsimotini hisobot ko\'rinishida chiqarish';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 30;
        }

        $fromDate = Carbon::now('Asia/Tashkent')->subDays($days)->toDateTimeString();

        $this->info("Hikvision qurilmalari soat drifti hisoboti (so'nggi {$days} kun):");

        $rows = DB::table('hikvision_access_events')
            ->join('hikvision_accesses', 'hikvision_access_events.hikvision_access_id', '=', 'hikvision_accesses.id')
            ->where('hikvision_access_events.created_at', '>=', $fromDate)
            ->whereNull('hikvision_access_events.deleted_at')
            ->select([
                'hikvision_accesses.shortSerialNumber',
                'hikvision_accesses.macAddress',
                'hikvision_accesses.dateTime',
                'hikvision_access_events.created_at',
            ])
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("Ko'rsatilgan davrda birorta ham event topilmadi.");
            return Command::SUCCESS;
        }

        $summary = [];

        foreach ($rows as $row) {
            $device = $row->shortSerialNumber ?: ($row->macAddress ?: 'Noma\'lum');

            if (!isset($summary[$device])) {
                $summary[$device] = [
                    'device' => $device,
                    'total' => 0,
                    'ahead' => 0,       // Qurilma oldinda: dateTime > created_at + 60s
                    'normal' => 0,      // Normal: -60s <= delay <= 60s
                    'late_1_10' => 0,   // 1-10 daqiqa kech: 60s < delay <= 600s
                    'late_10_plus' => 0,// 10+ daqiqa kech: delay > 600s
                ];
            }

            $summary[$device]['total']++;

            $createdTs = Carbon::parse($row->created_at)->timestamp;
            $deviceTs = Carbon::parse($row->dateTime)->timestamp;
            $delay = $createdTs - $deviceTs;

            if ($delay < -60) {
                // Device time is more than 60s in the future
                $summary[$device]['ahead']++;
            } elseif ($delay <= 60) {
                $summary[$device]['normal']++;
            } elseif ($delay <= 600) {
                $summary[$device]['late_1_10']++;
            } else {
                $summary[$device]['late_10_plus']++;
            }
        }

        $tableData = [];
        foreach ($summary as $item) {
            $tableData[] = [
                $item['device'],
                $item['total'],
                $item['ahead'] > 0 ? "<fg=red>{$item['ahead']}</>" : $item['ahead'],
                $item['normal'],
                $item['late_1_10'],
                $item['late_10_plus'] > 0 ? "<fg=yellow>{$item['late_10_plus']}</>" : $item['late_10_plus'],
            ];
        }

        $this->table(
            ['Qurilma / Identifikator', 'Jami eventlar', 'Qurilma oldinda (>60s)', 'Normal (±60s)', '1–10 daq kech', '10+ daq kech'],
            $tableData
        );

        return Command::SUCCESS;
    }
}
