<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\Member;
use App\Models\MemberPreviewSession;
use App\Models\User;
use App\Support\MemberContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Isolated member-preview authorization.
 *
 * Admin stays on the primary `web` guard / session cookie.
 * Preview identity lives only in a dedicated cookie + member_preview_sessions row.
 * Never calls Auth::login() for the member and never writes member_context_id into the admin session.
 */
class MemberPreviewGuard
{
    public function cookieName(): string
    {
        return (string) config('deltasupport.member_preview.cookie', 'delta_support_member_preview');
    }

    public function lifetimeMinutes(): int
    {
        return max(5, (int) config('deltasupport.member_preview.lifetime_minutes', 120));
    }

    public function active(Request $request): bool
    {
        return MemberContext::isPreview($request);
    }

    public function activeForAdmin(int $adminUserId): ?MemberPreviewSession
    {
        if (! Schema::hasTable('member_preview_sessions')) {
            return null;
        }

        return MemberPreviewSession::query()
            ->where('admin_user_id', $adminUserId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * @return array{session: MemberPreviewSession, plain_token: string}
     */
    public function issue(User $admin, Member $member, Request $request): array
    {
        if ($admin->is_admin !== true) {
            throw new AccessDeniedHttpException('Only an administrator can preview a member account.');
        }

        $this->revokeForAdmin((int) $admin->id);

        $uuid = (string) Str::uuid();
        $secret = Str::random(64);
        $plain = $uuid.'.'.$secret;

        $session = MemberPreviewSession::query()->create([
            'uuid' => $uuid,
            'admin_user_id' => $admin->id,
            'member_id' => $member->member_id,
            'token_hash' => hash('sha256', $secret),
            'expires_at' => now()->addMinutes($this->lifetimeMinutes()),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        ImpersonationLog::query()->create([
            'admin_id' => $admin->id,
            'member_id' => $member->member_id,
            'action' => 'started',
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return ['session' => $session, 'plain_token' => $plain];
    }

    public function makeCookie(string $plainToken): SymfonyCookie
    {
        return Cookie::make(
            $this->cookieName(),
            $plainToken,
            $this->lifetimeMinutes(),
            '/',
            config('session.domain'),
            (bool) config('session.secure'),
            true,
            false,
            config('session.same_site', 'lax') ?: 'lax',
        );
    }

    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget($this->cookieName(), '/', config('session.domain'));
    }

    public function bindFromRequest(Request $request): void
    {
        if (! Schema::hasTable('member_preview_sessions')) {
            return;
        }

        $raw = $request->cookie($this->cookieName());
        if (! is_string($raw) || $raw === '' || ! str_contains($raw, '.')) {
            return;
        }

        [$uuid, $secret] = explode('.', $raw, 2);
        if ($uuid === '' || $secret === '') {
            return;
        }

        $row = MemberPreviewSession::query()->where('uuid', $uuid)->first();
        if ($row === null || ! $row->isActive()) {
            return;
        }

        if (! hash_equals((string) $row->token_hash, hash('sha256', $secret))) {
            return;
        }

        if (! Member::query()->where('member_id', $row->member_id)->exists()) {
            return;
        }

        $row->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set(MemberContext::PREVIEW_ACTIVE_ATTRIBUTE, true);
        $request->attributes->set(MemberContext::PREVIEW_MEMBER_ATTRIBUTE, $row->member_id);
        $request->attributes->set(MemberContext::PREVIEW_ADMIN_ATTRIBUTE, (int) $row->admin_user_id);
        $request->attributes->set(MemberContext::PREVIEW_SESSION_ATTRIBUTE, (int) $row->id);
    }

    public function revokeForAdmin(int $adminUserId): int
    {
        if (! Schema::hasTable('member_preview_sessions')) {
            return 0;
        }

        $updated = MemberPreviewSession::query()
            ->where('admin_user_id', $adminUserId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($updated > 0 && Schema::hasTable('impersonation_logs')) {
            ImpersonationLog::query()
                ->where('admin_id', $adminUserId)
                ->whereNull('ended_at')
                ->update([
                    'action' => 'ended',
                    'ended_at' => now(),
                ]);
        }

        return $updated;
    }

    public function revokeFromRequest(Request $request): void
    {
        $sessionId = $request->attributes->get(MemberContext::PREVIEW_SESSION_ATTRIBUTE);
        if (is_numeric($sessionId)) {
            MemberPreviewSession::query()
                ->whereKey((int) $sessionId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        $adminId = MemberContext::previewAdminId($request);
        if ($adminId) {
            $this->revokeForAdmin($adminId);
        }

        $user = $request->user();
        if ($user?->is_admin === true) {
            $this->revokeForAdmin((int) $user->id);
        }
    }
}
