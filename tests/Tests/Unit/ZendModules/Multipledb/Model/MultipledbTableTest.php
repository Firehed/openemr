<?php

/**
 * Integration tests for MultipledbTable CRUD operations.
 *
 * These tests verify the behavioral contract of MultipledbTable against the
 * real multiple_db table. Each test cleans up after itself in tearDown.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Eric Stern <erics@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Unit\ZendModules\Multipledb\Model;

use Application\Model\ApplicationTable;
use Multipledb\Model\MultipledbTable;
use PHPUnit\Framework\TestCase;

// The Multipledb module is loaded dynamically by the Laminas ModuleManager
// when enabled in the database. Register its autoloader path so tests can
// instantiate its classes without requiring the full module to be active.
spl_autoload_register(function (string $class): void {
    $prefix = 'Multipledb\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $base = $GLOBALS['fileroot'] ?? $GLOBALS['webserver_root'] ?? dirname(__DIR__, 6);
        $file = $base . '/interface/modules/zend_modules/module/Multipledb/src/Multipledb/' . $relative . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

class MultipledbTableTest extends TestCase
{
    private MultipledbTable $table;
    private ApplicationTable $appTable;

    /** @var list<int> IDs of rows inserted during this test, for cleanup */
    private array $insertedIds = [];

    protected function setUp(): void
    {
        $this->table = new MultipledbTable();
        $this->appTable = new ApplicationTable();
        $this->insertedIds = [];

        // Ensure $_SESSION is available for checknamespace()
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->insertedIds as $id) {
            $this->appTable->zQuery(
                "DELETE FROM multiple_db WHERE id = ?",
                [$id],
                false
            );
        }
    }

    private function insertTestRow(string $namespace = 'test_ns'): int
    {
        $this->table->storeMultipledb(0, [
            'namespace' => $namespace,
            'host' => '127.0.0.1',
            'dbname' => 'test_db',
            'username' => 'test_user',
            'port' => 3306,
            'password' => '',
        ]);

        // Find the row we just inserted
        $result = $this->appTable->zQuery(
            "SELECT MAX(id) AS id FROM multiple_db WHERE namespace = ?",
            [$namespace],
            false
        );
        $id = (int) $result->current()['id'];
        $this->insertedIds[] = $id;
        return $id;
    }

    // ---------------------------------------------------------------
    // fetchAll
    // ---------------------------------------------------------------

    public function testFetchAllReturnsArray(): void
    {
        $result = $this->table->fetchAll();
        self::assertIsArray($result);
    }

    public function testFetchAllIncludesInsertedRow(): void
    {
        $id = $this->insertTestRow('fetchall_test_ns');
        $rows = $this->table->fetchAll();

        $found = false;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $id) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, "Inserted row with id=$id should appear in fetchAll()");
    }

    // ---------------------------------------------------------------
    // getMultipledbById
    // ---------------------------------------------------------------

    public function testGetMultipledbByIdReturnsRow(): void
    {
        $id = $this->insertTestRow('getbyid_ns');
        $row = $this->table->getMultipledbById($id);

        self::assertIsArray($row);
        self::assertEquals($id, $row['id']);
        self::assertEquals('getbyid_ns', $row['namespace']);
    }

    public function testGetMultipledbByIdReturnsNullForMissing(): void
    {
        $result = $this->table->getMultipledbById(999999999);
        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // storeMultipledb (insert)
    // ---------------------------------------------------------------

    public function testStoreMultipledbInsertsNewRow(): void
    {
        $countBefore = $this->countAllRows();
        $this->insertTestRow('store_insert_ns');
        $countAfter = $this->countAllRows();

        self::assertSame($countBefore + 1, $countAfter);
    }

    // ---------------------------------------------------------------
    // storeMultipledb (update)
    // ---------------------------------------------------------------

    public function testStoreMultipledbUpdatesExistingRow(): void
    {
        $id = $this->insertTestRow('before_update_ns');

        $this->table->storeMultipledb($id, [
            'namespace' => 'after_update_ns',
            'host' => '10.0.0.1',
            'password' => '',
        ]);

        $row = $this->table->getMultipledbById($id);
        self::assertEquals('after_update_ns', $row['namespace']);
        self::assertEquals('10.0.0.1', $row['host']);
    }

    // ---------------------------------------------------------------
    // deleteMultidbById
    // ---------------------------------------------------------------

    public function testDeleteMultidbByIdRemovesRow(): void
    {
        $id = $this->insertTestRow('to_delete_ns');
        self::assertIsArray($this->table->getMultipledbById($id));

        $this->table->deleteMultidbById($id);
        // Remove from cleanup list since it's already deleted
        $this->insertedIds = array_filter(
            $this->insertedIds,
            fn($v) => $v !== $id
        );

        self::assertNull($this->table->getMultipledbById($id));
    }

    // ---------------------------------------------------------------
    // checknamespace
    // ---------------------------------------------------------------

    public function testChecknamespaceFindsDuplicate(): void
    {
        $this->insertTestRow('dup_check_ns');
        $_SESSION['multiple_edit_id'] = 0;

        $result = $this->table->checknamespace('dup_check_ns');
        self::assertSame(1, $result);
    }

    public function testChecknamespaceReturnsZeroWhenNotFound(): void
    {
        $_SESSION['multiple_edit_id'] = 0;
        $result = $this->table->checknamespace('nonexistent_ns_' . uniqid());
        self::assertSame(0, $result);
    }

    // ---------------------------------------------------------------
    // randomSafeKey
    // ---------------------------------------------------------------

    public function testRandomSafeKeyReturns32Chars(): void
    {
        $key = $this->table->randomSafeKey();
        self::assertSame(32, strlen($key));
    }

    public function testRandomSafeKeyProducesDifferentValues(): void
    {
        $a = $this->table->randomSafeKey();
        $b = $this->table->randomSafeKey();
        self::assertNotSame($a, $b);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function countAllRows(): int
    {
        $result = $this->appTable->zQuery(
            "SELECT COUNT(*) AS cnt FROM multiple_db",
            [],
            false
        );
        return (int) $result->current()['cnt'];
    }
}
