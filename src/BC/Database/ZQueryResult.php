<?php

/**
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Eric Stern <erics@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\BC\Database;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Backwards-compatible wrapper returned by ApplicationTable::zQuery().
 *
 * Callers expect the result to be iterable (foreach), countable, and to
 * support ->current() and ->getGeneratedValue(). This class satisfies all
 * of those contracts using eagerly-fetched associative rows from DBAL.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
class ZQueryResult implements IteratorAggregate, Countable
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    private string|int|null $lastInsertId;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string|int|null $lastInsertId
     */
    public function __construct(array $rows, string|int|null $lastInsertId = null)
    {
        $this->rows = $rows;
        $this->lastInsertId = $lastInsertId;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * Returns the first row, or null if empty.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        return $this->rows[0] ?? null;
    }

    /**
     * Returns the last insert ID captured at query time.
     *
     * @return string|int|null
     */
    public function getGeneratedValue(): string|int|null
    {
        return $this->lastInsertId;
    }
}
