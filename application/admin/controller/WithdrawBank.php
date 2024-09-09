<?php
namespace app\admin\controller;

use library\Controller;
use think\Db;

/**
 * 提现银行管理
 * Class Item
 * @package app\admin\controller
 */
class WithdrawBank extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'lc_user_withdraw_bank';

    /**
     * 提现银行列表
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
        $this->title = '提现银行列表';
        $query = $this->_query($this->table)->alias('i')->field('i.*,c.name as cname,c.country_cn as ccountry_cn');
        $query->join('lc_currency c','i.cid=c.id')->order('i.sort asc,i.id asc')->page();
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        $this->currencies = Db::name("LcCurrency")->order('sort asc')->select();
    }
    /**
     * 添加提现银行
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加提现银行';
        $this->_form($this->table, 'form');
    }
    /**
     * 编辑提现银行
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑提现银行';
        $this->_form($this->table, 'form');
    }


    /**
     * 删除提现银行
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }
}
