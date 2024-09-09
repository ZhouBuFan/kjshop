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
use library\tools\Data;
use think\facade\Cache;
use think\Db;


/**
 * 项目管理
 * Class Item
 * @package app\admin\controller
 */
class Item extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcItem';

    /**
     * 项目管理
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
        $this->title = '项目管理';
        Cache::store('redis')->del("items");
        Cache::store('redis')->del("itemsdetail");
        Cache::store('redis')->del("itemsdetail");
        Cache::store('redis')->del("itemslist");
        Cache::store('redis')->del("itemsnumber");
        Cache::store('redis')->del("itemsnumber");
        $query = $this->_query($this->table)->equal('cid')->like('title_en_us');
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
        $this->mlists = Db::name('LcItemClass')->select();
        $this->mlist = Data::arr2table($this->mlists);
        foreach ($data as &$vo) {
            foreach ($this->mlist as $class) if ($class['id'] == $vo['cid']) $vo['item_class'] = $class;
        }
    }

    /**
     * 添加项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加项目';
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑项目';
        $this->_form($this->table, 'form');
    }

    /**
     * 删除项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->_delete($this->table);
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            $vo['prize'] = isset($vo['prize'])?$vo['prize']:0;
            $vo['index_type'] = isset($vo['index_type'])?$vo['index_type']:1;
            $vo['show'] = isset($vo['show'])?$vo['show']:1;
            $vo['type'] = isset($vo['type'])?$vo['type']:1;
            $vo['is_distribution'] = isset($vo['is_distribution'])?$vo['is_distribution']:0;
            if (empty($vo['class']) && $this->request->get('class', '0')) $vo['class'] = $this->request->get('class', '0');
            $this->class = Db::name("LcItemClass")->order('id asc')->select();
            $this->class = Data::arr2table($this->class);
            $this->viplists = Db::name("LcUserMember")->select();
            $vo['not_receive'] = empty($vo['not_receive']) ? [] : json_decode($vo['not_receive']);
        }
        if ($this->request->isPost()) {
            $vo['not_receive'] = isset($vo['not_receive']) ? json_encode($vo['not_receive']) : '[]';
        }
        if (empty($vo['add_time'])) $vo['add_time'] = date("Y-m-d H:i:s");
    }
    /**
     * 生成K线
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function renew_k()
    {
        $item = Db::name("LcItem")->find($this->request->post('id'));
        $k_range = explode(",",$item['k_range']);
        
        //生成X轴,Y轴
        $k_x = '';
        $k_y_12m = '';
        for ($i = 500; $i >= 0; $i--){
            //X轴
            $k_x = $k_x.sprintf("%.2f", $k_range[0] + mt_rand() / mt_getrandmax() * ($k_range[1] - $k_range[0]));
            
            //Y轴
            $k_y_12m = $k_y_12m.date("Y-m-d", strtotime("-$i days"));
            if($i!=0){
                $k_x = $k_x.",";
                $k_y_12m = $k_y_12m.",";
            }
        }
        
        $this->_save($this->table, ['k_x' => $k_x,'k_y_12m' => $k_y_12m]);
    }
    /**
     * 设置是否显示
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function set_show() {
        $params = $this->request->param();
        $res = Db::name($this->table)->where('id',$params['id'])->update(['show' => $params['show']]);
        // 回复前端结果
        if ($res !== false) {
            $this->success(lang('think_library_save_success'), '');
        } else {
            $this->error(lang('think_library_save_error'));
        }
    }

}
