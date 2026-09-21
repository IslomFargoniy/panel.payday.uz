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
        Schema::table('hikvision_access_events', function (Blueprint $table) {
            $table->index(['created_at', 'employeeNoString'], 'hae_created_at_emp_no_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hikvision_access_events', function (Blueprint $table) {
            $table->dropIndex('hae_created_at_emp_no_index');
        });
    }
};
