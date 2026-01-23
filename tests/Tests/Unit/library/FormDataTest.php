<?php

declare(strict_types=1);

namespace OpenEMR\Tests\Unit\library;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class FormDataTest extends TestCase
{
    #[DataProvider('sortOrders')]
    public function testEscapeSortOrder(string $input, string $expected): void
    {
        self::assertSame($expected, escape_sort_order($input));
    }

    public static function sortOrders(): array
    {
        return [
            ['asc', 'asc'],
            ['desc', 'desc'],
            ['DESC', 'desc'],
            ['foobar', 'asc'],
        ];
    }
}
