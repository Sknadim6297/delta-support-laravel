<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberRegistrationService
{
    public const ADMIN_SPONSOR_ID = 'ST666666';

    public function __construct(
        private readonly ?SponsorChain $sponsors = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{require_pan?: bool, allow_admin_sponsor?: bool, blank_sponsor_uses_admin?: bool, locked_sponsor?: ?Member, generate_password?: bool}  $options
     * @return array{member: Member, temporary_password: ?string}
     */
    public function register(array $input, array $options = []): array
    {
        $requirePan = (bool) ($options['require_pan'] ?? false);
        $pan = strtoupper(trim((string) ($input['pan_card_no'] ?? '')));

        if ($requirePan && strlen($pan) !== 10) {
            throw ValidationException::withMessages([
                'pan_card_no' => 'PAN Card Number must be exactly 10 characters.',
            ]);
        }

        if ($pan !== '' && Member::query()->whereRaw('UPPER(pan_card_no) = ?', [$pan])->count() >= 3) {
            throw ValidationException::withMessages([
                'pan_card_no' => 'This PAN card number has already been used 3 times.',
            ]);
        }

        $mobile = trim((string) ($input['mobile_no'] ?? ''));
        if (Member::query()->where('mobile_no', $mobile)->count() >= 3) {
            throw ValidationException::withMessages([
                'mobile_no' => 'This mobile number has already been used 3 times.',
            ]);
        }

        $sponsor = $this->sponsor($input, $options);
        $temporaryPassword = ($options['generate_password'] ?? false) ? Str::random(12) : null;
        $password = $temporaryPassword ?? (filled($input['password'] ?? null) ? (string) $input['password'] : null);

        $member = $this->createMember([
            'sponsor_id' => $sponsor['id'],
            'sponsor_name' => $sponsor['name'],
            'member_name' => trim((string) $input['member_name']),
            'email' => trim((string) $input['email']),
            'mobile_no' => $mobile,
            'pan_card_no' => $pan !== '' ? $pan : null,
            'wallet_address' => filled($input['wallet_address'] ?? null) ? trim((string) $input['wallet_address']) : null,
            'password' => $password === null ? null : Hash::make($password),
            'status' => 'inactive',
        ]);

        return [
            'member' => $member,
            'temporary_password' => $temporaryPassword,
        ];
    }

    public function previewMemberId(): string
    {
        return $this->nextMemberId();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{allow_admin_sponsor?: bool, blank_sponsor_uses_admin?: bool, locked_sponsor?: ?Member}  $options
     * @return array{id: string, name: string}
     */
    private function sponsor(array $input, array $options): array
    {
        $locked = $options['locked_sponsor'] ?? null;
        $requested = strtoupper(trim((string) ($input['sponsor_id'] ?? '')));

        if ($locked instanceof Member) {
            if ($requested !== '' && $requested !== $locked->member_id) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'Unauthorized sponsor ID.',
                ]);
            }

            return [
                'id' => $locked->member_id,
                'name' => $locked->member_name,
            ];
        }

        $chain = $this->sponsors ?? new SponsorChain;

        if ($requested === '' && ($options['blank_sponsor_uses_admin'] ?? false)) {
            $requested = $chain->rootId();
        }

        if ($chain->isRoot($requested) && ($options['allow_admin_sponsor'] ?? false)) {
            return [
                'id' => $chain->rootId(),
                'name' => 'Admin',
            ];
        }

        $sponsor = $requested === '' ? null : Member::query()->where('member_id', $requested)->first();
        if (! $sponsor) {
            $message = ($options['blank_sponsor_uses_admin'] ?? false)
                ? 'Invalid Sponsor ID. Enter an existing Member ID or leave it blank to use the default sponsor.'
                : 'Invalid Sponsor ID.';

            throw ValidationException::withMessages([
                'sponsor_id' => $message,
            ]);
        }

        ($this->sponsors ?? new SponsorChain)->assertChainEnds($sponsor->member_id);

        return [
            'id' => $sponsor->member_id,
            'name' => $sponsor->member_name,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(array $attributes): Member
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes): Member {
                    $attributes['member_id'] = $this->nextMemberId();

                    return Member::query()->create($attributes);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 4 || ! str_contains($exception->getMessage(), 'member_id')) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('No available Member ID found.');
    }

    private function nextMemberId(): string
    {
        for ($counter = 100000; $counter <= 999999; $counter++) {
            $candidate = 'ST' . $counter;

            if (! Member::query()->where('member_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No available Member ID found.');
    }
}
