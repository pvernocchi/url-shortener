<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migration;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            foreach (glob($dir . '/*.sql') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->tempDirs = [];
    }

    public function testRunningTwiceExecutesEachFileOnlyOnce(): void
    {
        Config::set('db.prefix', 't_');
        $path = $this->makeMigrationsDir([
            '001_first.sql'  => "CREATE TABLE `{prefix}one` (`id` INT);\n",
            '002_second.sql' => "CREATE TABLE `{prefix}two` (`id` INT);\n",
        ]);

        $pdoStmt = $this->createMock(\PDOStatement::class);
        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query', 'fetchAll', 'insert'])
            ->getMock();

        $db->method('query')->willReturn($pdoStmt);
        $db->method('fetchAll')->willReturnOnConsecutiveCalls(
            [],
            [['filename' => '001_first.sql'], ['filename' => '002_second.sql']]
        );
        $db->expects($this->exactly(2))
            ->method('insert')
            ->with(
                't_migrations',
                $this->callback(fn(array $row): bool => isset($row['filename'], $row['ran_at']))
            )
            ->willReturn(1);

        $migration = new Migration();
        $firstRun  = $migration->run($db, $path);
        $secondRun = $migration->run($db, $path);

        $this->assertSame(['001_first.sql', '002_second.sql'], $firstRun);
        $this->assertSame([], $secondRun);
    }

    public function testPrefixPlaceholderIsReplaced(): void
    {
        Config::set('db.prefix', 'pref_');
        $path = $this->makeMigrationsDir([
            '001_prefix.sql' => "CREATE TABLE `{prefix}links` (`id` INT);\n",
        ]);

        $pdoStmt = $this->createMock(\PDOStatement::class);
        $capturedSql = [];

        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query', 'fetchAll', 'insert'])
            ->getMock();

        $db->method('query')->willReturnCallback(function (string $sql) use (&$capturedSql, $pdoStmt): \PDOStatement {
            $capturedSql[] = $sql;
            return $pdoStmt;
        });
        $db->method('fetchAll')->willReturn([]);
        $db->method('insert')->willReturn(1);

        (new Migration())->run($db, $path);

        $this->assertTrue(
            (bool)array_filter($capturedSql, fn(string $sql): bool => str_contains($sql, 'pref_links'))
        );
    }

    public function testMalformedSqlFileThrows(): void
    {
        Config::set('db.prefix', 'x_');
        $path = $this->makeMigrationsDir([
            '001_bad.sql' => "MALFORMED SQL;\n",
        ]);

        $pdoStmt = $this->createMock(\PDOStatement::class);
        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query', 'fetchAll', 'insert'])
            ->getMock();

        $db->method('query')->willReturnCallback(function (string $sql) use ($pdoStmt): \PDOStatement {
            if (str_contains($sql, 'MALFORMED')) {
                throw new \RuntimeException('Syntax error');
            }
            return $pdoStmt;
        });
        $db->method('fetchAll')->willReturn([]);
        $db->expects($this->never())->method('insert');

        $this->expectException(\RuntimeException::class);
        (new Migration())->run($db, $path);
    }

    /**
     * @param array<string, string> $files
     */
    private function makeMigrationsDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/migration-test-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;

        foreach ($files as $filename => $content) {
            file_put_contents($dir . '/' . $filename, $content);
        }

        return $dir;
    }
}
