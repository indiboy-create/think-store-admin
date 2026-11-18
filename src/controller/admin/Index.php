<?php

declare(strict_types=1);

namespace youvision\store\admin\controller\admin;

use think\Response;
use youvision\store\admin\controller\Controller;

class Index extends Controller
{
    public function index(): Response
    {
        return youvision('index');
    }
}