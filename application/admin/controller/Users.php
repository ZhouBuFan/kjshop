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
 * 用户管理
 * Class Item
 * @package app\admin\controller
 */
class Users extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUser';
    protected $table_member = 'LcUserMember';
    protected $table_relation = 'LcUserRelation';

    /**
     * 用户列表
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
        $auth = $this->app->session->get('user');
        $params = $this->request->param();
        $this->title = '用户列表';
        $where = ' 1 ';
        if ($auth['username'] != 'admin') {
            $where .= " and (system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or system_user_id={$auth['id']}) ";
        }

        if (isset($params['u_username'])) {
                // 所属下级系统用户\
            if ($params['sys_username']) {
                $sys_user_id = Db::table('system_user')->alias('su')->join('system_user_relation sur', 'sur.uid=su.id')->where("su.is_deleted=0 and sur.parentid={$auth['id']}")->whereLike('username', "%{$params['sys_username']}%")->column('su.id');
                $where .= " and system_user_id in (".($sys_user_id ? implode(',', $sys_user_id) : 0).") ";
            }
            // 上级邀请人
            if ($params['superior_username']) {
                $user_id = Db::table('lc_user')->alias('u')->join('lc_user_relation ur', 'ur.uid=u.id')->join('lc_user lu', 'lu.id=ur.parentid')->where("ur.level = 1 and lu.username like '%{$params['superior_username']}%' and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )")->column('u.id');
                $where .= " and id in (".($user_id ? implode(',', $user_id) : 0).") ";
            }
            // 推广人数
            if ($params['promotion_num'] && $params['promotion_num_1']) {
                $user = Db::table('lc_user')->alias('u')->join('lc_user_relation ur', 'ur.parentid=u.id')->where("ur.level=1 and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )")->field('count(u.id) as num, u.id')->group('u.id')->having("num BETWEEN {$params['promotion_num']} and {$params['promotion_num_1']}")->select();
                $user_id = $user ? array_column($user, 'id') : [0];
                $where .= " and id in (".implode(',', $user_id).") ";
            }
            
            // 有效推广人数
            if ($params['effective_promotion_num_1'] && $params['effective_promotion_num']) {
                $user = Db::table('lc_user')->alias('u')->join('lc_user_relation ur', 'ur.parentid=u.id')->join('lc_user lu', 'lu.id=ur.uid')->where("lu.money>0 and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )")->field('count(u.id) as num, u.id')->group('u.id')->having("num BETWEEN {$params['effective_promotion_num']} and {$params['effective_promotion_num_1']}")->select();
                $user_id = $user ? array_column($user, 'id') : [0];
                $where .= " and id in (".implode(',', $user_id).") ";
            }

            //抽奖次数
            if ($params['draw_num'] && $params['draw_num_1']) {
                $user = Db::table('lc_draw_appoint')->alias('da')->join('lc_user u', 'u.id=da.uid')->where("(u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )")->field('count(u.id) as num, u.id')->group('u.id')->having("num BETWEEN {$params['draw_num']} and {$params['draw_num_1']}")->select();
                $user_id = $user ? array_column($user, 'id') : [0];
                $where .= " and id in (".implode(',', $user_id).") ";
            }
            // 充值钱包
            if ($params['recharge_wallet'] && $params['recharge_wallet_1']) {
                $where .= " and money BETWEEN {$params['recharge_wallet']} and {$params['recharge_wallet_1']} ";
            }
             // 余额钱包
             if ($params['balance_wallet'] && $params['balance_wallet_1']) {
                $where .= " and withdrawable BETWEEN {$params['balance_wallet']} and {$params['balance_wallet_1']} ";
            }
             // 积分
             if ($params['integral'] && $params['integral_1']) {
                $where .= " and point BETWEEN {$params['integral']} and {$params['integral_1']} ";
            }
             // 充值金额
             if ($params['recharge_amount'] && $params['recharge_amount_1']) {
                $user = Db::name("lc_user_recharge_record")->alias('uwr')->join('lc_user u', 'u.id=uwr.uid')->where("(u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} ) AND uwr.status = '1'")->field('sum(uwr.money) as money, u.id')->group('u.id')->having("money BETWEEN {$params['recharge_amount']} and {$params['recharge_amount_1']}")->select();
                $user_id = $user ? array_column($user, 'id') : [0];
                $where .= " and id in (".implode(',', $user_id).") ";
            }
 
            // 总结余
            if ($params['surplus_amount'] && $params['surplus_amount_1']) {
                $user = Db::query("select r.uid  from (SELECT wr.uid,  SUM(wr.money) as wr_money  FROM lc_user_withdraw_record wr where wr.status = 1  GROUP BY wr.uid) as w ,(SELECT rr.uid,  SUM(rr.money) as rr_money  FROM lc_user_recharge_record rr where rr.status = 1  GROUP BY rr.uid) as r where 
                 r.uid = w.uid  and ((r.rr_money-w.wr_money) BETWEEN {$params['surplus_amount']} and {$params['surplus_amount_1']})");
                $user_id = $user ? array_column($user, 'uid') : [0];
                $where .= " and id in (".implode(',', $user_id).") ";
            }
        }

        $this->member = Db::table('lc_user_member')->field('id,name')->select();
        
        $query = $this->_query($this->table)->where($where)->equal('auth_email#u_auth_email,auth_google#u_auth_google,clock#u_clock,mid#u_mid')->like('username#u_username,ip#u_ip')->dateBetween('time#u_time,logintime#u_logintime')->order('id desc')->page();
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
        $ip = new \Ip2Region();
        foreach($data as &$vo){
            $vo['online'] = strtotime($vo['logintime'])>(time()-300)?1:0;
            $vo['withdraw_sum']  = Db::name("lc_user_withdraw_record")->where("uid = {$vo['id']} AND status = '1'")->sum('money')*0.9;
            $vo['recharge_sum']  = Db::name("lc_user_recharge_record")->where("uid = {$vo['id']} AND status = '1'")->sum('money');
            //总结余
            $vo['surplus_sum'] =  $vo['recharge_sum'] - $vo['withdraw_sum'] ;
            $vo['invest_sum']  = Db::name('lc_invest')->where("uid = {$vo['id']} and itemid!=235")->sum('money');
            $vo['invest_wait_rate']  = Db::name('lc_invest')->where("uid = {$vo['id']} AND wait_interest > 0 AND status = 0")->sum('wait_interest');
            $vo['invest_wait_money']  = Db::name('lc_invest')->where("uid = {$vo['id']} AND money > 0 AND status = 0")->sum('money');
            $result = $ip->btreeSearch($vo['ip']);
            $vo['s_name'] = Db::table('system_user')->where("id={$vo['system_user_id']}")->value('username');
            $vo['isp'] = isset($result['region']) ? $result['region'] : '';
            $vo['isp'] = str_replace(['内网IP', '0', '|'], '', $vo['isp']);
            $top_user = Db::name('LcUserRelation')->where("uid = {$vo['id']} AND level = 1")->find();
            if(!empty($top_user)){
                $top_user = Db::name('LcUser')->find($top_user['parentid']);
                if(!empty($top_user)){
                    $vo['top'] = $top_user['username'];
                }
            }
            $vip = Db::name('LcUserMember')->find($vo['mid']);
            $vo['vip_name'] = $vip['name'];
            
            $vo['tema_direct_count'] = Db::name('LcUserRelation')->where("parentid = {$vo['id']} AND level = 1")->count();
            $vo['tema_effective_direct_count'] = Db::name('LcUserRelation')->alias('ur')->join('lc_user urr', 'urr.id=ur.uid')->where("ur.parentid = {$vo['id']} AND ur.level = 1 And urr.mid>8005")->count();
            $vo['tema_indirect_count'] = Db::name('LcUserRelation')->where("parentid = {$vo['id']} AND level <> 1")->count();
            $vo['tema_all_money'] = Db::name('LcUser u,lc_user_relation ur')->where("u.id=ur.uid AND ur.parentid = {$vo['id']}")->sum('u.money');
            // $vo['draw_num'] = Db::table('lc_draw_appoint')->where("uid={$vo['id']}")->count();
            
        }
    }

    /**
     * 表单数据处理
     * @param array $data
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$vo)
    {
        if ($this->request->isPost()) {
            if(!empty($vo['password_temp'])) $vo['password'] = md5($vo['password_temp']);
            //有id则为更新，无id则为新增
            if(isset($vo['id'])){
                $money = Db::name($this->table)->where("id = {$vo['id']}")->value('money');
                if($money&&$money != $vo['money']){
                    $handle_money = $money-$vo['money'];
                    $type = $handle_money>0?2:1;
                    //添加流水
                    $language = getLanguageByTimezone($vo['time_zone']);
                    addFunding($vo['id'],abs($handle_money),changeMoneyByLanguage(abs($handle_money),$language),$type,1,$language);
                    sysoplog('用户管理', "修改用户资金，账号：{$vo['username']}");
                }else{
                    sysoplog('用户管理', "修改用户资料，账号：{$vo['username']}");
                }
            }else{
                if (Db::name($this->table)->where(['username' => $vo['username']])->count() > 0) {
                    $this->error("账号{$vo['username']}已经存在，请使用其它账号！");
                }
                //判断推荐人
                $top_user = Db::name($this->table)->where(['username' => $vo['top_user']])->find();
                if(!empty($vo['top_user'])){
                    if (empty($top_user)) {
                        $this->error("推荐人{$vo['top_user']}不存在，请使用其它账号！");
                    }
                }
                
                $vo['time'] = date('Y-m-d H:i:s');
                $vo['password'] = md5($vo['password']);
                $vo['act_time'] = dateTimeChangeByZone($vo['time'], 'Asia/Shanghai', $vo['time_zone'], 'Y-m-d H:i:s');
                
                
                //邀请码
                $max_id = Db::name($this->table)->max('id');
                $vo['id'] = $max_id + 1;
                $vo['invite_code'] = createCode($vo['id']);
                $vo['mid'] = Db::name($this->table_member)->min('id');
                
                //插入邀请人关系网数据
                if (!empty($top_user)) {
                    $info = Db::name('LcInfo')->find(1);
                    insertUserRelation($vo['id'],$top_user['id'],$info['invite_level']);
                }
                sysoplog('用户管理', '添加用户');
            }
        } else {
            $this->member = Db::name("LcUserMember")->order('id ace')->select();
            $this->currencies = Db::name("LcCurrency")->order('sort asc')->select();
            $vo['auth_email'] = isset($vo['auth_email'])?$vo['auth_email']:0;
            $vo['auth_google'] = isset($vo['auth_google'])?$vo['auth_google']:0;
            $vo['clock'] = isset($vo['clock'])?$vo['clock']:0;
            $vo['member'] = $this->member;
        }
    }
    /**
     * 用户关系网
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function user_relation(){
        $this->title = '用户关系网';
        $username = $this->request->param('username');
        $type = $this->request->param('type');
        $auth = $this->app->session->get('user');
        $where = '1 ';
        // if (isset($auth['username']) and $auth['username'] != 'admin') {
        //     $where .= " and (system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or system_user_id={$auth['id']} )";
        // }
        $user = Db::name('LcUser')->where($where)->where(['username' => $username])->find();
        $where = '1 ';
        if(!empty($user)){
            $uid = $user['id'];
            if($type == 1){
                $where = " parentid = $uid";
            }else{
                $where = " uid = $uid";
            }
        } else {
            $where .= " and id = 0";
        }
        
        $query = $this->_query($this->table_relation)->where($where)->order('level asc,id asc')->page();
    }
    
    /**
     * 数据列表处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _user_relation_page_filter(&$data)
    {
        $type = $this->request->param('type');
        foreach($data as &$vo){
            $user_top;
            $user_my;
            if($type==1){
                $user_top = Db::name('LcUserRelation')->where("uid = {$vo['uid']} AND level = 1")->find();
                $user_my = Db::name('LcUser')->find($vo['uid']);
            }else{
                $user_top = Db::name('LcUserRelation')->where("uid = {$vo['parentid']} AND level = 1")->find();
                $user_my = Db::name('LcUser')->find($vo['parentid']);
            }
            
            if(!empty($user_top)){
                $user_top = Db::name('LcUser')->find($user_top['parentid']);
                $vo['top'] = $user_top['username'];
            }
            $vo['username'] = '--';
            if (!empty($user_my['system_user_id'])) {
                $vo['s_name'] = Db::table('system_user')->where("id={$user_my['system_user_id']}")->value('username');
            }
            $vo['time'] = '--';
            $vo['act_time'] = '--';
            $vo['time_zone'] = '--';
            $vo['team_direct_count'] = '--';
            $vo['team_indirect_count'] = '--';
            if(!empty($user_my)){
                $vo['username'] = $user_my['username'];
                $vo['time'] = $user_my['time'];
                $vo['act_time'] = $user_my['act_time'];
                $vo['time_zone'] = $user_my['time_zone'];
                $vo['team_direct_count'] = Db::name('LcUserRelation')->where("parentid = '{$user_my['id']}' AND level = 1")->count();
                $vo['team_indirect_count'] = Db::name('LcUserRelation')->where("parentid = '{$user_my['id']}' AND level <> 1")->count();
            }
        }
    }
    /**
     * 添加用户
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
        $this->_form($this->table, 'add_form');
    }

    /**
     * 编辑用户
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
        $this->_form($this->table, 'form');
    }

    /**
     * 禁用用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['clock' => '1']);
    }

    /**
     * 启用用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['clock' => '0']);
    }

    /**
     * 删除用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        sysoplog('用户管理', '删除用户');
        $this->_delete($this->table);
    }

    /**
     * 添加投资
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function add_invest() {
        if ($this->request->isGet()) {
            $user_id = $this->request->param('id');
            $items = Db::table('lc_item')->select();
            $this->_form($this->table, 'add_invest','',[],['projects' => $items, 'user_id' => $user_id]);
        } else {
            $item_id = $this->request->param('item_id');
            $user_id = $this->request->param('user_id');
            $language = 'en_us';
            $item = Db::table('lc_item')->where('id', $item_id)->find();
            $money_usd = $item['min'];
            //时区转换
            $time = date('Y-m-d H:i:s');
            $time_zone = getTimezoneByLanguage($language);
            $time_actual = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            $currency = getCurrencyByLanguage($language);
           
            $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $item['day'] . ' day'));
            $total_interest = $money_usd * $item['rate'] / 100;
            $total_num = 1;
            
            //到期还本付息（时）
            if($item['type']==3){
                //按时
                $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $item['day'] . ' hour'));
            }
            //每日付息到期还本
            elseif($item['type']==1 || $item['type']==4){
                //日利率
                $total_interest = $money_usd * $item['rate'] * $item['day'] / 100;
                //返息期数
                $total_num = $item['day'];
            }elseif($item['type']==5){
                $total_num = $item['day'];
            }elseif($item['type']==6){
                //日利率
                $total_interest = $item['min'] * $item['rate'] * $item['day'] / 100;
                //返息期数
                $total_num = $item['day'];
            }elseif($item['type']==7){
                //日利率
                $total_interest = $item['min'] * $item['rate'] * $item['day'] / 100;
                //返息期数
                $total_num = $item['day'];
            }elseif($item['type']==8){
                $total_num = $item['day'];
            }elseif($item['type']==12){
                //日利率
                $total_interest = $item['min'] * $item['rate'] * $item['day'] / 100;
                $total_num = $item['day'];
            }
            $time2_actual = dateTimeChangeByZone($time2, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            $orderNo = 'ST' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
            //添加投资记录
            $insert = array(
                "uid" =>$user_id,
                "itemid" =>$item['id'],
                "orderNo" =>$orderNo,
                "money" => $money_usd,
                "money2" =>$money_usd,
                "total_interest" =>$total_interest,
                "wait_interest" =>$total_interest,
                "total_num" =>$total_num,
                "wait_num" =>$total_num,
                "day" =>$item['day'],
                "rate" =>$item['rate'],
                "type" =>$item['type'],
                "not_receive"=>$item['not_receive'],
                "currency" =>$currency,
                "time_zone" =>$time_zone,
                "time" =>$time,
                "time_actual" =>$time_actual,
                "time2" =>$time2,
                "time2_actual" =>$time2_actual,
                "is_withdrawal_purchase"=>1
            );
            $iid = Db::name('LcInvest')->insertGetId($insert);
            if (!empty($iid)) {
                $this->success(lang('think_library_form_success'), '');
            } else {
                $this->error(lang('think_library_form_error'));
            }
        }
    }


/**
     * 充值
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function add_record() {
        $this->applyCsrfToken();
        if ($this->request->isPost()) {
            $money_num = $this->request->param('money_num');
            $user_id = $this->request->param('id');
            $language = 'en_us';

            //时区转换
            $time = date('Y-m-d H:i:s');
            $time_zone = getTimezoneByLanguage($language);
            $time_actual = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            $currency = getCurrencyByLanguage($language);
            
            $orderNo = 'IN' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
            //添加充值记录
            $insert = array(
                "uid" =>$user_id,
                "rid" =>100,
                "orderNo" =>$orderNo,
                "money" =>$money_num,
                "money2" =>changeMoneyByLanguage($money_num,$language),
                "hash" =>'',
                "voucher" =>'',
                "currency" =>$currency,
                "time_zone" =>$time_zone,
                "act_time" =>$time_actual,
                "time" =>$time
            );
            $rrid = Db::name('LcUserRechargeRecord')->insertGetId($insert);
            if (!empty($iid)) {
                $this->success(lang('think_library_form_success'), '');
            } else {
                $this->error(lang('think_library_form_error'));
            }
        }
        $this->_form($this->table, 'form');
    }
    
    
    
    /**
     * 设置模拟
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setreal()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['is_real' => '1']);
    }

    /**
     * 取消设置模拟
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setnoreal()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['is_real' => '0']);
    }


}
