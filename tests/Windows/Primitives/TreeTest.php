<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TreeNode;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Fixtures\FakeHost;
use Venusian\Surface\Tests\Fixtures\FakeTree;

/** a(a1(a1x), a2), b; a2's right cell red. */
function forest(): array
{
    return [
        new TreeNode('a', ['left' => 'A', 'right' => 'A'], [
            new TreeNode('a1', ['left' => 'A1'], [new TreeNode('a1x', ['left' => 'A1X'])]),
            new TreeNode('a2', ['left' => 'A2', 'right' => 'A2'], colors: ['right' => Color::hex('#ff0000')]),
        ]),
        new TreeNode('b', ['left' => 'B']),
    ];
}

function fakeTree(?array $nodes = null): FakeTree
{
    $tree = (new FakeHost('main'))->column('root')->tree('t', [new TableColumn('left', 'Left'), new TableColumn('right', 'Right')], $nodes ?? forest());
    assert($tree instanceof FakeTree);

    return $tree;
}

it('refuses a tree node with an empty id, a non-string cell, children that are not a list of nodes, or a colour that is not a Color', function (): void {
    expect(fn () => new TreeNode('', []))->toThrow(InvalidArgumentException::class, 'id')
        ->and(fn () => new TreeNode('n', ['left' => 1]))->toThrow(InvalidArgumentException::class, 'cell')
        ->and(fn () => new TreeNode('n', [1 => 'x']))->toThrow(InvalidArgumentException::class, 'cell')
        ->and(fn () => new TreeNode('n', [], [new stdClass()]))->toThrow(InvalidArgumentException::class, 'children')
        ->and(fn () => new TreeNode('n', [], ['x' => new TreeNode('c', [])]))->toThrow(InvalidArgumentException::class, 'children')
        ->and(fn () => new TreeNode('n', [], colors: ['left' => '#f00']))->toThrow(InvalidArgumentException::class, 'colour');
});

it('builds its native from the nodes and addresses them by id', function (): void {
    $tree = fakeTree();

    expect($tree->columns())->toHaveCount(2)
        ->and($tree->nodes())->toEqual(forest())
        ->and($tree->log)->toBe(['nodes:2'])
        ->and($tree->engineCellTexts($tree->engineNode('a1')))->toBe(['A1', ''])
        ->and($tree->engineCellColors($tree->engineNode('a2')))->toEqual([null, Color::hex('#ff0000')])
        ->and($tree->enginePathOf('a1x'))->toBe([0, 0, 0])
        ->and($tree->enginePathOf('a2'))->toBe([0, 1])
        ->and($tree->engineChildIds(null))->toBe(['a', 'b'])
        ->and($tree->engineChildIds('a'))->toBe(['a1', 'a2'])
        ->and($tree->engineChildIds('b'))->toBe([])
        ->and($tree->engineChildIds('zz'))->toBe([])
        ->and($tree->selectedNode())->toBeNull();
});

it('refuses no columns, a column that is not a TableColumn, and repeated column ids', function (): void {
    $root = (new FakeHost('main'))->column('root');

    expect(fn () => $root->tree('t', []))->toThrow(WindowException::class, 'column')
        ->and(fn () => $root->tree('u', ['left']))->toThrow(WindowException::class, 'TableColumn')
        ->and(fn () => $root->tree('v', [new TableColumn('x', 'X'), new TableColumn('x', 'Y')]))->toThrow(WindowException::class, 'unique');
});

it('refuses a repeated id at any depth and changes nothing', function (): void {
    $tree = fakeTree();
    $tree->expand('a');
    $tree->selectNode('a2');
    $log = $tree->log;

    expect(fn () => $tree->setNodes([new TreeNode('a', [], [new TreeNode('a', [])])]))->toThrow(WindowException::class, "'a'")
        ->and(fn () => $tree->setNodes([new TreeNode('x', []), new TreeNode('x', [])]))->toThrow(WindowException::class, "'x'")
        ->and(fn () => (new FakeHost('main'))->column('root')->tree('t', [new TableColumn('left', 'L')], [new TreeNode('a', [], [new TreeNode('a', [])])]))->toThrow(WindowException::class, "'a'")
        ->and($tree->nodes())->toEqual(forest())
        ->and($tree->isExpanded('a'))->toBeTrue()
        ->and($tree->selectedNode())->toBe('a2')
        ->and($tree->log)->toBe($log);
});

it('expands nodes with children only, records expansion under a collapsed ancestor, and reopens it with the ancestor', function (): void {
    $tree = fakeTree();

    $tree->expand('b');
    expect($tree->isExpanded('b'))->toBeFalse()
        ->and($tree->log)->toBe(['nodes:2'])
        ->and(fn () => $tree->expand('zz'))->toThrow(WindowException::class, "'zz'")
        ->and(fn () => $tree->isExpanded('zz'))->toThrow(WindowException::class, "'zz'");

    $tree->expand('a1');
    expect($tree->isExpanded('a1'))->toBeTrue()->and($tree->log)->toBe(['nodes:2']);

    $tree->expand('a');
    expect($tree->log)->toBe(['nodes:2', 'expanded:a=1', 'expanded:a1=1']);

    $tree->expand('a');
    $tree->expand('a', false);
    $tree->expand('a', false);
    expect($tree->log)->toBe(['nodes:2', 'expanded:a=1', 'expanded:a1=1', 'expanded:a=0'])
        ->and($tree->isExpanded('a1'))->toBeTrue();

    $tree->expand('a1', false);
    expect($tree->log)->toHaveCount(4)->and($tree->isExpanded('a1'))->toBeFalse();
});

it('selects a node by expanding its ancestors from the root down, and clears the selection', function (): void {
    $tree = fakeTree();

    $tree->selectNode('a1x');
    expect($tree->log)->toBe(['nodes:2', 'expanded:a=1', 'expanded:a1=1', 'selected:a1x'])
        ->and($tree->selectedNode())->toBe('a1x')
        ->and($tree->isExpanded('a'))->toBeTrue()
        ->and(fn () => $tree->selectNode('zz'))->toThrow(WindowException::class, "'zz'");

    $tree->selectNode(null);
    expect($tree->log[4])->toBe('selected:none')->and($tree->selectedNode())->toBeNull();
});

it('keeps expansion and selection for ids still present across setNodes, and drops the rest', function (): void {
    $tree = fakeTree();
    $tree->selectNode('a1x');
    $tree->selectNode('a');
    $tree->log = [];

    $tree->setNodes([new TreeNode('a', ['left' => 'A'], [new TreeNode('a1', ['left' => 'A1'])]), new TreeNode('c', [])]);
    expect($tree->log)->toBe(['nodes:2', 'expanded:a=1', 'selected:a'])
        ->and($tree->isExpanded('a1'))->toBeFalse()
        ->and($tree->selectedNode())->toBe('a');

    $tree->log = [];
    $tree->setNodes([new TreeNode('c', [])]);
    expect($tree->log)->toBe(['nodes:1'])->and($tree->selectedNode())->toBeNull();

    $tree->setNodes(forest());
    expect($tree->isExpanded('a'))->toBeFalse()->and($tree->isExpanded('a1'))->toBeFalse();
});

it('opens the ancestors of a kept selection that setNodes moved under a collapsed node, before selecting it', function (): void {
    $tree = fakeTree();
    $tree->selectNode('a1');
    $tree->log = [];

    $tree->setNodes([new TreeNode('a', [], [new TreeNode('a2', [])]), new TreeNode('b', [], [new TreeNode('a1', [])])]);
    expect($tree->log)->toBe(['nodes:2', 'expanded:a=1', 'expanded:b=1', 'selected:a1'])
        ->and($tree->isExpanded('b'))->toBeTrue()
        ->and($tree->selectedNode())->toBe('a1');
});

it('records the user\'s expansion and tells a change from an echo', function (): void {
    $tree = fakeTree();

    expect($tree->nativeNodeExpanded('a', true))->toBeTrue()
        ->and($tree->nativeNodeExpanded('a', true))->toBeFalse()
        ->and($tree->isExpanded('a'))->toBeTrue()
        ->and($tree->nativeNodeExpanded('b', true))->toBeFalse()
        ->and($tree->nativeNodeExpanded('zz', true))->toBeFalse()
        ->and($tree->nativeNodeExpanded('a', false))->toBeTrue()
        ->and($tree->isExpanded('a'))->toBeFalse();
});
