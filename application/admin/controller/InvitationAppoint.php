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
 * 邀请函指定管理
 * Class Item
 * @package app\admin\controller
 */
class InvitationAppoint extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcInvitationAppoint';

    /**
     * 邀请函指定列表
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
        $this->title = '邀请函指定列表';
        $auth = $this->app->session->get('user');
        $where = '';
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where = "(f_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or f_user_id={$auth['id']} )";
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,d.title_en_us as title,u.username, d.type as dtype');
        $query->join('lc_article d','i.article_id=d.id')->where($where)->join('lc_user u','i.uid=u.id')->like('u.username#u_username')->dateBetween('i.created_at#i_time')->order('i.id desc')->page();
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
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            $auth = $this->app->session->get('user');
            $where = ' 1 ';
            if ($auth['username'] != 'admin') {
                $where .= " and (system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or system_user_id={$auth['id']}) ";
            }
            $vo['users'] = Db::table('lc_user')->field('id, username')->where($where)->select();
            $vo['prizes'] = Db::table('lc_article')->field('id, title_en_us')->where(['show' => 1, 'type' => 14])->select();
        }
    }
    /**
     * 添加指定
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
     * 删除抽奖指定
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
