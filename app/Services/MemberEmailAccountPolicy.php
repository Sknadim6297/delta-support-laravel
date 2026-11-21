<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberEmailAccountPolicy
{
    public const LIMIT = 5;

    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public function count(string $email): int
    {
        $normalized = $this->normalize($email);
        if ($normalized === '') {
            return 0;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('members', 'normalized_email')) {
            return (int) DB::table('members')
                ->where('normalized_email', $normalized)
                ->count();
        }

        return (int) DB::table('members')
            ->get(['email'])
            ->filter(fn (object $member): bool => $this->normalize((string) $member->email) === $normalized)
            ->count();
    }

    public function assertAvailable(string $email, ?string $exceptMemberId = null): void
    {
        $normalized = $this->normalize($email);
        if ($normalized === '') {
            return;
        }

        $count = $this->count($normalized);
        if ($exceptMemberId !== null) {
            $count -= DB::table('members')
                ->where('member_id', $exceptMemberId)
                ->where(function ($query) use ($normalized): void {
                    $query->where('normalized_email', $normalized)
                        ->orWhereRaw('LOWER(TRIM(email)) = ?', [$normalized]);
                })
                ->count();
        }

        if ($count >= self::LIMIT) {
            throw ValidationException::withMessages([
                'email' => 'This email address has already reached the maximum limit of 5 member accounts.',
            ]);
        }
    }

    public function lockAndAssertAvailable(string $email, ?string $exceptMemberId = null): void
    {
        $normalized = $this->normalize($email);
        if ($normalized === '') {
            return;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('member_email_account_locks')) {
            DB::table('member_email_account_locks')->insertOrIgnore([
                'normalized_email' => $normalized,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('member_email_account_locks')
                ->where('normalized_email', $normalized)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $this->assertAvailable($normalized, $exceptMemberId);
    }

    public function validationRule(?string $exceptMemberId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($exceptMemberId): void {
            try {
                $this->assertAvailable((string) $value, $exceptMemberId);
            } catch (ValidationException $exception) {
                $messages = $exception->errors()['email'] ?? [];
                $fail($messages[0] ?? 'This email address has already reached the maximum limit of 5 member accounts.');
            }
        };
    }
}
