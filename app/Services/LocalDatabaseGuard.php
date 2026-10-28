<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalDatabaseGuard
{
    public const DATABASE = 'delta_support';

    public const CONFIRM = 'local-delta-support';

    public function assertOriginalLocal(): string
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Refused. Local income testing runs only when APP_ENV is local. No wallet was credited.');
        }

        $host = (string) config('database.connections.mysql.host');
        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('Refused. The database host is not the local machine. No wallet was credited.');
        }

        if ((string) config('database.connections.mysql.database') !== self::DATABASE) {
            throw new RuntimeException('Refused. The configured database is not delta_support. No wallet was credited.');
        }

        $actual = (string) DB::connection()->getDatabaseName();
        if ($actual !== self::DATABASE) {
            throw new RuntimeException('Refused. The connected database is not delta_support. No wallet was credited.');
        }

        return $actual;
    }
}
