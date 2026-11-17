<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\LayuiTree;

final class LayuiTreeTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function data(): array
    {
        return [
            ['id' => 1, 'pid' => 0, 'title' => '一级栏目一'],
            ['id' => 2, 'pid' => 0, 'title' => '一级栏目二'],
            ['id' => 3, 'pid' => 1, 'title' => '二级栏目一'],
            ['id' => 4, 'pid' => 1, 'title' => '二级栏目二'],
            ['id' => 5, 'pid' => 2, 'title' => '二级栏目三'],
            ['id' => 6, 'pid' => 3, 'title' => '三级栏目一'],
        ];
    }

    public function testTreeQueries(): void
    {
        $tree = (new LayuiTree())->init($this->data());

        self::assertSame([3, 4], array_keys($tree->getChild(1)));
        self::assertSame([3, 6, 4], $tree->getChildrenIds(1));
        self::assertSame([1, 3, 6, 4], $tree->getChildrenIds(1, true));
        self::assertSame([1, 3], $tree->getParentsIds(6));
        self::assertSame([1, 3, 6], $tree->getParentsIds(6, true));
        self::assertSame(3, $tree->getParent(6)[0]['id']);
        self::assertSame([], $tree->getParent(1));
    }

    public function testTreeArrayAndList(): void
    {
        $tree = (new LayuiTree())->init($this->data());
        $data = $tree->getTreeArray(0);

        self::assertCount(2, $data);
        self::assertCount(2, $data[0]['childlist']);
        self::assertSame($tree->nbsp . '└', $data[0]['childlist'][1]['spacer']);

        $rows = $tree->getTreeList($data);
        self::assertCount(6, $rows);
        self::assertSame(1, $rows[0]['haschild']);
        self::assertSame(0, $rows[2]['haschild']);
    }

    public function testMultipleChildAndCycles(): void
    {
        $tree = (new LayuiTree())->init($this->data());
        $nested = $tree->multipleChild();

        self::assertCount(2, $nested);
        self::assertArrayHasKey('childlist', $nested[0]);
        self::assertArrayHasKey('childlist', $nested[0]['childlist'][0]);

        $cycle = (new LayuiTree())->init([
            ['id' => 1, 'pid' => 2, 'title' => 'A'],
            ['id' => 2, 'pid' => 1, 'title' => 'B'],
        ]);

        self::assertSame([2], array_column($cycle->getChildren(1), 'id'));
        self::assertSame([1, 2], array_column($cycle->getChildren(1, true), 'id'));
        self::assertSame([2, 1], array_column($cycle->getParents(1, true), 'id'));

        $cycleTree = (new LayuiTree())->init([
            ['id' => 1, 'pid' => 2, 'title' => 'A'],
            ['id' => 2, 'pid' => 1, 'title' => 'B'],
        ])->getTreeArray(1);

        self::assertCount(1, $cycleTree);
    }

    public function testLayuiTreeAndTreeTable(): void
    {
        $tree = (new LayuiTree())->init($this->data());

        $treeData = $tree->toTree();
        self::assertCount(2, $treeData);
        self::assertSame('一级栏目一', $treeData[0]['title']);
        self::assertArrayHasKey('children', $treeData[0]);
        self::assertCount(2, $treeData[0]['children']);

        $flat = $tree->toTreeTable();
        self::assertCount(6, $flat);
        self::assertSame([1, 3, 6, 4, 2, 5], array_column($flat, 'id'));
        self::assertSame(1, $flat[1]['parentId']);
        self::assertArrayNotHasKey('pid', $flat[1]);
        self::assertTrue($flat[0]['isParent']);
        self::assertFalse($flat[2]['isParent']);

        $custom = $tree->toTreeTable(['pid' => 'pid', 'isParent' => false]);
        self::assertSame(1, $custom[1]['pid']);
        self::assertArrayNotHasKey('isParent', $custom[0]);
    }

    public function testMenuAndNav(): void
    {
        $tree = (new LayuiTree())->init($this->data());

        $menu = $tree->toMenu(['filter' => 'menu-demo', 'selected' => 3, 'expanded' => [1]]);
        self::assertStringContainsString('layui-menu', $menu);
        self::assertStringContainsString('lay-filter="menu-demo"', $menu);
        self::assertStringContainsString('layui-menu-item-group', $menu);
        self::assertStringContainsString('layui-menu-item-checked', $menu);
        self::assertStringContainsString("lay-options='{\"id\":1,\"type\":\"group\"}'", $menu);

        $nav = $tree->toNav([
            'tree' => true,
            'side' => true,
            'filter' => 'nav-demo',
            'selected' => 6,
            'expanded' => [1],
        ]);
        self::assertStringContainsString('layui-nav layui-nav-tree layui-nav-side', $nav);
        self::assertStringContainsString('lay-filter="nav-demo"', $nav);
        self::assertStringContainsString('layui-nav-child', $nav);
        self::assertStringContainsString('<dd class="layui-this">', $nav);
        self::assertStringContainsString('layui-nav-itemed', $nav);
    }

    public function testEscapingAndCustomFields(): void
    {
        $escaped = (new LayuiTree())->init([
            ['id' => 1, 'pid' => 0, 'title' => '<script>alert(1)</script>'],
            ['id' => 2, 'pid' => 1, 'title' => 'A"B\'C'],
        ])->toMenu();

        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringNotContainsString('A"B\'C', $escaped);

        $mapped = (new LayuiTree(['id' => 'cid', 'pid' => 'parent_id', 'title' => 'label']))
            ->init([
                ['cid' => 1, 'parent_id' => 0, 'label' => '节点A'],
                ['cid' => 2, 'parent_id' => 1, 'label' => '节点B'],
            ]);

        self::assertSame('cid', $mapped->pk);
        self::assertSame('parent_id', $mapped->pidname);
        self::assertSame(1, $mapped->toTree()[0]['id']);
        self::assertSame('节点A', $mapped->toTree()[0]['title']);
        self::assertSame(1, $mapped->toTreeTable()[1]['parentId']);
    }

    public function testConfiguredFields(): void
    {
        $app = new App(dirname(__DIR__));
        $app->config->set([
            'layui' => [
                'id' => 'cid',
                'pid' => 'parent_id',
                'title' => 'name',
                'children' => 'nodes',
            ],
        ], 'store_admin');

        $tree = new LayuiTree();
        self::assertSame('cid', $tree->pk);
        self::assertSame('parent_id', $tree->pidname);
        self::assertSame('name', $tree->title);
        self::assertSame('nodes', $tree->childrenname);
    }
}