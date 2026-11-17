<?php

declare(strict_types=1);

namespace youvision\store\admin\repository;

use think\App;
use think\facade\Db;
use youvision\store\admin\contract\Repository;
use youvision\store\admin\model\Role;
use youvision\store\admin\model\RoleUser;
use youvision\store\admin\model\RoleRule;

/**
 * 基于数据库的认证数据源
 */
class DatabaseRepository implements Repository
{
    protected App $app;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;
        $this->config = array_replace_recursive([
            'auth_user' => 'member',
            'auth_user_pk' => 'id',
            'auth_user_model' => '',
            'auth_user_fields' => [
                'email' => 'email',
                'mobile' => 'mobile',
                'username' => 'username',
                'password' => 'password',
                'salt' => 'salt',
                'salt2' => 'salt2',
            ],
        ], $config);
    }

    public function roleIds(int $uid): array
    {
        $roleIds = array_values(array_unique(array_map('intval', (array) RoleUser::where('user_id', $uid)->column('role_id'))));

        if ($roleIds === []) {
            return [];
        }

        // 仅返回启用中的角色，禁用角色不参与认证
        $active = Role::whereIn('id', $roleIds)->where('status', 1)->column('id');

        return array_values(array_unique(array_map('intval', (array) $active)));
    }

    public function rulesForRoles(array $roleIds, int $type): array
    {
        if ($roleIds === []) {
            return [];
        }

        $relations = RoleRule::whereIn('role_id', $roleIds)->with(['rule'])->select();
        $rules = [];

        foreach ($relations as $relation) {
            $rule = $relation->rule;

            if ($rule === null) {
                continue;
            }

            if ((int) $rule->status !== 1) {
                continue;
            }

            if ($type > 0 && (int) $rule->type !== $type) {
                continue;
            }

            $rules[] = [
                'name' => (string) $rule->name,
                'condition' => (string) $rule->condition,
                'type' => (int) $rule->type,
            ];
        }

        return $rules;
    }

    public function user(int $uid): array
    {
        $model = trim((string) $this->config['auth_user_model']);

        if ($model !== '' && class_exists($model)) {
            $instance = new $model();
            $pk = (string) $this->config['auth_user_pk'];
            $row = $instance->where($pk, $uid)->find();

            return $row === null ? [] : (array) $row->toArray();
        }

        $row = Db::name((string) $this->config['auth_user'])
            ->where((string) $this->config['auth_user_pk'], $uid)
            ->find();

        return is_array($row) ? $row : [];
    }

    /**
     * 按用户名、邮箱或手机号查找用户
     *
     * 查询字段由 auth_user_fields 的 username、email、mobile 映射决定，
     * 映射为空时跳过该字段。
     */
    public function findByLogin(string $login): array
    {
        $login = trim($login);

        if ($login === '') {
            return [];
        }

        $fields = (array) ($this->config['auth_user_fields'] ?? []);
        $columns = [];

        foreach (['username', 'email', 'mobile'] as $key) {
            $column = trim((string) ($fields[$key] ?? ''));

            if ($column !== '') {
                $columns[] = $column;
            }
        }

        $columns = array_values(array_unique($columns));

        if ($columns === []) {
            return [];
        }

        $query = Db::name((string) $this->config['auth_user']);

        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $query->where($column, $login);
            } else {
                $query->whereOr($column, $login);
            }
        }

        $row = $query->find();

        return is_array($row) ? $row : [];
    }

    public function roles(int $uid, string $field = ''): array
    {
        if ($field !== '') {
            return array_values(array_map('intval', (array) RoleUser::where('user_id', $uid)->column($field)));
        }

        $roles = RoleUser::with(['role'])->where('user_id', $uid)->select();

        return $roles === null ? [] : (array) $roles->toArray();
    }

    /**
     * 删除用户的全部角色关系
     */
    public function purgeUser(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }

        return (int) RoleUser::where('user_id', $uid)->delete();
    }
}