<?php

declare(strict_types=1);

use Apps\Analytics\Support\MonthlyPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per hour, every measure of that hour on it: a reading is a filter on `bucket`, never a join.
 * On PostgreSQL the table is partitioned by month, so a seek on one day walks one month and retention
 * is a DETACH; elsewhere it is a plain table and retention a DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::create('analytics_datasets', function (Blueprint $table): void {
                $table->timestamp('bucket')->primary();
                $table->unsignedInteger('signups_count')->default(0);
                $table->unsignedInteger('mails_sent_count')->default(0);
                $table->unsignedInteger('users_total')->default(0);
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE analytics_datasets (
                bucket            timestamp(0) NOT NULL,
                signups_count     integer      NOT NULL DEFAULT 0,
                mails_sent_count  integer      NOT NULL DEFAULT 0,
                users_total       integer      NOT NULL DEFAULT 0,
                PRIMARY KEY (bucket)
            ) PARTITION BY RANGE (bucket)
        SQL);

        app(MonthlyPartitions::class)->ensure(now(), DB::connection());
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS analytics_datasets CASCADE');
    }
};
