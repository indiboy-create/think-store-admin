<?php

declare(strict_types=1);

namespace youvision\store\admin\controller;

use think\Response;
use think\facade\Session;

class Passport extends Controller
{
    public function login(): Response
    {
        if ($this->request->isAjax()) {
            $data = $this->request->param();
            $login = trim((string) ($data['username'] ?? $data['user_name'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            $uid = store_admin_verify_password($login, $password);

            if ($uid > 0) {
                Session::set('store_admin', ['uid' => $uid, 'login' => $login]);

                return json(['code' => 0, 'msg' => '登录成功', 'url' => store_admin_url('index'), 'data' => ['uid' => $uid]]);
            }

            return json(['code' => 1, 'msg' => '登录失败']);
        }

        return youvision('login');
    }

    public function logout(): Response
    {
        Session::delete('store_admin');

        return json(['code' => 0, 'msg' => '注销成功']);
    }
}