<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('workers')) {
            $indexes = collect(Schema::getIndexes('workers'))->pluck('name')->all();

            Schema::table('workers', function (Blueprint $table) use ($indexes) {
                if (in_array('workers_phone_unique', $indexes)) {
                    $table->dropUnique('workers_phone_unique');
                }
                if (!in_array('workers_phone_index', $indexes)) {
                    $table->index('phone');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('workers')) {
            $indexes = collect(Schema::getIndexes('workers'))->pluck('name')->all();

            Schema::table('workers', function (Blueprint $table) use ($indexes) {
                if (in_array('workers_phone_index', $indexes)) {
                    $table->dropIndex('workers_phone_index');
                }
                if (!in_array('workers_phone_unique', $indexes)) {
                    $table->unique('phone');
                }
            });
        }
    }
};
