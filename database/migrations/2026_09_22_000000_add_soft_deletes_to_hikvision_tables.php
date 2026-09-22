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
        if (Schema::hasTable('hikvision_access_events') && !Schema::hasColumn('hikvision_access_events', 'deleted_at')) {
            Schema::table('hikvision_access_events', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at')->index();
            });
        }

        if (Schema::hasTable('hikvision_accesses') && !Schema::hasColumn('hikvision_accesses', 'deleted_at')) {
            Schema::table('hikvision_accesses', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hikvision_access_events') && Schema::hasColumn('hikvision_access_events', 'deleted_at')) {
            Schema::table('hikvision_access_events', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('hikvision_accesses') && Schema::hasColumn('hikvision_accesses', 'deleted_at')) {
            Schema::table('hikvision_accesses', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
