<?php

declare(strict_types=1);

namespace Apps\Analytics\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * The monthly partitions of analytics_datasets on PostgreSQL: a row cannot land in a month without
 * one, and an expired month leaves by DETACH, without a DELETE walking its rows. Creating and
 * detaching a table is DDL, so it runs on the module's owner connection.
 */
final class MonthlyPartitions
{
    private const string TABLE = 'analytics_datasets';

    /** Opens the month of $at and the one after it, so the next hour never finds its month missing. */
    public function ensure(Carbon $at, Connection $owner): void
    {
        if ($owner->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([$at->copy()->startOfMonth(), $at->copy()->addMonthNoOverflow()->startOfMonth()] as $month) {
            $owner->statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF %s FOR VALUES FROM ('%s') TO ('%s')",
                self::name($month),
                self::TABLE,
                $month->toDateString(),
                $month->copy()->addMonthNoOverflow()->toDateString(),
            ));
        }
    }

    /**
     * Drops every month that ends on or before $before; elsewhere than PostgreSQL, deletes its rows.
     *
     * @return list<string> the months dropped, as Y-m
     */
    public function prune(Carbon $before, Connection $owner): array
    {
        $before = $before->copy()->startOfMonth();

        if ($owner->getDriverName() !== 'pgsql') {
            $months = $owner->table(self::TABLE)->where('bucket', '<', $before)->pluck('bucket')
                ->map(fn (mixed $bucket): string => Carbon::parse((string) $bucket)->format('Y-m'))->unique()->values()->all();
            $owner->table(self::TABLE)->where('bucket', '<', $before)->delete();

            return $months;
        }

        $dropped = [];

        foreach ($owner->select('SELECT inhrelid::regclass::text AS name FROM pg_inherits WHERE inhparent = ?::regclass', [self::TABLE]) as $partition) {
            $name = (string) $partition->name;
            $month = Carbon::createFromFormat('Y_m', substr($name, -7))->startOfMonth();

            if ($month->lessThan($before)) {
                $owner->statement('ALTER TABLE '.self::TABLE." DETACH PARTITION {$name}");
                $owner->statement("DROP TABLE {$name}");
                $dropped[] = $month->format('Y-m');
            }
        }

        sort($dropped);

        return $dropped;
    }

    private static function name(Carbon $month): string
    {
        return self::TABLE.'_'.$month->format('Y_m');
    }
}
