<?php

/**
 * Plesk Windows "PHP script" entrypoint (PHP CLI / Plesk scheduler).
 *
 * No proc_open, no Symfony Process, no shell_exec.
 * Bootstraps Laravel, then calls deltasupport:scheduler-tick in-process.
 *
 * Phase 1 / safe default when env is unset: --verify-only
 *
 * Live financial posting requires ALL of:
 *   1) DELTASUPPORT_SCHEDULER_MODE=live in production .env
 *   2) DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=true
 *   3) financial automation already armed
 *   4) Explicit operator authorization
 *
 * Plesk task:
 *   Type: Run a PHP script
 *   Script: <httpdocs>\scripts\plesk\run-scheduler-tick.php
 *   Frequency: Every 1 minute
 *   PHP version: same as the site (8.2+)
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$base = dirname(__DIR__, 2);
$autoload = $base.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
$bootstrap = $base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

if (! is_file($autoload) || ! is_file($bootstrap)) {
    fwrite(STDERR, "Delta Support scheduler tick: Laravel bootstrap files missing under {$base}\n");
    exit(1);
}

require $autoload;

/** @var \Illuminate\Foundation\Application $app */
$app = require $bootstrap;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$mode = strtolower(trim((string) (
    $_ENV['DELTASUPPORT_SCHEDULER_MODE']
    ?? $_SERVER['DELTASUPPORT_SCHEDULER_MODE']
    ?? getenv('DELTASUPPORT_SCHEDULER_MODE')
    ?: 'verify'
)));

$parameters = match ($mode) {
    'live' => ['--live' => true],
    'dry-run', 'dryrun' => ['--dry-run' => true],
    default => ['--verify-only' => true],
};

$status = $kernel->call('deltasupport:scheduler-tick', $parameters);
echo $kernel->output();
exit($status);
