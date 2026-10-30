<?php

namespace Tests\Unit;

use App\Services\ProductionDatabaseCommandGuard;
use App\Services\ProductionDatabaseTarget;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ProductionDatabaseCommandGuardTest extends TestCase
{
    public function test_production_blocks_destructive_database_commands(): void
    {
        $this->app->instance('env', 'production');
        $event = new CommandStarting('migrate:fresh', new ArrayInput([]), new BufferedOutput());

        $this->expectException(RuntimeException::class);
        app(ProductionDatabaseCommandGuard::class)->handle($event);
    }

    public function test_production_allows_normal_migrations(): void
    {
        $this->app->instance('env', 'production');
        $event = new CommandStarting('migrate', new ArrayInput([]), new BufferedOutput());

        app(ProductionDatabaseCommandGuard::class)->handle($event);

        $this->assertTrue(true);
    }

    public function test_protected_names_cover_the_development_and_income_test_databases(): void
    {
        $targets = new ProductionDatabaseTarget;

        $this->assertTrue($targets->isProtected('delta_support'));
        $this->assertTrue($targets->isProtected('delta_support_income_test'));
        $this->assertFalse($targets->isProtected('delta_support_production'));
    }

    public function test_local_destructive_commands_stop_for_the_development_database(): void
    {
        $this->app->instance('env', 'local');
        $connection = \Mockery::mock();
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('delta_support');
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $event = new CommandStarting('migrate:fresh', new ArrayInput([]), new BufferedOutput());

        $this->expectException(RuntimeException::class);
        app(ProductionDatabaseCommandGuard::class)->handle($event);
    }
}