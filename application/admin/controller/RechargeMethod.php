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
use think\facade\Cache;

/**
 * 充值方式管理
 * Class Item
 * @package app\admin\controller
 */
class RechargeMethod extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUserRechargeMethod';

    /**
     * 充值方式列表
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
        $this->title = '充值方式列表';
        Cache::store('redis')->del("recharge_method");
        $query = $this->_query($this->table)->alias('i')->field('i.*,c.name as cname,c.country as ccountry,c.country_cn as ccountry_cn,c.price as crate');
        $query->join('lc_currency c','i.cid=c.id')->where('i.delete = 0')->order('c.sort asc,i.sort asc')->page();
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
            if($vo['type']==5){
                $wallet = Db::name("LcSysWallet")->find($vo['swid']);
                if(empty($wallet)){
                    $this->error("系统钱包不存在");
                }
                $vo['account'] = $wallet['address'];
                $vo['img'] = $wallet['qrcode'];
            }
        }
        $this->currencies = Db::name("LcCurrency")->order('sort asc')->select();
        $this->sysWallets = Db::name("LcSysWallet")->order('id asc')->select();
    }
    /**
     * 添加USDT
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_usdt()
    {
        $this->title = '添加USDT';
        $this->_form($this->table, 'form_usdt');
    }
    /**
     * 编辑USDT（需提交hash）
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_usdt()
    {
        $this->title = '编辑USDT（需提交hash）';
        $this->_form($this->table, 'form_usdt');
    }
    /**
     * 添加优盾USDT（需提交hash）
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_ydusdt()
    {
        $this->title = '添加优盾USDT（需提交hash）';
        $this->_form($this->table, 'form_ydusdt');
    }
    /**
     * 编辑优盾USDT（需提交hash）
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_ydusdt()
    {
        $this->title = '编辑优盾USDT（需提交hash）';
        $this->_form($this->table, 'form_ydusdt');
    }
    /**
     * 添加优盾USDT（免提交）
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_ydusdt2()
    {
        $this->title = '添加优盾USDT（免提交）';
        $this->_form($this->table, 'form_ydusdt2');
    }
    /**
     * 编辑优盾USDT（免提交）
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_ydusdt2()
    {
        $this->title = '编辑优盾USDT（免提交）';
        $this->_form($this->table, 'form_ydusdt2');
    }

    /**
     * 添加支付宝
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_alipay()
    {
        $this->title = '添加支付宝';
        $this->_form($this->table, 'form_alipay');
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
     * 添加微信扫码
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_wx()
    {
        $this->title = '添加微信扫码';
        $this->_form($this->table, 'form_wx');
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
     * 添加银行卡
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_bank()
    {
        $this->title = '添加银行卡';
        $this->_form($this->table, 'form_bank');
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
        $this->_save($this->table, ['delete' => '1']);
    }
}
