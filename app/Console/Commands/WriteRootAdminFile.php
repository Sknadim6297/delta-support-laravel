<?php

namespace App\Console\Commands;

use App\Rules\StrongPassword;
use App\Services\RootAdminBootstrap;
use App\Services\RootAdminSeedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class WriteRootAdminFile extends Command
{
    protected $signature = 'admin:root-admin-file
        {--email= : Administrator email stored in the private file}
        {--name= : Administrator name stored in the private file}';

    protected $description = 'Write a private root admin file that contains a password hash. The password is not printed or stored.';

    public function handle(RootAdminSeedFile $file, RootAdminBootstrap $bootstrap): int
    {
        if (! $this->input->isInteractive() || app()->runningUnitTests()) {
            $this->forgetPassword();
            $this->error('Refused. Run this on an interactive local console. No file was written.');

            return self::FAILURE;
        }

        if ($bootstrap->isConsumed()) {
            $this->forgetPassword();
            $this->error('Refused. Root admin provisioning is already closed. No file was written.');

            return self::FAILURE;
        }

        $email = trim((string) $this->option('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->forgetPassword();
            $this->error('Refused. Pass a valid --email. No file was written.');

            return self::FAILURE;
        }

        $password = $this->suppliedPassword();
        if ($password === null || ! StrongPassword::accepts($password)) {
            $this->forgetPassword();
            $this->error('Refused. Set ROOT_ADMIN_PASSWORD or enter a strong password at the hidden prompt. No file was written.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: 'Delta Support Admin'));

        try {
            $file->write($name, $email, Hash::make($password));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $this->forgetPassword();
        }

        $this->info('Root admin file written. Upload only that file through Plesk File Manager:');
        $this->line($file->path());
        $this->line('The password and hash were not printed.');

        return self::SUCCESS;
    }

    private function suppliedPassword(): ?string
    {
        $password = getenv('ROOT_ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            $fromServer = $_SERVER['ROOT_ADMIN_PASSWORD'] ?? '';
            $password = is_string($fromServer) ? $fromServer : '';
        }

        if ($password === '' && $this->input->isInteractive() && ! app()->runningUnitTests()) {
            $password = (string) $this->secret('Root admin password');
        }

        return $password === '' ? null : $password;
    }

    private function forgetPassword(): void
    {
        putenv('ROOT_ADMIN_PASSWORD');
        unset($_ENV['ROOT_ADMIN_PASSWORD'], $_SERVER['ROOT_ADMIN_PASSWORD']);
    }
}
