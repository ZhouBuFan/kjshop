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
 * 抽奖记录管理
 * Class Item
 * @package app\admin\controller
 */
class DrawRecord extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcDrawRecord';

    /**
     * 抽奖记录列表
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
        $this->title = '抽奖列表';
        $auth = $this->app->session->get('user');
        $where ="1=1";
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where .= " and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )";
        }
        $query = $this->_query($this->table)->where($where)->alias('i')->field('i.*,d.title_zh_cn as title,img,u.username');
        $query->join('lc_draw_prize d','i.pid=d.id')->join('lc_user u','i.uid=u.id')->equal('d.type#i_type')->like('u.username#u_username')->dateBetween('i.time#i_time')->order('i.id desc')->page();
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
     * 删除抽奖记录
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
