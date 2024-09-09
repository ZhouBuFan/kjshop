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
 * 提现方式管理
 * Class Item
 * @package app\admin\controller
 */
class WithdrawMethod extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUserWithdrawMethod';

    /**
     * 提现方式列表
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
        $this->title = '提现方式列表';
        Cache::store('redis')->del("withdrawal_method");
        $query = $this->_query($this->table)->alias('i')->field('i.*,c.name as cname,c.country as ccountry,c.country_cn as ccountry_cn,c.price as crate');
        $query->join('lc_currency c','i.cid=c.id')->where('i.delete=0')->order('c.sort asc,i.sort asc')->page();
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            $vo['show'] = isset($vo['show'])?$vo['show']:1;
            $vo['type'] = isset($vo['type'])?$vo['type']:1;
        }
        $this->currencies = Db::name("LcCurrency")->order('sort asc')->select();
    }
    /**
     * 添加可提现钱包类型
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加可提现钱包类型';
        $this->_form($this->table, 'form');
    }
    /**
     * 编辑可提现钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑可提现钱包';
        $this->_form($this->table, 'form');
    }


    /**
     * 删除提现钱包
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
