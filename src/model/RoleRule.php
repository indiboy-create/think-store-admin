<?php

declare(strict_types=1);

namespace youvision\store\admin\model;

use think\Model;
use think\model\relation\HasOne;
use youvision\store\admin\support\CacheFlusher;

/**
 * 角色与规则的关系
 *
 * @property int $role_id
 * @property int $rule_id
 */
class RoleRule extends Model
{
    protected $name = 'store_admin_role_rule';

    protected $pk = 'role_id';

    protected $type = [
        'role_id' => 'integer',
        'rule_id' => 'integer',
    ];

    public function rule(): HasOne
    {
        return $this->hasOne(Rule::class, 'id', 'rule_id');
    }

    /**
     * 兼容旧关联名称
     */
    public function rules(): HasOne
    {
        return $this->rule();
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