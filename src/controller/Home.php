<?php

declare(strict_types=1);

namespace youvision\store\admin\controller;

use think\Response;

class Home extends Controller
{
    public function index(): Response
    {
        return youvision('home');
    }
}