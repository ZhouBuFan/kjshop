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
use library\service\AdminService;
use think\Db;

/**
 * 系统用户管理
 * Class User
 * @package app\admin\controller
 */
class User extends Controller
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'SystemUser';
    protected $info = 'LcInfo';

    /**
     * 系统用户管理
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
        $this->title = '系统用户管理';
        $user = $this->app->session->get('user');
        $where  = ['is_deleted' => '0'];
        if (isset($user['username']) and $user['username'] != 'admin') {
            $where['f_user_id'] = $user['id'] ?? 0;
        }
        $query = $this->_query($this->table)->where($where)->like('username,phone,mail')->equal('status');
        $query->dateBetween('login_at,create_at')->order('id desc')->page();
    }

    /**
     * 添加系统用户
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
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑系统用户
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
        sysoplog('系统管理', '编辑系统用户');
        $this->_form($this->table, 'form');
    }

    /**
     * 修改用户密码
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function pass()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $this->verify = false;
            $this->_form($this->table, 'pass');
        } else {
            $post = $this->request->post();
            if ($post['password'] !== $post['repassword']) {
                $this->error('两次输入的密码不一致！');
            }
            if (Data::save($this->table, ['id' => $post['id'], 'password' => md5($post['password'])], 'id')) {
                sysoplog('系统管理', '修改系统用户密码');
                $this->success('密码修改成功，下次请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            // 用户权限处理
            $data['authorize'] = (isset($data['authorize']) && is_array($data['authorize'])) ? join(',', $data['authorize']) : '';
            // 用户账号重复检查
            if (isset($data['id'])) unset($data['username']);
            elseif (Db::name($this->table)->where(['username' => $data['username'], 'is_deleted' => '0'])->count() > 0) {
                $this->error("账号{$data['username']}已经存在，请使用其它账号！");
            }
            $data['authorize'] = $data['authorize'] ?? 2;
            $user = $this->app->session->get('user');
            $data['f_user_id'] = $user['id'] ?? -1;
        } else {
            $data['authorize'] = explode(',', isset($data['authorize']) ? $data['authorize'] : '');
            $this->authorizes = Db::name('SystemAuth')->where(['status' => '1'])->order('sort desc,id desc')->select();
        }
    }
    public function _form_result($result, $data) {
        $user = $this->app->session->get('user');
        $this->insertUserRelation($result,$user['id'], 999);
    }

    /**
     * 禁用系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止操作！');
        }
        $this->applyCsrfToken();
        sysoplog('系统管理', '禁用系统用户');
        $this->_save($this->table, ['status' => '0']);
    }

    /**
     * 启用系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $this->applyCsrfToken();
        sysoplog('系统管理', '启用系统用户');
        $this->_save($this->table, ['status' => '1']);
    }

    /**
     * 删除系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止删除！');
        }
        $this->applyCsrfToken();
        sysoplog('系统管理', '删除系统用户');
        $this->_delete($this->table);
    }
    
     /**
     * 生成代理链接
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setAgentLink()
    {
        $this->applyCsrfToken();
        $info = Db::name("LcInfo")->find(1);
        $agent_link = $info["domain"]."/#/?agent=".$this->request->post('id');
        $this->_save($this->table, ['agent_link' => $agent_link]);
    }
    /**
     * 绑定
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function bind()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0']);
    }

    /**
     * 解绑谷歌验证器
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function unbind()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止操作！');
        }
        $this->applyCsrfToken();
        $this->_save($this->table, ['auth_google' => '0','google_key' => '0','google_qrcode' => '0']);
    }
    /**
     * 绑定谷歌验证器
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function bind_google()
    {
        
        $this->applyCsrfToken();
        $user = $this->app->session->get('user');
        if ($this->request->isPost()) {
            $code = $this->request->post('code');
            if($this->verifyCode($user['google_key'],$code)){
                Db::name('SystemUser')->where(['id' => $user['id']])->update([
                    'auth_google'  => 1,
                ]);
				sysoplog('系统管理', '系统用户绑定谷歌验证器');
                $this->app->session->clear();
                $this->app->session->destroy();
                $this->success('绑定成功，请重新登录！', url('@admin/login'));
            }else{
               
               $this->error("验证码错误"); 
            }
            
        } else {
            $this->assign('google_key', $user['google_key']);
            $this->assign('google_qrcode', $user['google_qrcode']);
            $this->fetch();
        }
        
    }
    /**
 * @description：插入用户关系（闭包表方式）
 * @date: 2022/6/17
 * @param $userId
 * @param $parentid
 * @param $invite_level  分销级别
 * @return bool
 */
public function insertUserRelation($userId,$parentid,$invite_level)
{
    //查询上级关系网
    $topRelation = Db::name('SystemUserRelation')->where(['uid' => $parentid])->order('id asc')->select();
    
    Db::name('SystemUserRelation')->insert(['uid' => $userId,'parentid' => $parentid,'level' => 1]);
    //插入上级关系网
    if(!empty($topRelation)){
        foreach ($topRelation as $key => $top) {
            //当达到分销级别时，跳出循环
            if($key>=$invite_level-1){
                break;
            }
            Db::name('SystemUserRelation')->insert([
                'uid' => $userId,
                'parentid' => $top['parentid'],
                'level' => $top['level']+1
                ]);
        }
    }
    return true;
}

}
