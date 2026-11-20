<?php

declare(strict_types=1);

namespace youvision\store\admin\controller\filecloud;

use think\Request;
use think\Response;
use youvision\store\admin\controller\Controller;
use youvision\store\admin\support\layui\LayuiUploads;

/**
 * Layui upload 上传接口
 */
class Upload extends Controller
{
    public function upload(Request $request): Response
    {
        return Response::create(LayuiUploads::handle($request), 'json', 200);
    }
}