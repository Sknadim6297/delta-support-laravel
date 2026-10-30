<?php

namespace App\Services;

use RuntimeException;

class RootAdminSeedFile
{
    public function path(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'root-admin.json';
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function write(string $name, string $email, #[\SensitiveParameter] string $passwordHash): void
    {
        $directory = $this->directory();
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Refused. The root admin directory could not be created. No file was written.');
        }

        $json = json_encode([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $this->path().'.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Refused. The root admin file could not be written.');
        }

        @chmod($temporary, 0600);
        if (is_file($this->path())) {
            $this->deletePath($this->path());
        }
        if (! rename($temporary, $this->path())) {
            $this->deletePath($temporary);

            throw new RuntimeException('Refused. The root admin file could not be saved.');
        }
    }

    /**
     * @return array{name: string, email: string, password_hash: string}
     */
    public function read(): array
    {
        if (! $this->exists()) {
            throw new RuntimeException('Refused. The root admin file is missing. No account was created.');
        }

        $decoded = json_decode((string) file_get_contents($this->path()), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Refused. The root admin file could not be read. No account was created.');
        }

        $name = $decoded['name'] ?? 'Delta Support Admin';
        $email = $decoded['email'] ?? null;
        $passwordHash = $decoded['password_hash'] ?? null;
        if (! is_string($name) || ! is_string($email) || ! is_string($passwordHash)) {
            throw new RuntimeException('Refused. The root admin file could not be read. No account was created.');
        }

        return [
            'name' => trim($name),
            'email' => trim($email),
            'password_hash' => trim($passwordHash),
        ];
    }

    public function delete(): void
    {
        $this->deletePath($this->path());
        $this->deletePath($this->path().'.tmp');
    }

    private function directory(): string
    {
        return RootAdminBootstrap::storageDirectory();
    }

    private function deletePath(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
