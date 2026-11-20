<?php

declare(strict_types=1);

namespace youvision\store\admin\controller\doc;

use think\Request;
use think\Response;
use youvision\store\admin\controller\Controller;
use youvision\store\admin\facade\Auth;
use youvision\store\admin\service\Auth as AuthService;
use youvision\store\admin\support\theme\Theme;
use youvision\store\admin\support\view\View;

/**
 * 权限总览主题控制器
 */
class Overview extends Controller
{
    public function __construct(protected Theme $theme, protected View $view, ?Request $request = null)
    {
        parent::__construct($request);
    }

    public function index(Request $request): Response
    {
        $currentUid = (int) $request->middleware('store_admin_uid', 0);
        $uid = $this->theme->themeUser() ?: $currentUid;

        $html = $this->view->render('index', [
            'title' => '权限总览',
            'base' => $this->theme->base(),
            'home' => $this->theme->home(),
            'assets' => $this->theme->assets(),
            'apiUser' => $this->theme->apiUser(),
            'version' => $this->theme->version(),
            'configs' => $this->theme->configSummary(),
            'tables' => $this->theme->tables(),
            'routes' => $this->theme->routes(),
            'meta' => $this->theme->meta(),
            'uid' => $uid,
        ]);

        return Response::create($html, 'html', 200);
    }

    /**
     * 只读查询用户拥有的规则与角色
     */
    public function user(Request $request): Response
    {
        $uid = (int) $request->param('uid', 0);

        if ($uid <= 0) {
            return Response::create(['code' => 422, 'msg' => '请提供有效的用户 ID'], 'json', 422);
        }

        if ($this->theme->accessMode() !== 'public') {
            $currentUid = (int) $request->middleware('store_admin_uid', 0);

            if ($currentUid <= 0) {
                return Response::create(['code' => 403, 'msg' => '请先登录后再查询权限'], 'json', 403);
            }

            if ($uid !== $currentUid && !$this->canQueryOtherUsers($currentUid)) {
                return Response::create(['code' => 403, 'msg' => '无权查看其他用户的权限'], 'json', 403);
            }
        }

        try {
            $auth = app(AuthService::class);

            return Response::create([
                'code' => 0,
                'msg' => 'ok',
                'data' => [
                    'uid' => $uid,
                    'rules' => array_values((array) $auth->rules($uid)),
                    'roles' => array_values((array) $auth->roles($uid)),
                    'role_ids' => array_values((array) $auth->roles($uid, 'role_id')),
                ],
            ], 'json', 200);
        } catch (\Throwable) {
            return Response::create(['code' => 500, 'msg' => '查询失败，请检查数据库与认证配置'], 'json', 500);
        }
    }

    /**
     * 是否拥有查看其他用户权限的规则
     */
    protected function canQueryOtherUsers(int $uid): bool
    {
        $permission = $this->theme->permission();

        return $permission !== '' && Auth::check($permission, $uid, 0, 'node');
    }
}