<?php

namespace App\Console\Commands;

use App\Models\Branch\BranchDevice;
use Illuminate\Console\Command;

class FillDefaultDeviceKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:fill-default-keys {--dry-run : Faqat kaliti bo\'sh ISUP qurilmalarni ko\'rsatish} {--force : Kaliti bo\'sh ISUP qurilmalarga default kalitni yozish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kaliti bo\'sh bo\'lgan ISUP qurilmalarni aniqlash va default kalit bilan to\'ldirish';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $devices = BranchDevice::where('connection_type', 'isup')
            ->where(function ($q) {
                $q->whereNull('encryption_key')->orWhere('encryption_key', '');
            })
            ->get();

        if ($devices->isEmpty()) {
            $this->info('Kaliti bo\'sh ISUP qurilmalar topilmadi. Barcha ISUP qurilmalarda kalit mavjud.');
            return 0;
        }

        $this->warn(sprintf('%d ta kaliti bo\'sh ISUP qurilma topildi:', $devices->count()));

        $rows = $devices->map(function ($device) {
            return [
                'ID' => $device->id,
                'Filial ID' => $device->branch_id,
                'Nomi' => $device->name,
                'Device ID' => $device->device_id,
                'MAC' => $device->mac_address,
                'Holati' => $device->status ? 'Faol' : 'Nofaol',
            ];
        });

        $this->table(['ID', 'Filial ID', 'Nomi', 'Device ID', 'MAC', 'Holati'], $rows);

        $isDryRun = $this->option('dry-run') || !$this->option('force');

        if ($isDryRun) {
            $this->info('[DRY-RUN] Hech qanday o\'zgarish qilinmadi. Kalitlarni yozish uchun --force bayrog\'ini ishlating.');
            return 0;
        }

        $defaultKey = 'PayDay142026';
        foreach ($devices as $device) {
            $device->encryption_key = $defaultKey;
            $device->save();
        }

        $this->info(sprintf('%d ta qurilmaga default kalit (%s) muvaffaqiyatli yozildi.', $devices->count(), $defaultKey));

        return 0;
    }
}
