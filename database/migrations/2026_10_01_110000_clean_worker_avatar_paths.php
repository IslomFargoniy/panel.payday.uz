<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('workers') && Schema::hasColumn('workers', 'avatar')) {
            $workers = DB::table('workers')
                ->whereNotNull('avatar')
                ->where(function ($q) {
                    $q->where('avatar', 'like', '/%')
                      ->orWhere('avatar', 'like', 'storage/%');
                })
                ->select('id', 'avatar')
                ->get();

            foreach ($workers as $w) {
                $cleaned = ltrim(str_replace('/storage/', '', $w->avatar), '/');
                if (str_starts_with($cleaned, 'storage/')) {
                    $cleaned = substr($cleaned, 8);
                }
                $cleaned = ltrim($cleaned, '/');
                DB::table('workers')->where('id', $w->id)->update(['avatar' => $cleaned]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for cleaned file paths
    }
};
