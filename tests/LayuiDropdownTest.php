<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\LayuiDropdown;

final class LayuiDropdownTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function data(): array
    {
        return [
            ['id' => 1, 'title' => '菜单一'],
            [
                'id' => 2,
                'title' => '菜单二',
                'children' => [
                    ['id' => 21, 'title' => '子菜单二'],
                ],
            ],
            ['type' => '-'],
            [
                'id' => 3,
                'title' => '菜单组',
                'type' => 'group',
                'children' => [
                    ['id' => 31, 'title' => '子菜单三'],
                ],
            ],
        ];
    }

    public function testItems(): void
    {
        $items = LayuiDropdown::items($this->data());

        self::assertSame(1, $items[0]['id']);
        self::assertSame('菜单一', $items[0]['title']);
        self::assertSame(['type' => '-'], $items[2]);
        self::assertSame('菜单二', $items[1]['title']);
        self::assertSame(21, $items[1]['child'][0]['id']);
        self::assertSame('group', $items[3]['type']);
        self::assertSame(31, $items[3]['child'][0]['id']);
        self::assertArrayNotHasKey('children', $items[1]);
    }

    public function testCustomFieldMapping(): void
    {
        $items = LayuiDropdown::items([
            [
                'cid' => 1,
                'name' => '节点A',
                'nodes' => [
                    ['cid' => 2, 'name' => '节点B'],
                ],
            ],
        ], [
            'id' => 'cid',
            'title' => 'name',
            'source_child' => 'nodes',
        ]);

        self::assertSame(1, $items[0]['id']);
        self::assertSame('节点A', $items[0]['title']);
        self::assertSame(2, $items[0]['child'][0]['id']);
    }

    public function testGroupAndDivider(): void
    {
        self::assertSame(['type' => '-'], LayuiDropdown::divider());

        $group = LayuiDropdown::group('组', [['id' => 1, 'title' => '子项']], ['id' => 9]);

        self::assertSame(9, $group['id']);
        self::assertSame('group', $group['type']);
        self::assertSame('组', $group['title']);
        self::assertSame('子项', $group['child'][0]['title']);
    }

    public function testRender(): void
    {
        $render = LayuiDropdown::render([
            'elem' => '#more-menu',
            'id' => 'more-menu',
            'trigger' => 'hover',
            'data' => $this->data(),
        ]);

        self::assertSame('#more-menu', $render['elem']);
        self::assertSame('hover', $render['trigger']);
        self::assertSame(['id' => 'id', 'title' => 'title', 'children' => 'child'], $render['customName']);
        self::assertSame(21, $render['data'][1]['child'][0]['id']);
        self::assertArrayNotHasKey('children', $render['data'][1]);
    }

    public function testReload(): void
    {
        $response = LayuiDropdown::reload('more-menu', [
            ['id' => 1, 'title' => '新选项'],
        ]);

        self::assertSame(0, $response['code']);
        self::assertSame('more-menu', $response['data']['id']);
        self::assertTrue($response['data']['options']['show']);
        self::assertSame('新选项', $response['data']['options']['data'][0]['title']);
    }

    public function testReloadData(): void
    {
        $response = LayuiDropdown::reloadData('more-menu', [
            ['id' => 1, 'title' => '新选项'],
        ]);

        self::assertSame(0, $response['code']);
        self::assertFalse($response['data']['options']['show']);
    }

    public function testOpenAndClose(): void
    {
        self::assertSame('close', LayuiDropdown::close('more-menu')['data']['action']);
        self::assertSame('open', LayuiDropdown::open('more-menu')['data']['action']);
    }

    public function testConfiguredFields(): void
    {
        $app = new App(dirname(__DIR__));
        $app->config->set([
            'layui' => [
                'dropdown' => [
                    'id' => 'cid',
                    'title' => 'name',
                    'source_child' => 'nodes',
                ],
            ],
        ], 'store_admin');

        $items = LayuiDropdown::items([
            ['cid' => 1, 'name' => '节点A', 'nodes' => [['cid' => 2, 'name' => '节点B']]],
        ]);

        self::assertSame(1, $items[0]['id']);
        self::assertSame('节点B', $items[0]['child'][0]['title']);
    }
}