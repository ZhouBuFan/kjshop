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

namespace app\admin\controller;

use library\Controller;
use think\Db;

/**
 * 客服管理
 * Class Item
 * @package app\admin\controller
 */
class Service extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcService';

    /**
     * 客服列表
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = '客服列表';
        $auth = $this->app->session->get('user');
        $query = $this->_query($this->table)->where("system_user_id={$auth['id']}");
        $query->order('sort asc,id asc')->page();
    }


    /**
     * 添加客服
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加客服';
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑客服
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑客服';
        $this->_form($this->table, 'form');
    }

    /**
     * 删除客服
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }
    
       /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            if(!isset($vo['show'])) $vo['show'] = '1';
            if(!isset($vo['type'])) $vo['type'] = '1';
        }
        if ($this->request->isPost()) {
            $auth = $this->app->session->get('user');
            $vo['system_user_id'] = $auth['id'];
        }
        if (empty($vo['time'])) $vo['time'] = date("Y-m-d H:i:s");
    }

}
