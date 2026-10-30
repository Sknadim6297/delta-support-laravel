<?php

namespace App\Console\Commands;

use App\Services\ProductionDatabaseTarget;
use App\Services\RootAdminBootstrap;
use Illuminate\Console\Command;
use RuntimeException;

class SealRootAdminBootstrap extends Command
{
    protected $signature = 'admin:bootstrap-seal
        {--database= : Approved database name recorded in the seal. The token is not stored.}';

    protected $description = 'Write a one-time Root Admin seal that stores only hashes. Prints the token on an interactive local console.';

    public function handle(RootAdminBootstrap $bootstrap, ProductionDatabaseTarget $targets): int
    {
        $database = trim((string) $this->option('database'));
        $approved = (string) config('deltasupport.admin_bootstrap_database');
        if ($database === '' || $database !== $approved) {
            $this->error('Refused. Pass --database with the approved bootstrap database. No seal was written.');

            return self::FAILURE;
        }

        if ($targets->isProtected($database)) {
            $this->error('Refused. '.$database.' is protected. No seal was written.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive() || app()->runningUnitTests()) {
            $this->error('Refused. Run this on an interactive local console. No seal was written and no token was printed.');

            return self::FAILURE;
        }

        try {
            $issued = $bootstrap->issue($database);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Seal file written. Upload only this file through Plesk File Manager:');
        $this->line($bootstrap->sealPath());
        $this->line('HTTPS path: /admin/bootstrap/'.$issued['gate']);
        $this->line('Bootstrap token:');
        $this->line($issued['token']);
        $this->line('Copy both into a password manager, then close this console. Neither value is in the seal file.');

        return self::SUCCESS;
    }
}
