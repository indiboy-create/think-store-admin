<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\Collection;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\LayuiTable;

final class LayuiTableTest extends TestCase
{
    public function testResponse(): void
    {
        $response = LayuiTable::response([['id' => 1, 'title' => 'A']], 20);

        self::assertSame(0, $response['code']);
        self::assertSame(20, $response['count']);
        self::assertSame([['id' => 1, 'title' => 'A']], $response['data']);
    }

    public function testResponseSupportsCollectionAndPaginator(): void
    {
        $collection = new Collection([['id' => 1], ['id' => 2]]);
        $response = LayuiTable::response($collection);

        self::assertSame(2, $response['count']);
        self::assertSame([['id' => 1], ['id' => 2]], $response['data']);

        $paginator = new class {
            public function total(): int
            {
                return 8;
            }

            public function items(): array
            {
                return [['id' => 3]];
            }
        };

        $page = LayuiTable::response($paginator);
        self::assertSame(8, $page['count']);
        self::assertSame([['id' => 3]], $page['data']);
    }

    public function testCustomResponseFieldsAndError(): void
    {
        $response = LayuiTable::response([['id' => 1]], 1, [
            'statusName' => 'status',
            'statusCode' => 1,
            'countName' => 'total',
            'dataName' => 'rows',
        ]);

        self::assertSame(1, $response['status']);
        self::assertSame(1, $response['total']);
        self::assertSame([['id' => 1]], $response['rows']);

        $error = LayuiTable::error('参数错误');
        self::assertSame(1, $error['code']);
        self::assertSame('参数错误', $error['msg']);
    }

    public function testPageAndSort(): void
    {
        self::assertSame(
            ['page' => 2, 'limit' => 10, 'offset' => 10],
            LayuiTable::page(['page' => 2, 'limit' => 100], ['maxLimit' => 10])
        );
        self::assertSame(
            ['page' => 1, 'limit' => 1, 'offset' => 0],
            LayuiTable::page([], ['page' => 0, 'limit' => 0])
        );

        self::assertSame(
            ['field' => 'id', 'order' => 'desc'],
            LayuiTable::sort(['id'], ['query' => ['field' => 'id', 'order' => 'DESC']])
        );
        self::assertSame(
            ['field' => '', 'order' => ''],
            LayuiTable::sort(['id'], ['query' => ['field' => 'password', 'order' => 'asc']])
        );
    }

    public function testWhereAndColumns(): void
    {
        self::assertSame(
            ['keyword' => 'abc', 'status' => '1'],
            LayuiTable::where([
                'page' => 1,
                'limit' => 10,
                'field' => 'id',
                'order' => 'asc',
                'keyword' => 'abc',
                'status' => '1',
            ])
        );

        $columns = LayuiTable::columns([
            ['type' => 'checkbox', 'fixed' => 'left'],
            ['field' => 'id', 'title' => 'ID'],
            ['field' => 'title'],
        ]);

        self::assertSame('checkbox', $columns[0][0]['type']);
        self::assertSame('ID', $columns[0][1]['title']);
        self::assertSame('title', $columns[0][2]['title']);
    }

    public function testColumnRenderAndScript(): void
    {
        self::assertSame(
            ['field' => 'status', 'title' => '状态', 'sort' => true],
            LayuiTable::column('status', '状态', ['sort' => true])
        );

        $render = LayuiTable::render([
            'elem' => '#table-demo',
            'cols' => [['field' => 'id', 'title' => 'ID']],
        ]);

        self::assertSame('#table-demo', $render['elem']);
        self::assertSame([[['field' => 'id', 'title' => 'ID']]], $render['cols']);

        $script = LayuiTable::script([
            'elem' => '#table-demo',
            'cols' => [['field' => 'id', 'title' => 'ID']],
        ]);

        self::assertStringContainsString('table.render', $script);
        self::assertStringContainsString('#table-demo', $script);
    }
}