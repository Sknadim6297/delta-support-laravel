<?php

namespace App\Services;

use RuntimeException;

class RootAdminBootstrap
{
    public const BACKUP_CONFIRMATION = 'backup-verified';

    public const TTL_SECONDS = 1800;

    public static function hashSecret(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * @return array{token: string, gate: string}
     */
    public function issue(string $databaseName): array
    {
        $databaseName = trim($databaseName);
        $approved = (string) config('deltasupport.admin_bootstrap_database');
        if ($databaseName === '' || $approved === '' || $databaseName !== $approved) {
            throw new RuntimeException('Refused. The bootstrap database does not match the approved name. No seal was written.');
        }

        if (app(ProductionDatabaseTarget::class)->isProtected($databaseName)) {
            throw new RuntimeException('Refused. '.$databaseName.' is protected. No seal was written.');
        }

        if ($this->isConsumed()) {
            throw new RuntimeException('Refused. Root admin bootstrap is already closed. No seal was written.');
        }

        $token = $this->randomSecret();
        $gate = $this->randomSecret();
        $this->writeJson('seal.json', [
            'version' => 1,
            'token_hash' => self::hashSecret($token),
            'gate_hash' => self::hashSecret($gate),
            'database_name' => $databaseName,
            'expires_at' => time() + self::TTL_SECONDS,
        ]);

        return ['token' => $token, 'gate' => $gate];
    }

    public function isConsumed(): bool
    {
        return is_file($this->consumedPath());
    }

    /**
     * @return array{token_hash: string, gate_hash: string, database_name: string, expires_at: int}|null
     */
    public function readSeal(): ?array
    {
        if ($this->isConsumed() || ! is_file($this->sealPath())) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->sealPath()), true);
        $seal = $this->validatedSeal(is_array($decoded) ? $decoded : null);
        if ($seal === null) {
            $this->delete($this->sealPath());

            return null;
        }

        if ($seal['expires_at'] <= time()) {
            $this->delete($this->sealPath());

            return null;
        }

        return $seal;
    }

    public function gateMatches(string $gate): bool
    {
        $seal = $this->readSeal();
        if ($seal === null || ! $this->isSecretShape($gate)) {
            return false;
        }

        return hash_equals($seal['gate_hash'], self::hashSecret($gate));
    }

    public function tokenMatches(string $token): bool
    {
        $seal = $this->readSeal();
        if ($seal === null || ! $this->isSecretShape($token)) {
            return false;
        }

        return hash_equals($seal['token_hash'], self::hashSecret($token));
    }

    public function consume(): void
    {
        $seal = $this->readSeal();
        $database = $seal['database_name'] ?? (string) config('deltasupport.admin_bootstrap_database');
        $this->writeJson('consumed.json', [
            'consumed_at' => time(),
            'database_name' => $database,
        ]);
        $this->delete($this->sealPath());
    }

    public function sealPath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'seal.json';
    }

    public function consumedPath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'consumed.json';
    }

    private function randomSecret(): string
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        if (! $this->isSecretShape($secret)) {
            throw new RuntimeException('Refused. A bootstrap secret could not be generated. No seal was written.');
        }

        return $secret;
    }

    private function isSecretShape(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $value) === 1;
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @return array{token_hash: string, gate_hash: string, database_name: string, expires_at: int}|null
     */
    private function validatedSeal(?array $decoded): ?array
    {
        if ($decoded === null) {
            return null;
        }

        $tokenHash = $decoded['token_hash'] ?? null;
        $gateHash = $decoded['gate_hash'] ?? null;
        $database = $decoded['database_name'] ?? null;
        $expiresAt = $decoded['expires_at'] ?? null;
        if (! is_string($tokenHash) || ! is_string($gateHash) || ! is_string($database) || ! is_int($expiresAt)) {
            return null;
        }

        if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1 || preg_match('/^[a-f0-9]{64}$/', $gateHash) !== 1) {
            return null;
        }

        if ($database !== (string) config('deltasupport.admin_bootstrap_database')) {
            return null;
        }

        if (app(ProductionDatabaseTarget::class)->isProtected($database)) {
            return null;
        }

        if ($expiresAt > time() + self::TTL_SECONDS + 60) {
            return null;
        }

        return [
            'token_hash' => $tokenHash,
            'gate_hash' => $gateHash,
            'database_name' => $database,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param  array<string, int|string>  $payload
     */
    private function writeJson(string $filename, array $payload): void
    {
        $directory = $this->directory();
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Refused. The bootstrap directory could not be created. Nothing was written.');
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $target = $directory.DIRECTORY_SEPARATOR.$filename;
        $temporary = $target.'.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Refused. The bootstrap file could not be written.');
        }

        @chmod($temporary, 0600);
        if (is_file($target)) {
            $this->delete($target);
        }
        if (! rename($temporary, $target)) {
            $this->delete($temporary);

            throw new RuntimeException('Refused. The bootstrap file could not be saved.');
        }
    }

    private function directory(): string
    {
        return self::storageDirectory();
    }

    public static function storageDirectory(): string
    {
        $configured = config('deltasupport.admin_bootstrap_directory');
        $directory = is_string($configured) && $configured !== ''
            ? $configured
            : storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'admin-bootstrap');
        $public = public_path();
        if ($public !== '' && str_starts_with(strtolower($directory), strtolower(rtrim($public, '\\/')))) {
            throw new RuntimeException('Refused. The bootstrap seal path is inside the public web directory.');
        }

        return $directory;
    }

    private function delete(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
