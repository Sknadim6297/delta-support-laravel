<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\RootAdminBootstrap;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class RootAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Bootstrap-Secret-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedBootstrapDirectory();
        config(['deltasupport.admin_bootstrap_database' => DB::connection()->getDatabaseName()]);
        $this->removeBootstrapFiles();
    }

    protected function tearDown(): void
    {
        $this->removeBootstrapFiles();
        parent::tearDown();
    }

    public function test_setup_without_a_seal_or_the_exact_gate_is_not_found(): void
    {
        $this->get($this->https('http://localhost/admin/bootstrap'))->assertNotFound();

        $issued = $this->issue();
        $this->secure()->get($this->https('http://localhost/admin/bootstrap/'.str_repeat('a', 43)))
            ->assertNotFound()
            ->assertDontSee('Create Root Admin');

        $this->withServerVariables([
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '203.0.113.11',
        ])->get('http://localhost/admin/bootstrap/'.$issued['gate'])
            ->assertNotFound()
            ->assertDontSee('Create Root Admin');

        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $issued['gate']])))
            ->assertOk();
        $this->assertSame(0, User::query()->count());
    }

    public function test_expired_seal_and_query_string_are_rejected(): void
    {
        $issued = $this->issue();
        $path = app(RootAdminBootstrap::class)->sealPath();
        $seal = json_decode((string) file_get_contents($path), true);
        $seal['expires_at'] = time() - 5;
        file_put_contents($path, json_encode($seal));

        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $issued['gate']])))
            ->assertNotFound();
        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertNotFound();
        $this->assertFileDoesNotExist($path);
        $this->assertSame(0, User::query()->count());

        $replacement = $this->issue();
        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $replacement['gate']])).'?bootstrap_token='.$replacement['token'])
            ->assertNotFound()
            ->assertDontSee($replacement['token']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_existing_admin_cannot_be_replaced(): void
    {
        $existing = User::factory()->create([
            'is_admin' => true,
            'password' => 'Existing-Secret-2026!',
        ]);
        $hash = $existing->password;
        $issued = $this->issue();

        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $issued['gate']])))
            ->assertNotFound();
        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertNotFound()
            ->assertDontSee(self::PASSWORD);

        $existing->refresh();
        $this->assertSame($hash, $existing->password);
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
        $this->assertSame(0, Member::query()->count());
    }

    public function test_invalid_password_backup_phrase_and_token_create_nothing(): void
    {
        $issued = $this->issue();
        $seal = (string) file_get_contents(app(RootAdminBootstrap::class)->sealPath());
        $this->assertStringNotContainsString($issued['token'], $seal);
        $this->assertStringNotContainsString($issued['gate'], $seal);

        $weak = 'NoDigitPass!';
        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), [
            'bootstrap_token' => $issued['token'],
            'email' => 'admin@deltasupport.online',
            'password' => $weak,
            'password_confirmation' => $weak,
            'backup_confirmation' => RootAdminBootstrap::BACKUP_CONFIRMATION,
        ])->assertSessionHasErrors('password');
        $this->assertStringNotContainsString($weak, (string) json_encode(session()->getOldInput()));
        $this->assertSame(0, User::query()->count());

        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), [
            'bootstrap_token' => $issued['token'],
            'email' => 'admin@deltasupport.online',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'backup_confirmation' => 'not-verified',
        ])->assertSessionHasErrors('backup_confirmation');

        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload(str_repeat('c', 43)))
            ->assertSessionHasErrors('bootstrap_token');
        $this->assertStringNotContainsString(str_repeat('c', 43), (string) json_encode(session()->getOldInput()));
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Member::query()->count());
        $this->assertFileExists(app(RootAdminBootstrap::class)->sealPath());
    }

    public function test_post_without_csrf_token_is_rejected(): void
    {
        $issued = $this->issue();
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertStatus(419)
            ->assertDontSee(self::PASSWORD)
            ->assertDontSee($issued['token']);

        $this->assertSame(0, User::query()->count());
        $this->assertFileExists(app(RootAdminBootstrap::class)->sealPath());
    }

    public function test_repeated_attempts_are_rate_limited(): void
    {
        $issued = $this->issue();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->secure('203.0.113.40')->post(
                $this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])),
                $this->payload(str_repeat('d', 43))
            )->assertSessionHasErrors('bootstrap_token');
        }

        $this->secure('203.0.113.40')->post(
            $this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])),
            $this->payload(str_repeat('d', 43))
        )->assertStatus(429);

        $this->assertSame(0, User::query()->count());
    }

    public function test_database_identity_must_match_before_the_form_opens(): void
    {
        $issued = $this->issue();
        $path = app(RootAdminBootstrap::class)->sealPath();
        $seal = json_decode((string) file_get_contents($path), true);
        $seal['database_name'] = 'Delta_support_db';
        file_put_contents($path, json_encode($seal));
        config(['deltasupport.admin_bootstrap_database' => 'Delta_support_db']);

        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $issued['gate']])))
            ->assertNotFound();
        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertNotFound();
        $this->assertSame(0, User::query()->count());
        $this->assertFinancialFlagsStayOff();
    }

    public function test_successful_provision_is_one_time_and_logs_in_as_st666666(): void
    {
        $issued = $this->issue();
        $leaked = [];
        Log::listen(function ($message) use (&$leaked, $issued): void {
            $text = (string) $message->message;
            if (str_contains($text, self::PASSWORD) || str_contains($text, $issued['token'])) {
                $leaked[] = $text;
            }
        });

        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $issued['gate']])))
            ->assertOk()
            ->assertSee('ST666666')
            ->assertSee(RootAdminBootstrap::BACKUP_CONFIRMATION)
            ->assertDontSee($issued['token'])
            ->assertDontSee(self::PASSWORD);

        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertRedirect(route('login'))
            ->assertDontSee(self::PASSWORD)
            ->assertDontSee($issued['token']);

        $admin = User::query()->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->assertNotSame(self::PASSWORD, $admin->password);
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
        $this->assertSame(0, Member::query()->count());
        $this->assertFinancialFlagsStayOff();
        $this->assertSame(0, DB::table('package_purchases')->count());
        $this->assertSame([], $leaked);

        $log = AdminActivityLog::query()->firstOrFail();
        $this->assertSame('admin_provisioned', $log->action);
        $this->assertSame('ST666666', $log->subject);
        $this->assertSame(['email' => 'admin@deltasupport.online'], $log->filters);
        $this->assertStringNotContainsString(self::PASSWORD, (string) json_encode($log->filters));

        $consumed = (string) file_get_contents(app(RootAdminBootstrap::class)->consumedPath());
        $this->assertStringNotContainsString(self::PASSWORD, $consumed);
        $this->assertStringNotContainsString($issued['token'], $consumed);
        $this->assertStringNotContainsString($issued['gate'], $consumed);
        $this->assertFileDoesNotExist(app(RootAdminBootstrap::class)->sealPath());

        $this->secure()->get($this->https(route('admin.bootstrap.create', ['gate' => $issued['gate']])))->assertNotFound();
        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertNotFound();
        $this->assertSame($admin->password, User::query()->firstOrFail()->password);

        file_put_contents(app(RootAdminBootstrap::class)->sealPath(), json_encode([
            'version' => 1,
            'token_hash' => RootAdminBootstrap::hashSecret($issued['token']),
            'gate_hash' => RootAdminBootstrap::hashSecret($issued['gate']),
            'database_name' => DB::connection()->getDatabaseName(),
            'expires_at' => time() + 60,
        ]));
        $this->secure()->post($this->https(route('admin.bootstrap.store', ['gate' => $issued['gate']])), $this->payload($issued['token']))
            ->assertNotFound();
        $this->assertSame(1, User::query()->count());

        try {
            app(RootAdminBootstrap::class)->issue((string) DB::connection()->getDatabaseName());
            $this->fail('A consumed bootstrap accepted another seal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('closed', $exception->getMessage());
        }

        $this->post(route('login.submit'), [
            'member_id' => 'ST666666',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.change-password.index'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertRedirect(route('admin.change-password.index'));
        $this->get(route('admin.change-password.index'))->assertOk()->assertSeeText('Change Password');
    }

    public function test_seal_command_refuses_a_non_interactive_console_and_a_protected_database(): void
    {
        config(['deltasupport.admin_bootstrap_database' => 'Delta_support_db']);

        $this->artisan('admin:bootstrap-seal', [
            '--database' => 'Delta_support_db',
        ])->assertFailed()
            ->expectsOutputToContain('interactive');
        $this->assertFileDoesNotExist(app(RootAdminBootstrap::class)->sealPath());

        config(['deltasupport.admin_bootstrap_database' => 'delta_support']);
        $this->artisan('admin:bootstrap-seal', [
            '--database' => 'delta_support',
        ])->assertFailed()
            ->expectsOutputToContain('protected');

        try {
            app(RootAdminBootstrap::class)->issue('delta_support');
            $this->fail('A protected database accepted a seal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('protected', $exception->getMessage());
        }

        $this->assertFileDoesNotExist(app(RootAdminBootstrap::class)->sealPath());
        $this->assertSame(0, User::query()->count());
        $this->assertFinancialFlagsStayOff();
    }

    private function assertFinancialFlagsStayOff(): void
    {
        $row = DB::table('financial_controls')->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->financial_automation_armed);
        $this->assertSame(0, (int) $row->financial_emergency_stop);
        $this->assertSame(0, (int) $row->roi_calculation_enabled);
        $this->assertSame(0, (int) $row->roi_wallet_crediting_enabled);
        $this->assertSame(0, (int) $row->level_calculation_enabled);
        $this->assertSame(0, (int) $row->level_wallet_crediting_enabled);
        $this->assertSame(0, (int) $row->daily_financial_cycle_enabled);
        $this->assertSame(0, (int) $row->withdrawal_requests_enabled);
        $this->assertSame(0, (int) $row->external_payouts_enabled);
    }

    /**
     * @return array{token: string, gate: string}
     */
    private function issue(): array
    {
        return app(RootAdminBootstrap::class)->issue((string) DB::connection()->getDatabaseName());
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $token): array
    {
        return [
            'bootstrap_token' => $token,
            'email' => 'admin@deltasupport.online',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'backup_confirmation' => RootAdminBootstrap::BACKUP_CONFIRMATION,
        ];
    }

    private function secure(string $ip = '203.0.113.10'): static
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
        ]);
    }

    private function https(string $uri): string
    {
        return preg_replace('#^http://#', 'https://', $uri) ?? $uri;
    }

    private function useIsolatedBootstrapDirectory(): void
    {
        config(['deltasupport.admin_bootstrap_directory' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'delta-support-root-admin-tests']);
    }

    private function removeBootstrapFiles(): void
    {
        $this->useIsolatedBootstrapDirectory();
        $bootstrap = app(RootAdminBootstrap::class);
        foreach ([$bootstrap->sealPath(), $bootstrap->sealPath().'.tmp', $bootstrap->consumedPath(), $bootstrap->consumedPath().'.tmp'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
