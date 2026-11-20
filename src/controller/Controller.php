<?php

declare(strict_types=1);

namespace youvision\store\admin\controller;

use think\Request;

class Controller
{
    public function __construct(protected ?Request $request = null)
    {
    }

    /**
     * 获取当前请求对象
     */
    protected function request(): Request
    {
        return $this->request ??= app('request');
    }

    /**
     * 获取 post 数据（数组）
     */
    protected function postData(string $key): mixed
    {
        return $this->request()->post($key . '/a');
    }

    /**
     * 返回 table 所需格式
     *
     * @param array<string, mixed>|object $list
     */
    protected function toTable(mixed $list = []): mixed
    {
        if (is_object($list)) {
            $list = $list->toArray();
        }

        $data = [
            'code' => 0,
            'msg' => 'ok',
            'count' => isset($list['total']) ? $list['total'] : count($list),
        ];

        return json(array_merge($data, $list));
    }

    /**
     * 返回 TreeTable 所需格式
     *
     * @param array<string, mixed>|object $list
     */
    protected function toTreeTable(mixed $list = []): mixed
    {
        if (is_object($list)) {
            $list = $list->toArray();
        }

        $data = [
            'code' => 0,
            'msg' => 'ok',
            'count' => isset($list['total']) ? $list['total'] : count($list),
        ];

        return json(array_merge($data, ['data' => $list]));
    }
}