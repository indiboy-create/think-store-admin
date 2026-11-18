<?php

declare(strict_types=1);

namespace youvision\store\admin\controller;

class Menu extends Controller
{
    /**
     * 后台首页
     */
    public function index()
    {
        $systemInit = [
            [
                "id" => "home",
                "title" => "工作空间",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => "home-1",
                    "title" => "控制面板",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ], [
                    "id" => "home-2",
                    "title" => "我的资料",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ],
            [
                "id" => 200000,
                "title" => "应用管理",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => 200001,
                    "title" => "应用列表",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ],
            [
                "id" => 300000,
                "title" => "用户管理",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => 300001,
                    "title" => "用户列表",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ],
            [
                "id" => 400000,
                "title" => "消息管理",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => 400001,
                    "title" => "消息列表",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ], [
                    "id" => 400002,
                    "title" => "消息分类",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ], [
                    "id" => 400003,
                    "title" => "消息工具",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ],
            [
                "id" => 500000,
                "title" => "菜单配置",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => 500001,
                    "title" => "菜单管理",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ],
            [
                "id" => 600000,
                "title" => "省市区",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => 600001,
                    "title" => "全部",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ],
            [
                "id" => 700000,
                "title" => "系统配置",
                "icon" => "layui-icon layui-icon-console",
                "type" => 0,
                "href" => store_admin_url('home'),
                "children" => [[
                    "id" => 700001,
                    "title" => "清除缓存",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ], [
                    "id" => 700002,
                    "title" => "环境监测",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    "href" => store_admin_url('home')
                ]]
            ]
        ];
        return json($systemInit);
    }
}