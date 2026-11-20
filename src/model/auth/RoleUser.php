<?php

declare(strict_types=1);

namespace youvision\store\admin\model\auth;

use think\model\relation\HasMany;
use think\model\relation\HasOne;
use youvision\store\admin\model\BaseModel;
use youvision\store\admin\support\CacheFlusher;

/**
 * 用户与角色的关系
 *
 * @property int $user_id
 * @property int $role_id
 */
class RoleUser extends BaseModel
{
    protected $name = 'auth_role_user';

    protected $pk = 'user_id';

    protected $type = [
        'user_id' => 'integer',
        'role_id' => 'integer',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(RoleRule::class, 'role_id', 'role_id');
    }

    public function role(): HasOne
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public static function onAfterWrite($model): void
    {
        CacheFlusher::flush();
    }

    public static function onAfterDelete($model): void
    {
        CacheFlusher::flush();
    }
}