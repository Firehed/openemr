<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Eric Stern <erics@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\BC\Database;

use OpenEMR\BC\Database\ZQueryResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZQueryResult::class)]
#[Small]
class ZQueryResultTest extends TestCase
{
    public function testCountReturnsNumberOfRows(): void
    {
        $result = new ZQueryResult([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ]);
        self::assertCount(3, $result);
    }

    public function testCountReturnsZeroWhenEmpty(): void
    {
        $result = new ZQueryResult([]);
        self::assertCount(0, $result);
    }

    public function testCurrentReturnsFirstRow(): void
    {
        $result = new ZQueryResult([
            ['name' => 'first'],
            ['name' => 'second'],
        ]);
        self::assertSame(['name' => 'first'], $result->current());
    }

    public function testCurrentReturnsNullWhenEmpty(): void
    {
        $result = new ZQueryResult([]);
        self::assertNull($result->current());
    }

    public function testIterationYieldsAllRows(): void
    {
        $rows = [
            ['id' => 1, 'val' => 'a'],
            ['id' => 2, 'val' => 'b'],
        ];
        $result = new ZQueryResult($rows);

        $collected = [];
        foreach ($result as $row) {
            $collected[] = $row;
        }
        self::assertSame($rows, $collected);
    }

    public function testIterationOverEmptyResultYieldsNothing(): void
    {
        $result = new ZQueryResult([]);
        $collected = [];
        foreach ($result as $row) {
            $collected[] = $row;
        }
        self::assertSame([], $collected);
    }

    public function testGetGeneratedValueReturnsLastInsertId(): void
    {
        $result = new ZQueryResult([], 42);
        self::assertSame(42, $result->getGeneratedValue());
    }

    public function testGetGeneratedValueReturnsStringId(): void
    {
        $result = new ZQueryResult([], '123');
        self::assertSame('123', $result->getGeneratedValue());
    }

    public function testGetGeneratedValueReturnsNullByDefault(): void
    {
        $result = new ZQueryResult([['id' => 1]]);
        self::assertNull($result->getGeneratedValue());
    }

    public function testCanBeIteratedMultipleTimes(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $result = new ZQueryResult($rows);

        $first = [];
        foreach ($result as $row) {
            $first[] = $row;
        }
        $second = [];
        foreach ($result as $row) {
            $second[] = $row;
        }
        self::assertSame($first, $second);
    }
}
