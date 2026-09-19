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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $dbUsername = env('DB_USERNAME', 'root');

        DB::unprepared("DROP FUNCTION IF EXISTS count_working_days;");

        DB::unprepared("
            CREATE DEFINER=`$dbUsername`@`localhost` FUNCTION `count_working_days`(workerId INT, from_ DATE, to_ DATE) RETURNS INT
                READS SQL DATA
            BEGIN
                DECLARE working_days INT DEFAULT 0;
                DECLARE effectiveBranchId INT;
                DECLARE effectiveFirmId INT;
                DECLARE workingIndexes VARCHAR(255);

                -- 1. Resolve branch and firm info once
                SELECT w.branch_id, b.firm_id INTO effectiveBranchId, effectiveFirmId
                FROM workers w
                LEFT JOIN branches b ON w.branch_id = b.id
                WHERE w.id = workerId LIMIT 1;

                -- 2. Fetch working day indexes once (prioritizing worker-specific days)
                SELECT COALESCE(
                    (SELECT GROUP_CONCAT(d.`index`) FROM worker_days wd JOIN days d ON d.id = wd.day_id WHERE wd.worker_id = workerId),
                    (SELECT GROUP_CONCAT(d.`index`) FROM branch_days bd JOIN days d ON d.id = bd.day_id WHERE bd.branch_id = effectiveBranchId),
                    '1,2,3,4,5,6,7'
                ) INTO workingIndexes;

                -- 3. Calculate working days using a recursive date range
                WITH RECURSIVE date_range AS (
                    SELECT from_ AS date_val
                    UNION ALL
                    SELECT DATE_ADD(date_val, INTERVAL 1 DAY) FROM date_range WHERE date_val < to_
                )
                SELECT COUNT(*) INTO working_days
                FROM date_range dr
                WHERE FIND_IN_SET(DAYOFWEEK(dr.date_val), workingIndexes)
                  -- Optimized holiday checks (hitting the new indexes)
                  AND NOT EXISTS (SELECT 1 FROM worker_holidays WHERE worker_id = workerId AND dr.date_val BETWEEN `from` AND `to`)
                  AND NOT EXISTS (SELECT 1 FROM branch_holidays WHERE branch_id = effectiveBranchId AND `date` = dr.date_val)
                  AND NOT EXISTS (SELECT 1 FROM firm_holidays WHERE firm_id = effectiveFirmId AND `date` = dr.date_val);

                RETURN working_days;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To properly revert, we'd need to restore the original version from FunctionSeeder.
        // For simplicity, we just leave the optimized version or drop it if absolutely necessary.
    }
};
