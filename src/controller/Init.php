<?php

declare(strict_types=1);

namespace youvision\store\admin\controller;

use think\Response;

class Init extends Controller
{
    public function index(): Response
    {
        return json((array) config('store_admin.init', []));
    }
}