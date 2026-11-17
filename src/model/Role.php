<?php

declare(strict_types=1);

namespace youvision\store\auth\model;

use think\Model;
use think\model\relation\HasMany;
use youvision\store\auth\support\CacheFlusher;

/**
 * 权限角色
 *
 * @property int    $id
 * @property string $title
 * @property int    $status
 */
class Role extends Model
{
    protected $name = 'store_auth_role';

    protected $pk = 'id';

    protected $type = [
        'id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 删除角色时同步清理角色与规则、用户的关系数据
     */
    public static function onAfterDelete($role): void
    {
        RoleRule::where('role_id', $role->id)->delete();
        RoleUser::where('role_id', $role->id)->delete();
        CacheFlusher::flush();
    }

    protected function setStatusAttr($value): int
    {
        return in_array($value, ['on', 'true', '1', 1, true], true) ? 1 : 0;
    }

    protected function getUserNumAttr(): int
    {
        return (int) RoleUser::where('role_id', $this->getData('id'))->count();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(RoleRule::class, 'role_id', 'id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(RoleUser::class, 'role_id', 'id');
    }
}