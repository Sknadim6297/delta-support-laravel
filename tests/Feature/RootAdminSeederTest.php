<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\RootAdminBootstrap;
use App\Services\RootAdminSeed;
use App\Services\RootAdminSeedFile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RootAdminSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RootAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Bootstrap-Secret-2026!';

    private const EMAIL = 'admin@deltasupport.online';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedBootstrapDirectory();
        config(['deltasupport.admin_bootstrap_database' => DB::connection()->getDatabaseName()]);
        $this->removeSeedFile();
    }

    protected function tearDown(): void
    {
        $this->removeSeedFile();
        parent::tearDown();
    }

    public function test_seeder_creates_one_admin_from_a_hash_and_then_closes(): void
    {
        $hash = Hash::make(self::PASSWORD);
        $this->writeFile($hash);

        $this->artisan('db:seed', [
            '--class' => 'RootAdminSeeder',
            '--force' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('ST666666')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->doesntExpectOutputToContain($hash);

        $admin = User::query()->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->must_change_password);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame(self::EMAIL, $admin->email);
        $this->assertSame($hash, $admin->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, DB::table('package_purchases')->count());
        $this->assertFinancialFlagsStayOff();
        $this->assertFileDoesNotExist(app(RootAdminSeedFile::class)->path());
        $this->assertFileExists(app(RootAdminBootstrap::class)->consumedPath());

        $log = AdminActivityLog::query()->firstOrFail();
        $this->assertSame('admin_provisioned', $log->action);
        $this->assertSame('ST666666', $log->subject);
        $this->assertSame(['email' => self::EMAIL], $log->filters);
        $encodedLog = (string) json_encode($log->filters);
        $this->assertStringNotContainsString(self::PASSWORD, $encodedLog);
        $this->assertStringNotContainsString($hash, $encodedLog);

        $this->post(route('login.submit'), [
            'member_id' => 'ST666666',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.change-password.index'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertRedirect(route('admin.change-password.index'));

        $replacement = Hash::make('Another-Secret-2026!');
        $this->writeFile($replacement);
        $this->expectSeedRefusal('closed');
        $admin->refresh();
        $this->assertSame($hash, $admin->password);
        $this->assertSame(1, User::query()->count());

        $admin->delete();
        $this->writeFile($replacement);
        $this->expectSeedRefusal('closed');
        $this->assertSame(0, User::query()->count());
        $this->assertFileDoesNotExist(app(RootAdminSeedFile::class)->path());
    }

    public function test_duplicate_admin_is_not_replaced(): void
    {
        $existing = User::factory()->create([
            'is_admin' => true,
            'password' => 'Existing-Secret-2026!',
        ]);
        $stored = $existing->password;
        $this->writeFile(Hash::make(self::PASSWORD));

        $this->expectSeedRefusal('already exists');

        $existing->refresh();
        $this->assertSame($stored, $existing->password);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
        $this->assertFileDoesNotExist(app(RootAdminSeedFile::class)->path());
    }

    public function test_wrong_database_protected_database_and_invalid_hash_are_refused(): void
    {
        $hash = Hash::make(self::PASSWORD);
        $this->writeFile($hash);
        config(['deltasupport.admin_bootstrap_database' => 'Delta_support_db']);

        $this->expectSeedRefusal('Delta_support_db');
        $this->assertSame(0, User::query()->count());
        $this->assertFileExists(app(RootAdminSeedFile::class)->path());
        $this->assertFinancialFlagsStayOff();

        config(['deltasupport.admin_bootstrap_database' => 'delta_support']);
        $this->writeFile($hash);
        $this->expectSeedRefusal('protected');
        $this->assertSame(0, User::query()->count());

        config(['deltasupport.admin_bootstrap_database' => DB::connection()->getDatabaseName()]);
        $this->writeFile(self::PASSWORD);
        $this->expectSeedRefusal('not valid');
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Member::query()->count());
    }

    public function test_mariadb_case_folding_and_configured_identity_stay_separate_from_a_missing_file(): void
    {
        $seed = app(RootAdminSeed::class);

        $this->assertNull($seed->identityRefusal('mariadb', 'Delta_support_db', 'delta_support_db', 'Delta_support_db'));
        $this->assertNull($seed->identityRefusal('mysql', 'delta_support_db', 'DELTA_SUPPORT_DB', 'Delta_support_db'));
        $this->assertStringContainsString(
            'does not match the connected schema',
            (string) $seed->identityRefusal('mysql', 'Delta_support_db', 'delta_support', 'Delta_support_db')
        );
        $this->assertStringContainsString(
            'could not be verified',
            (string) $seed->identityRefusal('mysql', 'Delta_support_db', '', 'Delta_support_db')
        );
        $this->assertStringContainsString(
            'protected',
            (string) $seed->identityRefusal('mysql', 'delta_support', 'delta_support', 'Delta_support_db')
        );
        $this->assertNotNull($seed->identityRefusal('sqlite', 'Delta_support_db', 'delta_support_db', 'Delta_support_db'));

        config(['deltasupport.admin_bootstrap_database' => null]);
        $this->assertSame('Delta_support_db', $seed->approvedDatabaseName());

        config(['deltasupport.admin_bootstrap_database' => DB::connection()->getDatabaseName()]);
        $this->expectSeedRefusal('root admin file is missing');

        $this->writeFile(Hash::make(self::PASSWORD));
        config(['deltasupport.admin_bootstrap_database' => 'Delta_support_db']);
        $this->expectSeedRefusal('is not Delta_support_db');
        $this->assertSame(0, User::query()->count());
        $this->assertFileExists(app(RootAdminSeedFile::class)->path());
    }

    public function test_missing_admin_column_and_database_seeder_do_not_create_an_admin(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
        $this->writeFile(Hash::make(self::PASSWORD));

        $this->expectSeedRefusal('admin columns');
        $this->assertSame(0, User::query()->count());

        $this->removeSeedFile();
        $this->writeFile(Hash::make(self::PASSWORD));
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, User::query()->where('is_admin', true)->count());
        $this->assertFileExists(app(RootAdminSeedFile::class)->path());
    }

    public function test_hash_file_command_refuses_a_non_interactive_console(): void
    {
        $password = self::PASSWORD;
        putenv('ROOT_ADMIN_PASSWORD='.$password);
        $_ENV['ROOT_ADMIN_PASSWORD'] = $password;
        $_SERVER['ROOT_ADMIN_PASSWORD'] = $password;

        try {
            $this->artisan('admin:root-admin-file', [
                '--email' => self::EMAIL,
            ])->assertFailed()
                ->expectsOutputToContain('interactive')
                ->doesntExpectOutputToContain($password);
        } finally {
            putenv('ROOT_ADMIN_PASSWORD');
            unset($_ENV['ROOT_ADMIN_PASSWORD'], $_SERVER['ROOT_ADMIN_PASSWORD']);
        }

        $this->assertFileDoesNotExist(app(RootAdminSeedFile::class)->path());
        $this->assertSame(0, User::query()->count());
    }

    private function expectSeedRefusal(string $fragment): void
    {
        try {
            $this->seed(RootAdminSeeder::class);
            $this->fail('The root admin seeder created or changed an account.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($fragment, $exception->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }
    }

    private function writeFile(string $passwordHash): void
    {
        app(RootAdminSeedFile::class)->write('Delta Support Admin', self::EMAIL, $passwordHash);
    }

    private function useIsolatedBootstrapDirectory(): void
    {
        config(['deltasupport.admin_bootstrap_directory' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'delta-support-root-admin-tests']);
    }

    private function removeSeedFile(): void
    {
        $this->useIsolatedBootstrapDirectory();
        app(RootAdminSeedFile::class)->delete();
        $bootstrap = app(RootAdminBootstrap::class);
        foreach ([$bootstrap->sealPath(), $bootstrap->consumedPath()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function assertFinancialFlagsStayOff(): void
    {
        $row = DB::table('financial_controls')->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->financial_automation_armed);
        $this->assertSame(0, (int) $row->financial_emergency_stop);
        $this->assertSame(0, (int) $row->roi_calculation_enabled);
        $this->assertSame(0, (int) $row->level_calculation_enabled);
        $this->assertSame(0, (int) $row->withdrawal_requests_enabled);
        $this->assertSame(0, (int) $row->external_payouts_enabled);
    }
}
