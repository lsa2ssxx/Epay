<?php

use PHPUnit\Framework\TestCase;

final class DatabaseIntegrationTest extends TestCase
{
    /** @var PDO */
    private $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('EPAY_TEST_DSN');
        if (!$dsn) {
            self::markTestSkipped('未配置 EPAY_TEST_DSN，跳过 MariaDB 集成测试');
        }
        $this->pdo = new PDO(
            $dsn,
            getenv('EPAY_TEST_DB_USER') ?: 'root',
            getenv('EPAY_TEST_DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    protected function tearDown(): void
    {
        if (!$this->pdo) return;
        $this->pdo->exec('DROP TABLE IF EXISTS epay_test_order');
        $this->pdo->exec('DROP TABLE IF EXISTS epay_test_plugin');
        $tables = $this->pdo->query("SHOW TABLES LIKE 'epay\\_install\\_%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
        }
    }

    public function testPaymentStatusGateCanSettleOnlyOnce(): void
    {
        $this->pdo->exec(
            'CREATE TABLE epay_test_order ('.
            'trade_no char(19) PRIMARY KEY, status tinyint(1) NOT NULL'.
            ') ENGINE=InnoDB'
        );
        $this->pdo->exec("INSERT INTO epay_test_order VALUES ('2026072800000000001', 0)");

        $sql = "UPDATE epay_test_order SET status=1 WHERE trade_no='2026072800000000001' AND status IN (0,4)";
        self::assertSame(1, $this->pdo->exec($sql));
        self::assertSame(0, $this->pdo->exec($sql));
    }

    public function testUpdate6ExpandsPluginTypeColumns(): void
    {
        $this->pdo->exec(
            'CREATE TABLE epay_test_plugin ('.
            'name varchar(30) PRIMARY KEY, types varchar(255), transtypes varchar(50)'.
            ') ENGINE=InnoDB'
        );
        $sql = file_get_contents(EPAY_TEST_ROOT.'/install/update6.sql');
        self::assertNotFalse($sql);
        $sql = str_replace('pre_plugin', 'epay_test_plugin', $sql);
        $this->pdo->exec($sql);

        $statement = $this->pdo->query(
            "SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS ".
            "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='epay_test_plugin' ".
            "AND COLUMN_NAME IN ('types','transtypes')"
        );
        $lengths = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame('500', (string)$lengths['types']);
        self::assertSame('500', (string)$lengths['transtypes']);
    }

    public function testMigrationRunnerUpdatesVersionOnlyAfterAllMigrations(): void
    {
        $source = file_get_contents(EPAY_TEST_ROOT.'/install/update.php');
        self::assertNotFalse($source);
        $migrationLoop = strpos($source, 'foreach($migrationFiles as $migrationFile)');
        $versionUpdate = strpos($source, '$versionSql =');
        self::assertNotFalse($migrationLoop);
        self::assertNotFalse($versionUpdate);
        self::assertGreaterThan($migrationLoop, $versionUpdate);
        self::assertStringContainsString('版本号未更新', $source);
    }

    public function testFreshInstallSchemaIsExecutableAndUsesCurrentVersion(): void
    {
        $sql = file_get_contents(EPAY_TEST_ROOT.'/install/install.sql');
        self::assertNotFalse($sql);
        $sql = str_replace('pre_', 'epay_install_', $sql);
        $this->pdo->exec($sql);

        self::assertSame(
            '2058',
            (string)$this->pdo->query(
                "SELECT v FROM epay_install_config WHERE k='version'"
            )->fetchColumn()
        );
        $length = $this->pdo->query(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS ".
            "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='epay_install_plugin' AND COLUMN_NAME='types'"
        )->fetchColumn();
        self::assertSame('500', (string)$length);
    }
}
