<?php

declare(strict_types=1);

namespace youvision\store\auth\model;

use think\Model;
use youvision\store\auth\support\CacheFlusher;

/**
 * 权限规则
 *
 * @property int    $id
 * @property string $name
 * @property string $title
 * @property int    $type
 * @property int    $status
 * @property string $condition
 */
class Rule extends Model
{
    protected $name = 'store_auth_rule';

    protected $pk = 'id';

    protected $type = [
        'id' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 只查询启用状态的规则
     */
    public function scopeEnabled($query): void
    {
        $query->where('status', 1);
    }

    protected function setStatusAttr($value): int
    {
        return in_array($value, ['on', 'true', '1', 1, true], true) ? 1 : 0;
    }

    /**
     * 删除规则时同步清理角色与规则的关系并刷新缓存
     */
    public static function onAfterDelete($rule): void
    {
        RoleRule::where('rule_id', $rule->id)->delete();
        CacheFlusher::flush();
    }
}