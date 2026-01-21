#!/usr/bin/env php
<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

final class GlobalDefinitionCollector extends NodeVisitorAbstract
{
    /** @var array<string, array<int, array{file: string, line: int}>> */
    public array $definitions = [];

    private bool $inGlobalNamespace = true;

    public function __construct(
        private string $currentFile,
    ) {
    }

    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            // `namespace;` or `namespace {}` counts as global
            $this->inGlobalNamespace = $node->name === null;
            return;
        }

        if (!$this->inGlobalNamespace) {
            return;
        }

        if (
            $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_
        ) {
            if ($node->name === null) {
                // anonymous class
                return;
            }

            $name = $node->name->toString();

            $this->definitions[$name][] = [
                'file' => $this->currentFile,
                'line' => $node->getStartLine(),
            ];
        }
    }

    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->inGlobalNamespace = true;
        }
    }
}

$root = $argv[1] ?? getcwd();
$root = realpath($root);

if ($root === false) {
    fwrite(STDERR, "Invalid root directory\n");
    exit(1);
}

$parser = (new ParserFactory())->createForNewestSupportedVersion();

/** @var array<string, array<int, array{file: string, line: int}>> */
$allDefinitions = [];

$dirIter = new RecursiveDirectoryIterator(
    $root,
    RecursiveDirectoryIterator::SKIP_DOTS
);

$iter = new RecursiveIteratorIterator($dirIter);
$iter = new RegexIterator($iter, '/^.+\.(php|inc)$/i', RegexIterator::GET_MATCH);

$isBlocked = function (string $file): bool {
    $blockedDirectories = [
        '/vendor/',
        '/tmp-phpstan',
    ];
    foreach ($blockedDirectories as $blocked) {
        if (str_contains($file, $blocked)) {
            return true;
        }
    }
    return false;
};

foreach ($iter as $matches) {
    $file = $matches[0];

    if ($isBlocked($file)) {
        continue;
    }
    echo "\n$file...";

    $code = file_get_contents($file);
    if ($code === false) {
        continue;
    }

    try {
        $ast = $parser->parse($code);
        if ($ast === null) {
            continue;
        }

        $traverser = new NodeTraverser();
        $collector = new GlobalDefinitionCollector($file);
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        foreach ($collector->definitions as $name => $locations) {
            foreach ($locations as $loc) {
                $allDefinitions[$name][] = $loc;
            }
        }
    } catch (Error $e) {
        // intentionally ignore parse errors; messy codebase assumed
        continue;
    }
}

$dupes = array_filter($allDefinitions, fn ($locs) => count($locs) > 1);

if (count($dupes) === 0){
    echo "No duplicate files detected!";
    exit(0);
}

ksort($dupes);

foreach ($dupes as $fnName => $locations) {
    printf("Function `%s` defined in %d places:\n", $fnName, count($locations));
    foreach ($locations as $loc) {
        printf("  - %s:%d\n", $loc['file'], $loc['line']);
    }
}

printf("Total %d duplicate functions", count($dupes));
exit(1);
