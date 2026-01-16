<?php

declare(strict_types=1);

namespace OpenEMR\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Function_>
 */
final class DuplicateFunctionRule implements Rule
{
    /** @var array<string, string> */
    private static array $seenFunctions = [];

    public function getNodeType(): string
    {
        return Function_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name) {
            return [];
        }

        $namespace = $scope->getNamespace() ?? '';
        $functionName = $node->name->toString();

        // FULLY QUALIFIED NAME
        $key = strtolower($namespace . '\\' . $functionName);

        if (isset(self::$seenFunctions[$key])) {
            return [
                sprintf(
                    'Duplicate function definition "%s()" (previously defined in %s)',
                    $functionName,
                    self::$seenFunctions[$key]
                ),
            ];
        }

        self::$seenFunctions[$key] = $scope->getFile();

        return [];
    }
}
