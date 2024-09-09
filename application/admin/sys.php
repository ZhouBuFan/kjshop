<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

use library\File;
use library\service\AdminService;
use library\service\CaptchaService;
use library\service\SystemService;
use think\Db;
use think\facade\Middleware;
use think\facade\Route;
use think\Request;

if (!function_exists('auth')) {
    /**
     * 节点访问权限检查
     * @param string $node 需要检查的节点
     * @return boolean
     * @throws ReflectionException
     */
    function auth($node)
    {
        return AdminService::instance()->check($node);
    }
}


if (!function_exists('sysoplog')) {
    /**
     * 写入系统日志
     * @param string $action 日志行为
     * @param string $content 日志内容
     * @return boolean
     */
    function sysoplog($action, $content)
    {
        return SystemService::instance()->setOplog($action, $content);
    }
}


// 访问权限检查中间键
Middleware::add(function (Request $request, \Closure $next) {
    if (AdminService::instance()->check()) {
        return $next($request);
    } elseif (AdminService::instance()->isLogin()) {
        return json(['code' => 0, 'msg' => '抱歉，没有访问该操作的权限！']);
    } else {
        return json(['code' => 0, 'msg' => '抱歉，需要登录获取访问权限！', 'url' => url('@admin/login')]);
    }
});

// ThinkAdmin 图形验证码
Route::get('/think/admin/captcha', function () {
    $image = CaptchaService::instance();
    return json(['code' => '1', 'info' => '生成验证码', 'data' => [
        'uniqid' => $image->getUniqid(), 'image' => $image->getData()
    ]]);
});
