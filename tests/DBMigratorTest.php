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
        $this->assertStringContainsString($GLOBALS['wpdb']->prefix . 'matchmaker_event_clicks', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString('has_one_on_one', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString('is_parent_applying', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString('pref_country longtext', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString('pref_city longtext', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString('user_one_rejection_reason', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString('user_two_rejection_reason', $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString("'archived'", $GLOBALS['__mm_dbdelta_sql']);
        $this->assertStringContainsString("'featured'", $GLOBALS['__mm_dbdelta_sql']);
        $this->assertEquals('2.14.0', $GLOBALS['__mm_options']['mm_matchmaking_db_v2_version']);
    }

    public function test_maybe_migrate_skips_when_already_installed(): void
    {
        $GLOBALS['__mm_options']['mm_matchmaking_db_v2_version'] = '2.14.0';
        $migrator = DBMigrator::instance();
        $migrator->maybe_migrate();

        $this->assertNull($GLOBALS['__mm_dbdelta_sql']);
    }

    public function test_maybe_migrate_heals_latin1_wp_users_collation(): void
    {
        $GLOBALS['wpdb']->mock_results["SHOW FULL COLUMNS FROM {$GLOBALS['wpdb']->users}"] = [
            (object) ['Field' => 'user_login', 'Collation' => 'latin1_swedish_ci'],
            (object) ['Field' => 'user_nicename', 'Collation' => 'latin1_swedish_ci'],
            (object) ['Field' => 'display_name', 'Collation' => 'latin1_swedish_ci'],
            (object) ['Field' => 'user_email', 'Collation' => 'latin1_swedish_ci'],
        ];

        $migrator = DBMigrator::instance();
        $migrator->maybe_migrate();

        $found_alter = false;
        foreach ($GLOBALS['wpdb']->queries as $q) {
            if (str_contains($q, "ALTER TABLE {$GLOBALS['wpdb']->users}") && str_contains($q, 'user_login')) {
                $found_alter = true;
                break;
            }
        }

        $this->assertTrue($found_alter, 'Expected ALTER TABLE on wp_users to heal latin1 collation');
    }
}
