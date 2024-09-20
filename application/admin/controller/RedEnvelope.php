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
 * 红包管理
 * Class Item
 * @package app\admin\controller
 */
class RedEnvelope extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRedEnvelope';

    /**
     * 红包列表
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
        $this->title = '红包列表';
        $auth = $this->app->session->get('user');
        $where = '';
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where = "(f_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or f_user_id={$auth['id']} )";
        }
        $query = $this->_query($this->table)->where($where);
        $query->order('id desc')->page();
    }

    /**
     * 表单数据处理
     * @param array $vo                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   
     * @throws \ReflectionException                                                                                                                                       
     */
    protected function _form_filter(&$vo){
        $user = $this->app->session->get('user');

        if ($this->request->isGet()) {
            if(!isset($vo['type'])) $vo['type'] = '1';
            if(empty($vo['code'])) $vo['code'] = strtoupper(substr(uniqid(),0,9));
            if(empty($vo['f_user_id'])) $vo['f_user_id'] = $user['id'];
            // $vo['s_name'] = Db::table('system_user')->where("id={$user['id']}")->value('username');  
                 
        }
        
        
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
        $user = $this->app->session->get('user');
        foreach($data as &$vo){
            $systemuser = Db::table('system_user')->where("id={$vo['f_user_id']}")->find(); 
            $vo['s_name'] =$systemuser['username']; 
        }  
    }
    


    /**
     * 添加红包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        // $this->title = '添加红包';
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $total = $params['money']; // 红包总金额
            $num = $params['num']; // 红包个数
            $type = $params['type']; // 红包类型
            $code = $params['code']; // 红包标识码

            if ($num == 1) { // 单人红包
                Cache::store('redis')->lpush($code, $total);
            } else {
                if ($type == 1) { // 随机红包
                    $min = 1; // 每个红包的最小金额
                    $remainingAmount = $total; // 剩余金额
                    $remainingNum = $num; // 剩余红包数量

                    for ($i = 0; $i < $num - 1; $i++) {
                        // 保证随机红包金额在 [min, 剩余金额 / 剩余红包数量 * 2] 范围内
                        $max = ($remainingAmount / $remainingNum) * 2;
                        $money = mt_rand($min * 100, $max * 100) / 100; // 生成随机金额，保留两位小数

                        $remainingAmount -= $money; // 剩余金额减少
                        $remainingNum--; // 剩余红包数量减少
                        $money=bcadd($money,0,2);
                        Cache::store('redis')->lpush($code, $money); // 将红包金额放入缓存队列
                    }

                    // 最后一个红包，直接放入剩余的金额
                    $remainingAmount=bcadd($remainingAmount,0,2);
                    Cache::store('redis')->lpush($code, $remainingAmount);
                } else { // 平均红包
                    $avg = $total / $num; // 计算每个红包的平均金额
                    for ($i = 0; $i < $num; $i++) {
                        $avg=bcadd($avg,0,2);
                        Cache::store('redis')->lpush($code, $avg); // 每个红包都是平均金额
                    }
                }
            }
        }
        $this->_form($this->table, 'form');
    }


    /**
     * 编辑红包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        // $this->title = '编辑红包';
        $this->_form($this->table, 'form');
    }

    /**
     * 删除红包
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        if ($this->request->isPost()) {
            $params = $this->request->param();

            $redenvelope=Db::table('lc_red_envelope')->where("id={$params['id']}")->find();
            Cache::store('redis')->del($redenvelope['code']);

        }
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
}
