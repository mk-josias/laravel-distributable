<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dataset;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Reads the datasets analytics:compute wrote, never the journals: a reading folds hourly rows. */
final class ShowStats extends Command
{
    protected $signature = 'analytics:stats {--date= : The day to read, today by default}';

    protected $description = 'Sum the hourly datasets of a day';

    public function handle(): int
    {
        $day = Carbon::parse((string) ($this->option('date') ?? 'today'))->startOfDay();
        $datasets = Dataset::query()->where('bucket', '>=', $day)->where('bucket', '<', $day->copy()->addDay());

        $this->line('signups: '.(clone $datasets)->sum('signups_count').', mails: '.(clone $datasets)->sum('mails_sent_count'));

        return self::SUCCESS;
    }
}
