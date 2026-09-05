<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Matchmaker\Core\DBMigrator;

final class DBMigratorTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset globals before each test
        $GLOBALS['__mm_dbdelta_sql'] = null;
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['wpdb'] = new Fakewpdb();
        // Ensure fresh singleton instance by resetting static property via reflection
        $ref = new ReflectionClass(DBMigrator::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null);
    }

    public function test_maybe_migrate_creates_tables_and_updates_option(): void
    {
        $migrator = DBMigrator::instance();
        $migrator->maybe_migrate();

        $this->assertNotNull($GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString($GLOBALS['wpdb']->prefix . 'matchmaking_pool', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString($GLOBALS['wpdb']->prefix . 'matches', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString($GLOBALS['wpdb']->prefix . 'matchmaker_logs', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertEquals('2.5.0', $GLOBALS['__mm_options']['mm_matchmaking_db_v2_version']);
    }

    public function test_maybe_migrate_skips_when_already_installed(): void
    {
        $GLOBALS['__mm_options']['mm_matchmaking_db_v2_version'] = '2.5.0';
        $migrator = DBMigrator::instance();
        $migrator->maybe_migrate();

        $this->assertNull($GLOBALS['__mm_dbdelta_sql']);
    }
}
