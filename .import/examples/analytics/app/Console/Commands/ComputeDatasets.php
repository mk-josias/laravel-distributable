<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dataset;
use App\Models\Mail;
use App\Models\Signup;
use App\Support\MonthlyPartitions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** A dataset row is derived: a day is rebuilt from the journals, so a day computed twice ends the same. */
final class ComputeDatasets extends Command
{
    protected $signature = 'analytics:compute {--date= : The day to (re)compute, today by default}';

    protected $description = 'Rebuild the hourly datasets of a day from the journals';

    public function handle(MonthlyPartitions $partitions): int
    {
        $day = Carbon::parse((string) ($this->option('date') ?? 'today'))->startOfDay();
        $next = $day->copy()->addDay();
        $partitions->ensure($day, DB::connection());

        $signups = $this->perHour(Signup::query()->where('created_at', '>=', $day)->where('created_at', '<', $next)->pluck('created_at'));
        $mails = $this->perHour(Mail::query()->where('created_at', '>=', $day)->where('created_at', '<', $next)->pluck('created_at'));
        $total = Signup::query()->where('created_at', '<', $day)->count();
        $hours = $signups->keys()->merge($mails->keys())->unique()->sort()->values();

        DB::transaction(function () use ($day, $next, $hours, $signups, $mails, &$total): void {
            Dataset::query()->where('bucket', '>=', $day)->where('bucket', '<', $next)->delete();

            foreach ($hours as $hour) {
                $total += $signups->get($hour, 0);

                Dataset::query()->create([
                    'bucket' => $hour,
                    'signups_count' => $signups->get($hour, 0),
                    'mails_sent_count' => $mails->get($hour, 0),
                    'users_total' => $total,
                ]);
            }
        });

        $this->components->info("{$hours->count()} hourly buckets computed for {$day->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Carbon>  $moments
     * @return Collection<string, int>
     */
    private function perHour(Collection $moments): Collection
    {
        return $moments->countBy(fn (Carbon $at): string => $at->copy()->startOfHour()->toDateTimeString());
    }
}
