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
 * 用户钱包管理
 * Class Item
 * @package app\admin\controller
 */
class Wallet extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUserWallet';

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
        $this->title = '钱包列表';
        $auth = $this->app->session->get('user');
        $where = "deleted_at = '0000-00-00 00:00:00' ";
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where .= " and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )";
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.username,c.name as cname,c.country as ccountry,c.country_cn as ccountry_cn,c.price as crate');
        $query->where($where)->join('lc_user u','i.uid=u.id')->join('lc_currency c','i.cid=c.id')->like('u.username#u_username')->order('i.id desc')->page();
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
        if ($this->request->isGet()) {
            $vo['show'] = isset($vo['show'])?$vo['show']:1;
        }else{
            sysoplog('用户管理', '编辑用户钱包');
        }
        $this->currencies = Db::name("LcCurrency")->order('sort asc')->select();
    }
    /**
     * 编辑USDT
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_usdt()
    {
        $this->title = '编辑USDT';
        $this->_form($this->table, 'form_usdt');
    }
    /**
     * 编辑支付宝
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_alipay()
    {
        $this->title = '编辑支付宝';
        $this->_form($this->table, 'form_alipay');
    }
    /**
     * 编辑微信扫码
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_wx()
    {
        $this->title = '编辑微信扫码';
        $this->_form($this->table, 'form_wx');
    }
    /**
     * 编辑银行卡
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_bank()
    {
        $this->title = '编辑银行卡';
        $this->_form($this->table, 'form_bank');
    }


    /**
     * 删除充值方式
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
        // $this->_delete($this->table);
    }
}
