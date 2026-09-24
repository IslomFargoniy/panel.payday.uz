<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('hikvision_access_events')
            ->where('attendanceStatus', 'breakOut')
            ->orWhere('label', 'Tanaffusga chiqdi')
            ->update([
                'attendanceStatus' => 'checkOut',
                'label' => 'Ketdi'
            ]);

        DB::table('hikvision_access_events')
            ->where('attendanceStatus', 'breakIn')
            ->orWhere('label', 'Tanaffusdan qaytdi')
            ->update([
                'attendanceStatus' => 'checkIn',
                'label' => 'Keldi'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed as breakIn/breakOut is abandoned permanently
    }
};
