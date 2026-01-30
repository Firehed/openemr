<?php

/**
 * Integration tests for ApplicationTable::zQuery() and related methods.
 *
 * These tests verify the behavioral contract that callers of zQuery() depend
 * on: iterable results, countable results, current() for first row,
 * getGeneratedValue() for insert IDs, and parameterized queries.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Eric Stern <erics@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Unit\ZendModules\Application\Model;

use Application\Model\ApplicationTable;
use PHPUnit\Framework\TestCase;

class ApplicationTableTest extends TestCase
{
    private ApplicationTable $table;

    protected function setUp(): void
    {
        $this->table = new ApplicationTable();
        // Create a temporary table for DML tests. Temporary tables are
        // session-scoped and automatically dropped when the connection closes.
        $this->table->zQuery(
            "CREATE TEMPORARY TABLE IF NOT EXISTS _test_zquery (
                id INT AUTO_INCREMENT PRIMARY KEY,
                val VARCHAR(100) NOT NULL
            )",
            [],
            false
        );
    }

    protected function tearDown(): void
    {
        $this->table->zQuery("DROP TEMPORARY TABLE IF EXISTS _test_zquery", [], false);
    }

    // ---------------------------------------------------------------
    // SELECT behavior
    // ---------------------------------------------------------------

    public function testSelectReturnsIterableResult(): void
    {
        $result = $this->table->zQuery("SELECT 1 AS n", [], false);
        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row;
        }
        self::assertCount(1, $rows);
        self::assertEquals('1', $rows[0]['n']);
    }

    public function testSelectResultIsCountable(): void
    {
        $result = $this->table->zQuery(
            "SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3",
            [],
            false
        );
        self::assertSame(3, $result->count());
    }

    public function testSelectCurrentReturnsFirstRow(): void
    {
        $result = $this->table->zQuery("SELECT 'hello' AS greeting", [], false);
        $row = $result->current();
        self::assertIsArray($row);
        self::assertEquals('hello', $row['greeting']);
    }

    public function testSelectCurrentReturnsFalseWhenEmpty(): void
    {
        $result = $this->table->zQuery("SELECT 1 WHERE 1=0", [], false);
        self::assertFalse($result->current());
    }

    public function testSelectWithParameterizedQuery(): void
    {
        $result = $this->table->zQuery("SELECT ? AS val", [42], false);
        $row = $result->current();
        self::assertEquals(42, $row['val']);
    }

    public function testSelectMultipleRowsPreservesOrder(): void
    {
        $result = $this->table->zQuery(
            "SELECT 'a' AS letter UNION ALL SELECT 'b' UNION ALL SELECT 'c'",
            [],
            false
        );
        $letters = [];
        foreach ($result as $row) {
            $letters[] = $row['letter'];
        }
        self::assertSame(['a', 'b', 'c'], $letters);
    }

    public function testEmptyResultSetCountIsZero(): void
    {
        $result = $this->table->zQuery("SELECT 1 WHERE 1=0", [], false);
        self::assertSame(0, $result->count());
    }

    public function testResultCanBeIteratedMultipleTimes(): void
    {
        $result = $this->table->zQuery("SELECT 1 AS n UNION ALL SELECT 2", [], false);
        $first = [];
        foreach ($result as $row) {
            $first[] = $row['n'];
        }
        $second = [];
        foreach ($result as $row) {
            $second[] = $row['n'];
        }
        self::assertSame($first, $second);
    }

    // ---------------------------------------------------------------
    // SHOW queries (should behave like SELECT)
    // ---------------------------------------------------------------

    public function testShowQueryReturnsResults(): void
    {
        $result = $this->table->zQuery("SHOW TABLES", [], false);
        // Any functioning OpenEMR DB will have tables
        self::assertGreaterThan(0, $result->count());
    }

    // ---------------------------------------------------------------
    // INSERT behavior
    // ---------------------------------------------------------------

    public function testInsertReturnsGeneratedValue(): void
    {
        $result = $this->table->zQuery(
            "INSERT INTO _test_zquery (val) VALUES (?)",
            ['test_insert'],
            false
        );
        $id = $result->getGeneratedValue();
        self::assertNotEmpty($id);
        self::assertNotFalse($id);
    }

    public function testInsertedRowIsReadable(): void
    {
        $result = $this->table->zQuery(
            "INSERT INTO _test_zquery (val) VALUES (?)",
            ['readable_row'],
            false
        );
        $id = $result->getGeneratedValue();

        $select = $this->table->zQuery(
            "SELECT val FROM _test_zquery WHERE id = ?",
            [$id],
            false
        );
        self::assertEquals('readable_row', $select->current()['val']);
    }

    public function testConsecutiveInsertsReturnIncreasingIds(): void
    {
        $r1 = $this->table->zQuery(
            "INSERT INTO _test_zquery (val) VALUES (?)",
            ['first'],
            false
        );
        $r2 = $this->table->zQuery(
            "INSERT INTO _test_zquery (val) VALUES (?)",
            ['second'],
            false
        );
        self::assertGreaterThan((int)$r1->getGeneratedValue(), (int)$r2->getGeneratedValue());
    }

    // ---------------------------------------------------------------
    // UPDATE behavior
    // ---------------------------------------------------------------

    public function testUpdateModifiesRow(): void
    {
        $this->table->zQuery(
            "INSERT INTO _test_zquery (val) VALUES (?)",
            ['before'],
            false
        );

        $this->table->zQuery(
            "UPDATE _test_zquery SET val = ? WHERE val = ?",
            ['after', 'before'],
            false
        );

        $result = $this->table->zQuery(
            "SELECT val FROM _test_zquery WHERE val = ?",
            ['after'],
            false
        );
        self::assertSame(1, $result->count());
        self::assertEquals('after', $result->current()['val']);
    }

    // ---------------------------------------------------------------
    // DELETE behavior
    // ---------------------------------------------------------------

    public function testDeleteRemovesRow(): void
    {
        $r = $this->table->zQuery(
            "INSERT INTO _test_zquery (val) VALUES (?)",
            ['to_delete'],
            false
        );
        $id = $r->getGeneratedValue();

        $this->table->zQuery(
            "DELETE FROM _test_zquery WHERE id = ?",
            [$id],
            false
        );

        $result = $this->table->zQuery(
            "SELECT * FROM _test_zquery WHERE id = ?",
            [$id],
            false
        );
        self::assertSame(0, $result->count());
    }

    // ---------------------------------------------------------------
    // quoteValue
    // ---------------------------------------------------------------

    public function testQuoteValueEscapesSingleQuotes(): void
    {
        $quoted = $this->table->quoteValue("it's a test");
        // The quoted value should be safe to embed in SQL
        $result = $this->table->zQuery(
            "SELECT {$quoted} AS val",
            [],
            false
        );
        self::assertEquals("it's a test", $result->current()['val']);
    }

    // ---------------------------------------------------------------
    // Error handling
    // ---------------------------------------------------------------

    public function testInvalidSqlReturnsFalse(): void
    {
        ob_start();
        $result = $this->table->zQuery("NOT VALID SQL", [], false, true);
        ob_end_clean();
        self::assertFalse($result);
    }

    // ---------------------------------------------------------------
    // Static helper methods (pure functions, no DB needed)
    // ---------------------------------------------------------------

    public function testDateFormatMapsKnownFormats(): void
    {
        self::assertSame('Y-m-d', ApplicationTable::dateFormat('0'));
        self::assertSame('m/d/Y', ApplicationTable::dateFormat(1));
        self::assertSame('d/m/Y', ApplicationTable::dateFormat(2));
    }

    public function testDateFormatPassesThroughUnknownFormat(): void
    {
        self::assertSame('custom', ApplicationTable::dateFormat('custom'));
    }

    public function testFixDateConvertsFormats(): void
    {
        self::assertSame(
            '01/15/2024',
            ApplicationTable::fixDate('2024-01-15', 1, '0')
        );
    }

    public function testFixDateReturnsFalseForEmptyInput(): void
    {
        self::assertFalse(ApplicationTable::fixDate(''));
        self::assertFalse(ApplicationTable::fixDate(null));
    }

    public function testFixDateHandlesCompactDate(): void
    {
        self::assertSame(
            '2024-03-15',
            ApplicationTable::fixDate('20240315', '0')
        );
    }
}
