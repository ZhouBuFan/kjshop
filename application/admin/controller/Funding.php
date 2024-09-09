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
 * 流水记录
 * Class Item
 * @package app\admin\controller
 */
class Funding extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUserFunding';

    /**
     * 流水记录
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
        $this->title = '流水记录';
        $auth = $this->app->session->get('user');
        $sys_username = $this->request->param('sys_username');
        $where = '1 ';
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where .= " and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )";
        }
        // 所属下级系统用户
        if ($sys_username) {
            $sys_user_id = Db::table('system_user')->alias('su')->join('system_user_relation sur', 'sur.uid=su.id')->where("su.is_deleted=0 and sur.parentid={$auth['id']}")->whereLike('username', "%{$sys_username}%")->column('su.id');
            $where .= " and u.system_user_id in (".($sys_user_id ? implode(',', $sys_user_id) : 0).") ";
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.username');
        $query->where($where)->join('lc_user u','i.uid=u.id')->equal('i.type#i_type')->equal('i.fund_type#i_fund_type')->like('u.username#u_username')->dateBetween('i.act_time#i_time')->valueBetween('i.money')->order('i.id desc')->page();
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
        foreach($data as &$vo){
            
            $user = Db::name('LcUser')->where('id', $vo['uid'])->find();
           
            $vo['s_name'] = Db::table('system_user')->where("id={$user['system_user_id']}")->value('username');
            $top_user = Db::name('LcUserRelation')->where("uid = {$vo['uid']} AND level = 1")->find();
            if(!empty($top_user)){
                $top_user = Db::name('LcUser')->find($top_user['parentid']);
                if(!empty($top_user)){
                    $vo['top'] = $top_user['username'];
                }
            }
        }
    }
    
    /**
     * 删除记录
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
