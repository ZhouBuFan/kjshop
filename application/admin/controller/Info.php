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
 * 网站配置
 * Class Item
 * @package app\admin\controller
 */
class Info extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcInfo';
    protected $reward_table = 'LcReward';
    protected $version_table = 'LcVersion';
    protected $popup_table = 'LcPopup';
    protected $email_table = 'LcEmail';
    protected $phone_table = 'LcSms';

    /**
     * 网站信息
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
        $this->title = '网站设置';
        Cache::store('redis')->del("info");
        $this->_form($this->table, 'info');
    }
    /**
     * 网站信息修改
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
        $this->_form($this->table, 'info_form');
    }
    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isPost()&&isset($vo['ban_ip'])&&!empty($vo['ban_ip'])){
            $vo['ban_ip'] = trim(str_replace('，',',',$vo['ban_ip']));
        }
    }
    
    /**
     * 奖励配置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function reward()
    {
        $this->title = '奖励配置';
        $this->items = Db::name('LcItem')->select();
        $this->_form($this->reward_table, 'reward');
    }
    /**
     * 奖励配置修改
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function reward_edit()
    {
        $this->items = Db::name('LcItem')->select();
        $this->_form($this->reward_table, 'reward_form');
    }
    /**
     * 邮箱配置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function email()
    {
        $this->title = '邮箱配置';
        $this->_form($this->email_table, 'email');
    }
    /**
     * 邮箱配置编辑
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function email_edit()
    {
        $this->_form($this->email_table, 'email_form');
    }
    /**
     * 短信配置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function phone()
    {
        $this->title = '短信配置';
        $this->_form($this->phone_table, 'phone');
    }
    /**
     * 短信配置编辑
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function phone_edit()
    {
        $this->_form($this->phone_table, 'phone_form');
    }
     /**
     * APP下载
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function version()
    {
        $this->title = 'APP下载';
        Cache::store('redis')->del("version");
        $this->_form($this->version_table, 'version');
    }
    /**
     * APP下载编辑
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function version_edit()
    {
        $this->_form($this->version_table, 'version_form');
    }
    /**
     * 首页弹窗
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function popup()
    {
        $this->title = '首页弹窗设置';
        Cache::store('redis')->del("popup");
        $this->_form($this->popup_table, 'popup');
    }
    /**
     * 首页弹窗编辑
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function popup_edit()
    {
        $this->_form($this->popup_table, 'popup_form');
    }

    /**
     * 支付设置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function pay()
    {
        $this->title = '支付设置';
        $this->_form($this->table, 'pay');
    }

    /**
     * 图片设置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function img()
    {
        $this->title = '支付设置';
        $this->_form($this->table, 'img');
    }
}
