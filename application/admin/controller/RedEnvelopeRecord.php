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
 * 红包记录管理
 * Class Item
 * @package app\admin\controller
 */
class RedEnvelopeRecord extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRedEnvelopeRecord';

    /**
     * 红包记录列表
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
        $this->title = '红包领取列表';
        $auth = $this->app->session->get('user');
        $where = '';
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where = "(d.f_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or d.f_user_id={$auth['id']} )";
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,d.code as code,u.username,d.type as dtype');
        $query->where($where)->join('lc_red_envelope d','i.pid=d.id')->join('lc_user u','i.uid=u.id')->equal('d.type#i_type')->like('u.username#u_username')->like('d.code#d_code')->dateBetween('i.time#i_time')->order('i.id desc')->page();
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
     * 删除红包记录
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
