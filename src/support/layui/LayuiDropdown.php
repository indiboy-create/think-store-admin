<?php

declare(strict_types=1);

namespace youvision\store\admin\support\layui;

use think\facade\Config;

/**
 * Layui dropdown 数据与重载接口。
 *
 * 将业务数据转换为 dropdown.render 所需的 data 结构，
 * 并提供 reload / reloadData / close / open 的服务端返回结构。
 */
final class LayuiDropdown
{
    /**
     * 将树形或嵌套数据转换为 dropdown 数据
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed>             $fields
     * @return array<int, array<string, mixed>>
     */
    public static function items(array $data, array $fields = []): array
    {
        $fields = self::fields($fields);
        $items = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? 'normal');

            if ($type === '-') {
                $items[] = ['type' => '-'];
                continue;
            }

            $node = [];

            if (array_key_exists($fields['id'], $item)) {
                $node['id'] = $item[$fields['id']];
            }

            if (array_key_exists($fields['title'], $item)) {
                $node['title'] = $item[$fields['title']];
            }

            if ($type !== 'normal') {
                $node['type'] = $type;
            }

            foreach (['href', 'target', 'disabled', 'templet', 'isSpreadItem'] as $field) {
                if (array_key_exists($field, $item)) {
                    $node[$field] = $item[$field];
                }
            }

            $children = $item[$fields['source_child']] ?? $item[$fields['child']] ?? [];

            if (is_array($children) && $children !== []) {
                $node['child'] = self::items($children, $fields);
            }

            $items[] = $node;
        }

        return $items;
    }

    /**
     * 分割线
     *
     * @return array{type: string}
     */
    public static function divider(): array
    {
        return ['type' => '-'];
    }

    /**
     * 纵向菜单组
     *
     * @param array<int, array<string, mixed>> $children
     * @param array<string, mixed>             $options
     * @return array<string, mixed>
     */
    public static function group(string $title, array $children, array $options = []): array
    {
        $fields = self::fields(is_array($options['fields'] ?? null) ? $options['fields'] : []);
        $item = [
            'title' => $title,
            'type' => 'group',
            'child' => self::items($children, $fields),
        ];

        if (isset($options['id'])) {
            $item['id'] = $options['id'];
        }

        return $item;
    }

    /**
     * 生成 dropdown.render 配置
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function render(array $options = []): array
    {
        if (isset($options['data']) && is_array($options['data'])) {
            $fields = is_array($options['fields'] ?? null) ? $options['fields'] : [];
            $options['data'] = self::items($options['data'], $fields);
        }

        $options['customName'] ??= [
            'id' => 'id',
            'title' => 'title',
            'children' => 'child',
        ];

        return $options;
    }

    /**
     * 完整重载，返回给前端调用 dropdown.reload(id, options)
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed>             $options
     * @return array<string, mixed>
     */
    public static function reload(string $id, array $data = [], array $options = []): array
    {
        $options['data'] = $data;
        $options['show'] ??= true;

        return [
            'code' => 0,
            'msg' => '',
            'data' => [
                'id' => $id,
                'options' => self::render($options),
            ],
        ];
    }

    /**
     * 仅重载数据或内容，返回给前端调用 dropdown.reloadData(id, options)
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed>             $options
     * @return array<string, mixed>
     */
    public static function reloadData(string $id, array $data = [], array $options = []): array
    {
        $options['show'] ??= false;

        return self::reload($id, $data, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public static function close(string $id): array
    {
        return [
            'code' => 0,
            'msg' => '',
            'data' => [
                'id' => $id,
                'action' => 'close',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function open(string $id): array
    {
        return [
            'code' => 0,
            'msg' => '',
            'data' => [
                'id' => $id,
                'action' => 'open',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function error(string $msg, int $code = 1): array
    {
        return [
            'code' => $code,
            'msg' => $msg,
            'data' => null,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function fields(array $fields = []): array
    {
        $default = [
            'id' => 'id',
            'title' => 'title',
            'child' => 'child',
            'source_child' => 'children',
        ];

        try {
            $config = Config::get('store_admin.layui.dropdown', []);
        } catch (\Throwable) {
            $config = [];
        }

        $fields = array_intersect_key($fields, $default);

        return array_replace($default, is_array($config) ? $config : [], $fields);
    }
}