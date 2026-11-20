<?php

declare(strict_types=1);

namespace youvision\store\admin\model;

use think\Model;

/**
 * 模型基类
 */
abstract class BaseModel extends Model
{
    /**
     * 查找单条记录
     *
     * @param mixed $data 查询条件
     * @param array $with 关联查询
     * @return static|null
     */
    public static function findOne($data, array $with = [])
    {
        try {
            $query = (new static())->with($with);
            return is_array($data) ? $query->where($data)->find() : $query->find($data);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 按条件删除记录
     */
    public static function deleteAll(array $where)
    {
        return (new static())->where($where)->delete();
    }
}