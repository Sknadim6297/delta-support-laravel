<?php

namespace App\Support;

/**
 * Single authoritative permission registry for Sub-Admin sidebar visibility and route authorization.
 *
 * Delegable permissions are one key per admin module (ordinary workflows). Sensitive or Main-Admin-only
 * routes remain on reserved keys for deny-by-default route resolution.
 *
 * @phpstan-type PermissionDefinition array{
 *     key: string,
 *     label: string,
 *     group: string,
 *     module: string,
 *     reserved: bool,
 *     routes: list<string>,
 *     parent: ?string,
 *     action: ?string,
 * }
 */
final class AdminPermissionRegistry
{
    /** @var array<string, PermissionDefinition>|null */
    private static ?array $definitions = null;

    /** @var list<string>|null */
    private static ?array $reservedKeys = null;

    /** @var array<string, string>|null */
    private static ?array $legacyAliases = null;

    /** @var list<string>|null */
    private static ?array $moduleKeys = null;

    /**
     * @return array<string, PermissionDefinition>
     */
    public static function definitions(): array
    {
        return self::$definitions ??= self::buildDefinitions();
    }

    /**
     * @return array<string, PermissionDefinition>
     */
    public static function delegableDefinitions(): array
    {
        return array_filter(
            self::definitions(),
            static fn (array $definition): bool => $definition['reserved'] === false
        );
    }

    /**
     * @return list<string>
     */
    public static function reservedKeys(): array
    {
        if (self::$reservedKeys !== null) {
            return self::$reservedKeys;
        }

        $keys = [];
        foreach (self::definitions() as $definition) {
            if ($definition['reserved']) {
                $keys[] = $definition['key'];
            }
        }

        return self::$reservedKeys = $keys;
    }

    /**
     * @return list<string>
     */
    public static function moduleKeys(): array
    {
        return self::$moduleKeys ??= [
            'dashboard',
            'members',
            'tour_requests',
            'packages',
            'payments',
            'invoices',
            'roi',
            'level_income',
            'wallets',
            'withdrawals',
            'rewards',
            'reports',
            'support',
            'settings',
        ];
    }

    /**
     * Maps legacy action-level permission keys to current module keys.
     *
     * @return array<string, string>
     */
    public static function legacyAliases(): array
    {
        return self::$legacyAliases ??= [
            'members.view' => 'members',
            'members.create' => 'members',
            'members.edit' => 'members',
            'members.status' => 'members',
            'members.payment_controls' => 'members',
            'members.impersonate' => 'members',
            'ranks.manage' => 'members',

            'tour_requests.view' => 'tour_requests',
            'tour_requests.manage' => 'tour_requests',
            'tour_requests.export' => 'tour_requests',

            'packages.view' => 'packages',
            'packages.create' => 'packages',
            'packages.edit' => 'packages',
            'packages.manage_status' => 'packages',
            'purchases.view' => 'packages',
            'purchases.create' => 'packages',

            'payments.view' => 'payments',
            'payments.approve' => 'payments',
            'payments.reject' => 'payments',
            'payments.settings' => 'payments',

            'invoices.view' => 'invoices',

            'roi.view' => 'roi',
            'roi.income_test' => 'roi',

            'level_income.view' => 'level_income',
            'level_income.settings' => 'level_income',

            'wallets.view' => 'wallets',
            'transfers.view' => 'wallets',
            'transfers.reverse' => 'wallets',
            'transfers.settings' => 'wallets',

            'withdrawals.view' => 'withdrawals',
            'withdrawals.approve' => 'withdrawals',
            'withdrawals.reject' => 'withdrawals',
            'withdrawals.complete' => 'withdrawals',
            'withdrawals.settings' => 'withdrawals',

            'rewards.view' => 'rewards',
            'rewards.manage' => 'rewards',

            'reports.view' => 'reports',
            'reports.export' => 'reports',

            'support.view' => 'support',
            'support.manage' => 'support',

            'settings.general' => 'settings',
            'settings.branding' => 'settings',
            'settings.security' => 'settings',
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function normalizeToModuleKeys(array $keys): array
    {
        $aliases = self::legacyAliases();
        $moduleKeySet = array_fill_keys(self::moduleKeys(), true);
        $normalized = [];
        $seen = [];

        foreach ($keys as $key) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }

            if (isset($moduleKeySet[$key])) {
                $module = $key;
            } elseif (isset($aliases[$key])) {
                $module = $aliases[$key];
            } else {
                continue;
            }

            if (! self::isDelegable($module)) {
                continue;
            }

            if (isset($seen[$module])) {
                continue;
            }

            $seen[$module] = true;
            $normalized[] = $module;
        }

        return $normalized;
    }

    public static function isValid(string $permission): bool
    {
        return isset(self::definitions()[$permission]);
    }

    public static function isDelegable(string $permission): bool
    {
        $definition = self::definitions()[$permission] ?? null;

        return $definition !== null && $definition['reserved'] === false;
    }

    public static function isReserved(string $permission): bool
    {
        $definition = self::definitions()[$permission] ?? null;

        return $definition !== null && $definition['reserved'] === true;
    }

    public static function isAlwaysAllowedRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        foreach (self::alwaysAllowedRoutePatterns() as $pattern) {
            if (self::routeMatchesPattern($routeName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function permissionForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if (self::isAlwaysAllowedRoute($routeName)) {
            return null;
        }

        foreach (self::definitions() as $definition) {
            foreach ($definition['routes'] as $pattern) {
                if (! str_ends_with($pattern, '*') && $pattern === $routeName) {
                    return $definition['key'];
                }
            }
        }

        $bestKey = null;
        $bestLength = -1;

        foreach (self::definitions() as $definition) {
            foreach ($definition['routes'] as $pattern) {
                if (! str_ends_with($pattern, '*')) {
                    continue;
                }

                if (! self::routeMatchesPattern($routeName, $pattern)) {
                    continue;
                }

                $length = strlen($pattern);
                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestKey = $definition['key'];
                }
            }
        }

        return $bestKey;
    }

    /**
     * Ordered landing-route candidates for Sub-Admins without dashboard access.
     *
     * @return list<array{permission: string, route: string, params: array<string, mixed>}>
     */
    public static function homeCandidates(): array
    {
        return [
            ['permission' => 'dashboard', 'route' => 'dashboard', 'params' => []],
            ['permission' => 'members', 'route' => 'admin.members.directory', 'params' => []],
            ['permission' => 'tour_requests', 'route' => 'admin.tour-requests.index', 'params' => []],
            ['permission' => 'packages', 'route' => 'admin.packages.index', 'params' => []],
            ['permission' => 'payments', 'route' => 'admin.payments.index', 'params' => ['status' => 'pending']],
            ['permission' => 'invoices', 'route' => 'admin.invoices.index', 'params' => []],
            ['permission' => 'roi', 'route' => 'admin.finance.roi-plans', 'params' => []],
            ['permission' => 'level_income', 'route' => 'admin.finance.level-income', 'params' => []],
            ['permission' => 'wallets', 'route' => 'admin.wallets.overview', 'params' => []],
            ['permission' => 'withdrawals', 'route' => 'admin.withdrawals.overview', 'params' => []],
            ['permission' => 'rewards', 'route' => 'admin.rewards.index', 'params' => []],
            ['permission' => 'reports', 'route' => 'admin.catalog-reports.show', 'params' => ['report' => 'members']],
            ['permission' => 'support', 'route' => 'admin.support.index', 'params' => []],
            ['permission' => 'settings', 'route' => 'admin.settings.general', 'params' => []],
        ];
    }

    /**
     * Delegable permissions grouped for Sub-Admin assignment UI (sidebar modules).
     *
     * @return list<array{
     *     module: string,
     *     group: string,
     *     permissions: list<PermissionDefinition>,
     * }>
     */
    public static function modulesForUi(): array
    {
        $moduleOrder = [
            'Dashboard',
            'Member Management',
            'Tour Requests',
            'Package Management',
            'Payment Management',
            'Invoices',
            'ROI Management',
            'Level Income',
            'Wallet Management',
            'Withdrawals',
            'Rewards',
            'Reports',
            'Support Tickets',
            'Settings',
        ];

        /** @var array<string, array{module: string, group: string, permissions: list<PermissionDefinition>}> $grouped */
        $grouped = [];

        foreach (self::delegableDefinitions() as $definition) {
            $module = $definition['module'];
            if (! isset($grouped[$module])) {
                $grouped[$module] = [
                    'module' => $module,
                    'group' => $definition['group'],
                    'permissions' => [],
                ];
            }

            $grouped[$module]['permissions'][] = $definition;
        }

        foreach ($grouped as $module => $payload) {
            usort(
                $payload['permissions'],
                static fn (array $left, array $right): int => $left['key'] <=> $right['key']
            );
            $grouped[$module]['permissions'] = $payload['permissions'];
        }

        $ordered = [];
        foreach ($moduleOrder as $module) {
            if (isset($grouped[$module])) {
                $ordered[] = $grouped[$module];
                unset($grouped[$module]);
            }
        }

        foreach ($grouped as $remaining) {
            $ordered[] = $remaining;
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function filterDelegableKeys(array $keys): array
    {
        return self::normalizeToModuleKeys($keys);
    }

    /**
     * @return list<string>
     */
    private static function alwaysAllowedRoutePatterns(): array
    {
        return [
            'admin.logout',
            'admin.change-password.*',
            'admin.access-denied',
            'admin.settings.profile',
            'admin.settings.profile.update',
            'admin.settings.profile.password',
            'admin.settings.security',
            'admin.impersonation.stop',
            'admin.coming-soon',
        ];
    }

    private static function routeMatchesPattern(string $route, string $pattern): bool
    {
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($route, substr($pattern, 0, -1));
        }

        return $route === $pattern;
    }

    /**
     * @return array<string, PermissionDefinition>
     */
    private static function buildDefinitions(): array
    {
        $items = [
            self::def(
                key: 'dashboard',
                label: 'Dashboard',
                group: 'Overview',
                module: 'Dashboard',
                routes: ['dashboard'],
            ),

            self::def(
                key: 'members',
                label: 'Member Management',
                group: 'Members',
                module: 'Member Management',
                routes: [
                    'admin.members.*',
                    'admin.genealogy.*',
                    'admin.member-panel',
                    'admin.ranks.*',
                ],
            ),

            self::def(
                key: 'tour_requests',
                label: 'Tour Requests',
                group: 'Commerce',
                module: 'Tour Requests',
                routes: ['admin.tour-requests.*'],
            ),

            self::def(
                key: 'packages',
                label: 'Package Management',
                group: 'Commerce',
                module: 'Package Management',
                routes: [
                    'admin.packages.*',
                    'admin.purchases.*',
                ],
            ),
            self::def(
                key: 'packages.authorize_accrual',
                label: 'Authorize Package Accrual',
                group: 'Commerce',
                module: 'Package Management',
                routes: ['admin.packages.authorize-accrual'],
                reserved: true,
            ),
            self::def(
                key: 'packages.plan_publish',
                label: 'Publish Package Plans',
                group: 'Commerce',
                module: 'Package Management',
                routes: ['admin.packages.plan.publish'],
                reserved: true,
            ),

            self::def(
                key: 'payments',
                label: 'Payment Management',
                group: 'Commerce',
                module: 'Payment Management',
                routes: ['admin.payments.*'],
            ),

            self::def(
                key: 'invoices',
                label: 'Invoices',
                group: 'Commerce',
                module: 'Invoices',
                routes: ['admin.invoices.*'],
            ),

            self::def(
                key: 'roi',
                label: 'ROI Management',
                group: 'Finance',
                module: 'ROI Management',
                routes: [
                    'admin.finance.roi-*',
                    'admin.finance.real-income',
                    'admin.finance.income-test*',
                ],
            ),

            self::def(
                key: 'level_income',
                label: 'Level Income',
                group: 'Finance',
                module: 'Level Income',
                routes: [
                    'admin.finance.level-income',
                    'admin.finance.eligibility',
                    'admin.demo-income',
                    'admin.finance.commission-settings',
                    'admin.finance.level-unlock-rules',
                    'admin.finance.level-income-rules.draft',
                    'admin.finance.level-income-rules.clone',
                ],
            ),
            self::def(
                key: 'level_income.publish',
                label: 'Publish Level Income Rules',
                group: 'Finance',
                module: 'Level Income',
                routes: ['admin.finance.level-income-rules.publish'],
                reserved: true,
            ),
            self::def(
                key: 'level_income.authorize',
                label: 'Authorize Level Income Posting',
                group: 'Finance',
                module: 'Level Income',
                routes: ['admin.finance.level-income-rules.authorize'],
                reserved: true,
            ),
            self::def(
                key: 'level_income.revoke',
                label: 'Revoke Level Income Posting',
                group: 'Finance',
                module: 'Level Income',
                routes: ['admin.finance.level-income-rules.revoke'],
                reserved: true,
            ),
            self::def(
                key: 'level_income.grandfather',
                label: 'Grandfather Level Income Baseline',
                group: 'Finance',
                module: 'Level Income',
                routes: ['admin.finance.level-income-rules.grandfather-capture'],
                reserved: true,
            ),

            self::def(
                key: 'wallets',
                label: 'Wallet Management',
                group: 'Finance',
                module: 'Wallet Management',
                routes: [
                    'admin.wallets.*',
                    'admin.member-transfers.*',
                ],
            ),

            self::def(
                key: 'withdrawals',
                label: 'Withdrawals',
                group: 'Finance',
                module: 'Withdrawals',
                routes: ['admin.withdrawals.*'],
            ),

            self::def(
                key: 'rewards',
                label: 'Rewards',
                group: 'Insights',
                module: 'Rewards',
                routes: ['admin.rewards.*'],
            ),

            self::def(
                key: 'reports',
                label: 'Reports',
                group: 'Insights',
                module: 'Reports',
                routes: ['admin.catalog-reports.*'],
            ),

            self::def(
                key: 'support',
                label: 'Support Tickets',
                group: 'Support',
                module: 'Support Tickets',
                routes: ['admin.support.*'],
            ),

            self::def(
                key: 'settings',
                label: 'Settings',
                group: 'Account',
                module: 'Settings',
                routes: [
                    'admin.settings.general*',
                    'admin.settings.branding*',
                    'admin.settings.security',
                ],
            ),
            self::def(
                key: 'settings.financial',
                label: 'Financial Controls',
                group: 'Account',
                module: 'Settings',
                routes: [
                    'admin.settings.financial',
                    'admin.settings.financial.update',
                ],
                reserved: true,
            ),

            self::def(
                key: 'sub_admins.manage',
                label: 'Manage Sub-Admins',
                group: 'Administration',
                module: 'Sub-Admin Management',
                routes: ['admin.sub-admins.*'],
                reserved: true,
            ),

            self::def(
                key: 'legacy.investments',
                label: 'Legacy Investments',
                group: 'Legacy',
                module: 'Legacy',
                routes: ['admin.investments.*'],
                reserved: true,
            ),
            self::def(
                key: 'legacy.activation_wallet',
                label: 'Legacy Activation Wallet',
                group: 'Legacy',
                module: 'Legacy',
                routes: ['admin.activation-wallet.*'],
                reserved: true,
            ),
            self::def(
                key: 'legacy.reports',
                label: 'Legacy Reports',
                group: 'Legacy',
                module: 'Legacy',
                routes: ['admin.report.*'],
                reserved: true,
            ),
        ];

        $definitions = [];
        foreach ($items as $item) {
            $definitions[$item['key']] = $item;
        }

        return $definitions;
    }

    /**
     * @param  list<string>  $routes
     * @return PermissionDefinition
     */
    private static function def(
        string $key,
        string $label,
        string $group,
        string $module,
        array $routes,
        bool $reserved = false,
        ?string $parent = null,
        ?string $action = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'group' => $group,
            'module' => $module,
            'reserved' => $reserved,
            'routes' => array_values($routes),
            'parent' => $parent,
            'action' => $action,
        ];
    }
}