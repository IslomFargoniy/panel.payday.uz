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
        if (!Schema::hasTable('salary_worker_days')) {
            Schema::create('salary_worker_days', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_id');
                $table->foreign('salary_id')->references('id')->on('salaries')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->unsignedBigInteger('day_id');
                $table->foreign('day_id')->references('id')->on('days')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_worker_holidays')) {
            Schema::create('salary_worker_holidays', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_id');
                $table->foreign('salary_id')->references('id')->on('salaries')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->date('from');
                $table->date('to');
                $table->string('comment')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_worker_holidays');
        Schema::dropIfExists('salary_worker_days');
    }
};
