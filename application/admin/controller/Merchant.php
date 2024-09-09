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
 * 优盾钱包管理
 * Class Item
 * @package app\admin\controller
 */
class Merchant extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcMerchant';

    /**
     * 优盾钱包管理
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
        $this->title = '优盾钱包管理';
        $query = $this->_query($this->table)->like('name');
        $query->order('id asc')->page();
    }

    /**
     * 添加优盾钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    // public function add()
    // {
    //     $this->title = '添加优盾钱包';
    //     $this->_form($this->table, 'form');
    // }

    /**
     * 编辑优盾钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑优盾钱包';
        $this->_form($this->table, 'form');
    }
    /**
     * 手动代付
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function auto0()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['auto' => '0']);
    }
    /**
     * 自动代付
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function auto1()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['auto' => '1']);
    }
    /**
     * 手动审核
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function auto_audit0()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['auto_audit' => '0']);
    }
    /**
     * 自动审核
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function auto_audit1()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['auto_audit' => '1']);
    }

    /**
     * 删除优盾钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    // public function remove()
    // {
    //     $this->applyCsrfToken();
    //     $this->_delete($this->table);
    // }

}
