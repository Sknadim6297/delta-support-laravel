<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the active member identity for member-panel requests.
 *
 * Priority:
 * 1) Isolated member-preview cookie (admin Login as Member) — never written into the admin session
 * 2) Normal member_context_id on the primary web session (ordinary member login)
 */
class MemberContext
{
    public const PREVIEW_MEMBER_ATTRIBUTE = 'member_preview_member_id';

    public const PREVIEW_ACTIVE_ATTRIBUTE = 'member_preview_active';

    public const PREVIEW_ADMIN_ATTRIBUTE = 'member_preview_admin_id';

    public const PREVIEW_SESSION_ATTRIBUTE = 'member_preview_session_id';

    public static function id(?Request $request = null): ?string
    {
        $request ??= request();

        $preview = $request->attributes->get(self::PREVIEW_MEMBER_ATTRIBUTE);
        if (is_string($preview) && $preview !== '') {
            return $preview;
        }

        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->get('member_context_id');

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    public static function isPreview(?Request $request = null): bool
    {
        $request ??= request();

        return (bool) $request->attributes->get(self::PREVIEW_ACTIVE_ATTRIBUTE, false);
    }

    public static function previewAdminId(?Request $request = null): ?int
    {
        $request ??= request();
        $adminId = $request->attributes->get(self::PREVIEW_ADMIN_ATTRIBUTE);

        return is_numeric($adminId) ? (int) $adminId : null;
    }
}
