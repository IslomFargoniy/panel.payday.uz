<?php

namespace App\Console\Commands;

use App\Models\Worker\Worker;
use Illuminate\Console\Command;

class WorkerCleanAvatarPathsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:clean-avatar-paths {--dry-run : Faqat noto\'g\'ri yo\'lli avatarlarni ko\'rsatish} {--force : Avatarlar yo\'llarini tozalash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worker avatarlaridagi /storage/ prefikslarini tozalash va yagona formatga keltirish';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $workers = Worker::whereNotNull('avatar')
            ->where(function ($q) {
                $q->where('avatar', 'like', '/%')
                  ->orWhere('avatar', 'like', 'storage/%');
            })
            ->get();

        if ($workers->isEmpty()) {
            $this->info('Noto\'g\'ri yo\'lli avatarlar topilmadi. Barcha avatarlar toza formatda.');
            return 0;
        }

        $this->warn(sprintf('%d ta noto\'g\'ri formatdagi avatar topildi:', $workers->count()));

        $rows = $workers->map(function ($w) {
            $cleaned = ltrim(str_replace('/storage/', '', $w->avatar), '/');
            if (str_starts_with($cleaned, 'storage/')) {
                $cleaned = substr($cleaned, 8);
            }
            return [
                'ID' => $w->id,
                'FIO' => $w->name,
                'Eski yo\'l' => $w->avatar,
                'Yangi yo\'l' => $cleaned,
            ];
        });

        $this->table(['ID', 'FIO', 'Eski yo\'l', 'Yangi yo\'l'], $rows);

        $isDryRun = $this->option('dry-run') || !$this->option('force');

        if ($isDryRun) {
            $this->info('[DRY-RUN] O\'zgartirish amalga oshirilmadi. Tozalashni bajarish uchun --force dan foydalaning.');
            return 0;
        }

        $count = 0;
        foreach ($workers as $worker) {
            $raw = $worker->getRawOriginal('avatar');
            $cleaned = ltrim(str_replace('/storage/', '', $raw), '/');
            if (str_starts_with($cleaned, 'storage/')) {
                $cleaned = substr($cleaned, 8);
            }
            $worker->avatar = $cleaned;
            $worker->save();
            $count++;
        }

        $this->info(sprintf('%d ta workerning avatar yo\'li muvaffaqiyatli tozalandi.', $count));
        return 0;
    }
}
