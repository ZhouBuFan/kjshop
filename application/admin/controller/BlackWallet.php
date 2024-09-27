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
use think\Request;

/**
 * 用户钱包管理
 * Class Item
 * @package app\admin\controller
 */
class BlackWallet extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcBlackWallet';

    /**
     * 钱包列表
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
        $this->title = '黑名单钱包列表';
        $auth = $this->app->session->get('user');
        $account = $this->request->param('account');
        $where='deleted_at is null ';
        if ($account) {
            $where .= " and account like '%{$account}%' ";
        }
        $this->_query($this->table)->where($where)->order('id desc')->page();
    }



    /**
     * 数据列表处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _index_page_filter(&$data)
    {


    }
    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        $auth = $this->app->session->get('user');
        $vo['sys_user']=$auth['username'];
        $vo['created_at']=date('Y-m-d H:i:s');
            sysoplog('用户管理', '编辑黑名单用户钱包');
    }
    /**
     * 添加黑名单银行账户
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加黑名单银行账户';
        $this->_form($this->table, 'add_form');
    }



    /**
     * 删除黑名单
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        Db::name($this->table)->where("id=$id")->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->success('success');
    }
}
