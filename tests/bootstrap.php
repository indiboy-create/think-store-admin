<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
if (!function_exists('response')) {
    require dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';
}