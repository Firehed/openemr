<?php

/**
 * Custom PHPStan Rule to Find void/never Return Type Candidates
 *
 * This rule identifies functions and methods that:
 * - Have no return type declared
 * - Have no return statements or only empty return statements (void candidates)
 * - Unconditionally throw/exit/die (never candidates)
 *
 * This helps prioritize adding native return types to the codebase.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Claude Code Assistant
 * @copyright Copyright (c) 2026 OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\FunctionLike>
 */
class MissingVoidNeverReturnTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    /**
     * @param Node\FunctionLike $node
     * @return array<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only process functions and methods, not closures/arrow functions
        if (!($node instanceof Function_) && !($node instanceof ClassMethod)) {
            return [];
        }

        // Skip if already has a return type
        if ($node->getReturnType() !== null) {
            return [];
        }

        // Skip abstract methods (they don't have bodies to analyze)
        if ($node instanceof ClassMethod && $node->isAbstract()) {
            return [];
        }

        // Skip magic methods - they often have implicit return types
        if ($node instanceof ClassMethod) {
            $methodName = $node->name->toString();
            if (str_starts_with($methodName, '__')) {
                return [];
            }
        }

        // Skip methods with no body (interface methods)
        $stmts = $node->getStmts();
        if ($stmts === null || count($stmts) === 0) {
            return [];
        }

        // Analyze the function body
        $analysis = $this->analyzeBody($stmts);

        $errors = [];
        $name = $this->getNodeName($node, $scope);

        if ($analysis['type'] === 'void') {
            $errors[] = RuleErrorBuilder::message(
                sprintf('%s can have void return type: %s', $name, $analysis['reason'])
            )
                ->identifier('openemr.missingVoidReturnType')
                ->tip('Add ": void" return type declaration.')
                ->build();
        } elseif ($analysis['type'] === 'never') {
            $errors[] = RuleErrorBuilder::message(
                sprintf('%s can have never return type: %s', $name, $analysis['reason'])
            )
                ->identifier('openemr.missingNeverReturnType')
                ->tip('Add ": never" return type declaration.')
                ->build();
        }

        return $errors;
    }

    /**
     * Analyze the function body to determine if it's a void or never candidate
     *
     * @param Node\Stmt[] $stmts
     * @return array{type: string|null, reason: string}
     */
    private function analyzeBody(array $stmts): array
    {
        $nodeFinder = new NodeFinder();

        // Find all return statements (excluding those in nested closures/functions)
        $returns = [];
        $hasValueReturn = false;
        $hasEmptyReturn = false;

        foreach ($this->findReturnsInScope($stmts) as $return) {
            $returns[] = $return;
            if ($return->expr !== null) {
                $hasValueReturn = true;
            } else {
                $hasEmptyReturn = true;
            }
        }

        // If there's a return with a value, this is not void or never
        if ($hasValueReturn) {
            return ['type' => null, 'reason' => 'Returns a value'];
        }

        // Check for unconditional throw/exit/die (never candidate)
        // This is conservative - only if the ONLY top-level statement is throw/exit/die
        if (count($stmts) === 1) {
            $stmt = $stmts[0];
            if ($stmt instanceof Throw_) {
                return ['type' => 'never', 'reason' => 'Unconditionally throws'];
            }
            if ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Exit_) {
                return ['type' => 'never', 'reason' => 'Unconditionally exits'];
            }
        }

        // If no return statements or only empty returns, it's void
        if (count($returns) === 0) {
            return ['type' => 'void', 'reason' => 'No return statements'];
        }

        if ($hasEmptyReturn && !$hasValueReturn) {
            return ['type' => 'void', 'reason' => 'Only empty return statements'];
        }

        return ['type' => null, 'reason' => 'Unknown pattern'];
    }

    /**
     * Find return statements in the current scope (not in nested functions/closures)
     *
     * @param Node\Stmt[] $stmts
     * @return Return_[]
     */
    private function findReturnsInScope(array $stmts): array
    {
        $returns = [];
        $this->findReturnsRecursive($stmts, $returns);
        return $returns;
    }

    /**
     * @param Node[]|Node $nodes
     * @param Return_[] $returns
     */
    private function findReturnsRecursive($nodes, array &$returns): void
    {
        if (!is_array($nodes)) {
            $nodes = [$nodes];
        }

        foreach ($nodes as $node) {
            if (!($node instanceof Node)) {
                continue;
            }

            // Don't descend into nested functions/closures/arrow functions
            if ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
                continue;
            }
            if ($node instanceof Function_ || $node instanceof ClassMethod) {
                continue;
            }

            if ($node instanceof Return_) {
                $returns[] = $node;
            }

            // Recurse into sub-nodes
            foreach ($node->getSubNodeNames() as $subNodeName) {
                $subNode = $node->$subNodeName;
                if ($subNode instanceof Node) {
                    $this->findReturnsRecursive([$subNode], $returns);
                } elseif (is_array($subNode)) {
                    $this->findReturnsRecursive($subNode, $returns);
                }
            }
        }
    }

    private function getNodeName(Node\FunctionLike $node, Scope $scope): string
    {
        if ($node instanceof Function_) {
            return 'Function ' . ($node->namespacedName?->toString() ?? $node->name->toString()) . '()';
        }

        $classReflection = $scope->getClassReflection();
        $className = $classReflection?->getName() ?? 'unknown';
        return 'Method ' . $className . '::' . $node->name->toString() . '()';
    }
}
