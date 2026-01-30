<?php

/**
 * Custom PHPStan Rule to Forbid Laminas-DB Usage
 *
 * This rule prevents use of laminas-db classes anywhere in the codebase.
 * Laminas-DB has been removed and replaced with Doctrine DBAL.
 *
 * @package   OpenEMR
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2025 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Use_>
 */
class ForbiddenClassesRule implements Rule
{
    /**
     * Forbidden namespace prefixes
     */
    private const FORBIDDEN_NAMESPACES = [
        'Laminas\\Db\\',
    ];

    public function getNodeType(): string
    {
        return Use_::class;
    }

    /**
     * @param Use_ $node
     * @return array<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return iterator_to_array($this->getErrors($node, $scope));
    }

    /**
     * @return \Generator<\PHPStan\Rules\RuleError>
     */
    private function getErrors(Use_ $node, Scope $scope): \Generator
    {
        foreach ($node->uses as $use) {
            $importedName = $use->name->toString();

            // Check if this is a forbidden Laminas-DB import
            if ($this->isForbiddenImport($importedName)) {
                $message = sprintf(
                    'Laminas-DB class "%s" is forbidden. Use Doctrine DBAL instead.',
                    $importedName
                );

                yield RuleErrorBuilder::message($message)
                    ->identifier('openemr.deprecatedLaminasDb')
                    ->build();
            }
        }
    }

    private function isForbiddenImport(string $importedName): bool
    {
        foreach (self::FORBIDDEN_NAMESPACES as $forbiddenNamespace) {
            if (str_starts_with($importedName, $forbiddenNamespace)) {
                return true;
            }
        }
        return false;
    }
}
