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
 * 货币管理
 * Class AlipaySet
 * @package app\admin\controller
 */
class Currency extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcCurrency';
    protected $info_table = 'LcInfo';

    /**
     * 货币列表
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
        $this->title = '货币列表';
        $this->rate_api = getInfo('rate_api');
        $query = $this->_query($this->table);
        $query->order('sort asc,id desc')->page();
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
     * 添加货币
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->applyCsrfToken();
        $this->title = '添加';
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑货币
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->applyCsrfToken();
        $this->title = '编辑';
        $this->_form($this->table, 'form');
    }
    /**
     * 隐藏
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function hidden()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['show' => '0']);
    }

    /**
     * 显示
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function show()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['show' => '1']);
    }
    
    /**
     * 编辑汇率API
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function api()
    {
        $this->applyCsrfToken();
        $this->title = '编辑汇率API';
        $this->_form($this->info_table, 'api');
    }

}
