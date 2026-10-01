<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('getBalance', function ($id) {
                return 1000000;
            });
            $pdo->sqliteCreateFunction('IF', function ($condition, $trueVal, $falseVal) {
                return $condition ? $trueVal : $falseVal;
            });
            $pdo->sqliteCreateFunction('CONCAT', function (...$args) {
                return implode('', $args);
            });
            $pdo->sqliteCreateFunction('TIMESTAMPDIFF', function ($unit, $from, $to) {
                if (!$from || !$to) return 0;
                $fromTs = strtotime($from);
                $toTs = strtotime($to);
                $diff = $toTs - $fromTs;
                if (strtoupper($unit) === 'MINUTE') return (int)($diff / 60);
                if (strtoupper($unit) === 'SECOND') return $diff;
                if (strtoupper($unit) === 'HOUR') return (int)($diff / 3600);
                return $diff;
            });
            $pdo->sqliteCreateFunction('GREATEST', function (...$args) {
                return max($args);
            });
            $pdo->sqliteCreateFunction('LEAST', function (...$args) {
                return min($args);
            });
            $pdo->sqliteCreateFunction('TIME', function ($datetime) {
                return $datetime ? date('H:i:s', strtotime($datetime)) : null;
            });
            $pdo->sqliteCreateFunction('DATE', function ($datetime, $modifier = null) {
                if (!$datetime) return null;
                $ts = strtotime($datetime);
                if ($modifier) {
                    $ts = strtotime($modifier, $ts);
                }
                return date('Y-m-d', $ts);
            });
            $pdo->sqliteCreateFunction('TIMESTAMP', function ($date, $time = null) {
                return $time ? "$date $time" : $date;
            });
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            $this->seed(\Database\Seeders\RoleSeeder::class);
        }
    }
}
