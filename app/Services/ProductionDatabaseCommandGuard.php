<?php

namespace App\Services;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProductionDatabaseCommandGuard
{
    private const DESTRUCTIVE_COMMANDS = [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function __construct(private readonly ProductionDatabaseTarget $targets)
    {
    }

    public function handle(CommandStarting $event): void
    {
        if (! in_array($event->command, self::DESTRUCTIVE_COMMANDS, true)) {
            return;
        }

        if (app()->environment('production')) {
            $this->refuse('Destructive database commands are disabled in production; use the approved recovery procedure.');
        }

        if (app()->environment('testing')) {
            return;
        }

        try {
            $name = (string) DB::connection()->getDatabaseName();
        } catch (Throwable) {
            $this->refuse('Destructive database commands are disabled because the connected database could not be verified.');
        }

        if ($this->targets->isProtected($name)) {
            $this->refuse('Destructive database commands are disabled for the protected database '.$name.'. Nothing was deleted.');
        }
    }

    private function refuse(string $message): never
    {
        throw new RuntimeException($message);
    }
}
