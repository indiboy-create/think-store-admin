<?php

declare(strict_types=1);

namespace youvision\store\admin\controller;

use think\Request;
use think\Response;
use youvision\store\admin\support\layui\LayuiUploads;

/**
 * Layui upload 上传接口
 */
class UploadController
{
    public function upload(Request $request): Response
    {
        return Response::create(LayuiUploads::handle($request), 'json', 200);
    }
}