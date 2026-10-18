<?php

declare(strict_types=1);

namespace Microservices\Migrations;

use Illuminate\Database\Migrations\Migration;
use Microservices\Contracts\Colocation;
use Microservices\Models\ShadowModel;
use Microservices\Services\Shadows\ShadowRegistry;

/** The migration of a copy: it creates the table the keeper's copy model reads. */
abstract class ShadowMigration extends Migration
{
    /** The service whose database the migration runs in, when a migrate command runs it for several. */
    public static string $keeper = '';

    /**
     * The copy this migration builds, by its class: its source comes from it, never from a string repeated here.
     *
     * @return class-string<ShadowModel>
     */
    abstract protected function shadow(): string;

    /** The keeper's own class of that source when it keeps a subclass, so its $table wins. */
    protected function table(): string
    {
        $keeper = static::$keeper !== '' ? static::$keeper : (string) app(Colocation::class)->current();
        $kept = app(ShadowRegistry::class)->shadowsOf($this->shadow()::sourceTable(), $keeper)[0] ?? $this->shadow();

        return (new $kept)->getTable();
    }
}
