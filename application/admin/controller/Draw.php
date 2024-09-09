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
 * 奖品管理
 * Class Item
 * @package app\admin\controller
 */
class Draw extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcDrawPrize';
    protected $set_table = 'LcDraw';

    /**
     * 奖品列表
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
        $this->title = '奖品列表';
        Cache::store('redis')->del("draw");
        $query = $this->_query($this->table);
        
        $query->order('sort asc , id asc')->page();
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            if(!isset($vo['type'])) $vo['type'] = '3';
            if(!isset($vo['item_id'])) $vo['item_id'] = '';
            $this->items = Db::name('LcItem')->select();
            // var_dump($vo);die;
        }
    }

    /**
     * 添加奖品
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        // $this->title = '添加奖品';
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑奖品
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        // $this->title = '编辑奖品';
        $this->_form($this->table, 'form');
    }

    /**
     * 删除奖品
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
     * 转盘配置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function set()
    {
        $this->title = '转盘配置';
        $this->_form($this->set_table, 'set');
    }
    /**
     * 转盘配置修改
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function set_edit()
    {
        $this->_form($this->set_table, 'set_form');
    }

     /**
     * 设置是否显示
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function set_show() {
        $params = $this->request->param();
        $res = Db::name($this->table)->where('id',$params['id'])->update(['status' => $params['show']]);
        // 回复前端结果
        if ($res !== false) {
            $this->success(lang('think_library_save_success'), '');
        } else {
            $this->error(lang('think_library_save_error'));
        }
    }
}
