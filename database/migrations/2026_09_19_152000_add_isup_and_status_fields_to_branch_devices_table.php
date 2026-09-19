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
        Schema::table('branch_devices', function (Blueprint $table) {
            $table->string('name')->nullable()->after('branch_id');
            $table->string('device_id')->nullable()->after('mac_address');
            $table->string('connection_type')->default('http_listening')->after('device_id'); // 'isup', 'http_listening'
            $table->boolean('status')->default(true)->after('connection_type');
            $table->boolean('is_online')->default(false)->after('status');
            $table->timestamp('last_seen_at')->nullable()->after('is_online');
            $table->string('encryption_key')->nullable()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_devices', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'device_id',
                'connection_type',
                'status',
                'is_online',
                'last_seen_at',
                'encryption_key',
            ]);
        });
    }
};
