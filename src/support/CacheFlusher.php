<?php

declare(strict_types=1);

namespace youvision\store\admin\support;

use youvision\store\admin\Auth;

/**
 * 在授权数据变化后清理认证缓存
 *
 * 模型事件中调用，失败时不影响业务写入；此时需手动调用 Auth::flush()。
 */
final class CacheFlusher
{
    public static function flush(): void
    {
        if (!function_exists('app')) {
            return;
        }

        try {
            Auth::flush();
        } catch (\Throwable) {
        }
    }
}