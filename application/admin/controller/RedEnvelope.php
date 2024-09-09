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
            $params['money'] ;
            $params['num'];
            $params['f_user_id'];
            $params['code'];
            $total= $params['money'] ;
            $min = 100; 
            if($params['num'] == 1){//单人红包
                Cache::store('redis')->lpush( $params['code'],$total);
                
            }else{
                //按人均来上下浮动
                $num = $params['num'];
                //人均
                $money = $total / $num;
                for($i=0;$i<$num;$i++){
                    // if($money <= 1000){
                    //     $fd=mt_rand(-100,100);
                    // }elseif($money > 1000 && $money <=3000){
                    //     $fd=mt_rand(-300,300);
                    // }elseif($money > 3000){
                    //     $fd=mt_rand(-500,500);
                    // }
                    $fd=mt_rand(-$money*0.1,$money*0.1);
                    if(intval($money+$fd) <0){
                        Cache::store('redis')->lpush($params['code'],1);
                    }else{
                        Cache::store('redis')->lpush($params['code'],intval($money+$fd));
                    }
                    
                }




                // //多人红包
                // $num = $params['num'];
                // for($i=1;$i<$num;$i++){
                //     $safe_total = ($total-($num-$i)*$min)/($num-$i);
                //     $money=mt_rand($min*100,$safe_total*100)/100;
                //     $total = $total-$money;
                //     //第1- -1个红包;
                //     // print_r( $params['code'].'第'.$i.'个红包：'.$money. '元,余额：'.$total. '元</br>');
                //     // $money =  number_format($money, 2);
                //     if($money<500){
                //         $money = 500;
                //     }
                //     if($money>4000){
                //         $money = 4000;
                //     }
                //     Cache::store('redis')->lpush($params['code'],intval($money));
                    
                // }
                // if($total<500){
                //     $total = 500;
                // }
                // if($total>4000){
                //     $total = 4000;
                // }
                // //最后一个红包
                // // $total = number_format($total,2);
                // // print_r($params['code'].'第'.$num.'个红包：'.$total. '元,余额：0元</br>');   
                // Cache::store('redis')->lpush($params['code'],intval($total));
            }
        }
        // print_r('123213213123');
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
