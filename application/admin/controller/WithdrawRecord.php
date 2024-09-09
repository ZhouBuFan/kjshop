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

use app\libs\yomipay\yomipay;
use app\libs\xlpay\xlpay;
use app\libs\jmpay\jmpay;
use app\libs\onePay\Tool;
use library\Controller;
use think\Db;

/**
 * 提现管理
 * Class Item
 * @package app\admin\controller
 */
class WithdrawRecord extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUserWithdrawRecord';
    protected $sysWalletTable = 'LcSysWallet';
    protected $userWalletTable = 'LcUserWallet';

    /**
     * 提现记录
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
        
        $this->title = '提现记录';
        $auth = $this->app->session->get('user');
        $params = $this->request->param();
        $where = ' 1 ';
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where .= " and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )";
        }
        if (isset($params['u_username'])) {
            // 所属下级系统用户
            if ($params['sys_username']) {
                $sys_user_id = Db::table('system_user')->alias('su')->join('system_user_relation sur', 'sur.uid=su.id')->where("su.is_deleted=0 and sur.parentid={$auth['id']}")->whereLike('username', "%{$params['sys_username']}%")->column('su.id');
                $where .= " and u.system_user_id in (".($sys_user_id ? implode(',', $sys_user_id) : 0).") ";
            }
            // 上级邀请人
            if ($params['superior_username']) {
                $user_id = Db::table('lc_user')->alias('u')->join('lc_user_relation ur', 'ur.uid=u.id')->join('lc_user lu', 'lu.id=ur.parentid')->where("ur.level = 1 and lu.username like '%{$params['superior_username']}%' and (u.system_user_id in (select uid from system_user_relation where parentid={$auth['id']}) or u.system_user_id={$auth['id']} )")->column('u.id');
                $where .= " and i.uid in (".($user_id ? implode(',', $user_id) : 0).") ";
            }
            // 提现金额
            if ($params['recharge_amount'] && $params['recharge_amount_1']) {
                $where .= " and i.money BETWEEN {$params['recharge_amount']} and {$params['recharge_amount_1']} ";
            }
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d 00:00:00');
        // 今日充值金额
        $this->today_recharge = Db::name($this->table)->alias('i')->where($where)->join('lc_user u','i.uid=u.id')->where("i.status=1 and payment_received_time BETWEEN '$today' and '$now'")->sum('i.money') * 0.9;
        // 今日充值金额
        $this->today_recharge_num = Db::name($this->table)->alias('i')->where($where)->join('lc_user u','i.uid=u.id')->where("i.status=1 and payment_received_time BETWEEN '$today' and '$now'")->count();
        // 总充值成功笔数
        $total_recharge_suc_num = Db::name($this->table)->alias('i')->where($where)->join('lc_user u','i.uid=u.id')->where("i.status=1")->count();
        $this->total_recharge_suc_num =  $total_recharge_suc_num;
        // 总充值笔数
        $total_recharge_num = Db::name($this->table)->alias('i')->where($where)->join('lc_user u','i.uid=u.id')->count();
        // 充值成功率
        $this->success_rate = ($total_recharge_num ? bcdiv($total_recharge_suc_num, $total_recharge_num, 4)*100 : 0) . "%";

        $query = $this->_query($this->table)->alias('i')->field('i.*,u.username');
        $query->where($where)->join('lc_user u','i.uid=u.id')->equal('i.status#i_status,i.orderNo#order_no,i.type#type')->like('u.username#u_username,u.agent#u_agent')->dateBetween('i.act_time#i_time')->order('i.id desc')->page();
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
            $wallet = Db::name("lc_user_wallet")->find($vo['wid']);
            if($wallet){
                $vo['wallet_wname'] = $wallet['wname'];
                $vo['wallet_name'] = $wallet['name'];
                $vo['wallet_account'] = $wallet['account'];
                $vo['wallet_img'] = $wallet['img'];
                $vo['wallet_type'] = $wallet['type'];

                $wallet2 = Db::name("lc_user_wallet")->where("uid={$vo['uid']} and deleted_at='0000-00-00 00:00:00'")->find();
                if($wallet){
                    $vo['wallet_wname2'] = $wallet2['wname'];
                    $vo['wallet_name2'] = $wallet2['name'];
                    $vo['wallet_account2'] = $wallet2['account'];
                    $vo['wallet_img2'] = $wallet2['img'];
                    $vo['wallet_type2'] = $wallet2['type'];
                }
            }
            $user = Db::name('LcUser')->where('id', $vo['uid'])->find();
            $vo['s_name'] = Db::table('system_user')->where("id={$user['system_user_id']}")->value('username');
            $top_user = Db::name('LcUserRelation')->where("uid = {$vo['uid']} AND level = 1")->find();
            if(!empty($top_user)){
                $top_user = Db::name('LcUser')->find($top_user['parentid']);
                if(!empty($top_user)){
                    $vo['top'] = $top_user['username'];
                }
            }
            $vo['withdraw_sum']  = Db::name("lc_user_withdraw_record")->where("uid = {$vo['uid']} AND status = '1'")->sum('money');
            $vo['recharge_sum']  = Db::name("lc_user_recharge_record")->where("uid = {$vo['uid']} AND status = '1'")->sum('money');
            $vo['withdraw_sum']  = $vo['withdraw_sum'] *0.9;
            //总结余
            $vo['surplus_sum'] =  $vo['recharge_sum'] - $vo['withdraw_sum'] ;
        }
    }

    /**
     * 同意提现
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function agree()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        sysoplog('财务管理', '同意提现');
        $ids = explode(',',$id);
        $info = Db::name('lc_info')->where("id=1")->find();
        if($info['daifu'] == 1){
            $tool = new tool();  //onepay
        }else if($info['daifu'] == 2){
            $tool = new jmpay();   //jmpay
        }else if($info['daifu'] == 3){
            $tool = new xlpay();   //xlpay
        }else{
            $this->error('提现同意失败????');
        }
        
        // $tool = new Tool();   //onepay
        $ids = Db::name($this->table)->whereIn('id',$ids)->where('status', 0)->column('id');
        foreach ($ids as $id) {
            $agree = Db::name($this->table)->where("id=$id")->find();
            $wallert = Db::name($this->userWalletTable)->find($agree['wid']);
            // $wallert = Db::name($this->userWalletTable)->where("uid={$agree['uid']} and type = 4 and deleted_at = '0000-00-00 00:00:00'")->find();
            ###########推送出款单
            $out['order_no'] = $agree['orderNo'];
            $out['money'] = ($agree['money']-$agree['charge']);

            if($info['daifu'] == 1){
                
                $out['bankname'] = Db::table("lc_user_withdraw_bank")->where("id={$wallert['bid']}")->value('code'); // 银行名称
            }else if($info['daifu'] == 2){
                 //baoxuepay
                 $out['bankname'] = Db::table("lc_user_withdraw_bank1")->where("id={$wallert['bid']}")->value('code'); // 银行名称
            }else if($info['daifu'] == 3){
                //baoxuepay
                $out['bankname'] = Db::table("lc_user_withdraw_bank2")->where("id={$wallert['bid']}")->value('code'); // 银行名称
           }


            //以下参数自行修改
            $out['accountname'] = $wallert['name'];// 收款人姓名
            $out['cardnumber'] = $wallert['account'];// 银行卡号
            if($out['cardnumber']=='08111682912' || $out['cardnumber']=='0895344793875' || $out['cardnumber']=='089528114298' || $out['cardnumber']=='0895621185020' || $out['cardnumber']=='082112480170' || $out['cardnumber']=='081282399330' || $out['cardnumber']=='08999360624'){
                $this->error('提现同意失败:'.'禁止的银行账户');
            }

            $res = $tool->sends_pay_out($out); 
            $res = !empty($res) ? json_decode($res, true) : [];

               //onepay-1
             if($info['daifu'] == 1){
                if (empty($res) || $res['code'] != '200') {
                     $this->error('提现同意失败:'.$res['message']);
                }
                Db::name($this->table)->where("id=$id")->update(['status' => '4','type' => '1','time2' => date('Y-m-d H:i:s'), 'serial_number' =>  $res['data']['channelNo']]);
            }elseif($info['daifu'] == 2){
                //jmpay-2
                if (empty($res) || $res['code'] != '200') {
                    $this->error('提现同意失败:'.$res['en_message']);
                }
                Db::name($this->table)->where("id=$id")->update(['status' => '4','type' => '2','time2' => date('Y-m-d H:i:s'), 'serial_number' => $res['data']['trace_no']]);

            }elseif($info['daifu'] == 3){
                 //xlpay-3
                 if (!empty($res['status'])) {
                    $this->error('提现同意失败:'.$res['detail']);
                }
                Db::name($this->table)->where("id=$id")->update(['status' => '4','type' => '3','time2' => date('Y-m-d H:i:s'), 'serial_number' => $res['platformOrderCode']]);

            }

            
            

            
            
            //onepay
            // $res = $tool->sends_pay_out($out); 
            // $res = !empty($res) ? json_decode($res, true) : [];
            // if (empty($res) || $res['code'] != '200') {
            //     $this->error('提现同意失败:'.$res['message']);
            // }
            // Db::name($this->table)->where("id=$id")->update(['status' => '4','type'=>'1','time2' => date('Y-m-d H:i:s'), 'serial_number' => $res['data']['channelNo']]);

        }
        $this->success('success');
    }


    /**
     * 拒绝提现
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function refuse()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $ids = explode(',',$id);
        $ids = Db::name($this->table)->whereIn('id',$ids)->where('status', 0)->column('id');
        foreach ($ids as $id) {
            $withdrawRecord = Db::name($this->table)->find($id);
            $uid = $withdrawRecord['uid'];
            
            //拒绝时返还提现金额
            //流水添加
            addFunding($uid,$withdrawRecord['money'],$withdrawRecord['money2'],1,4,getLanguageByTimezone($withdrawRecord['time_zone']));
            //余额返还
            setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = $uid");
            Db::name($this->table)->where("id=$id")->update(['status' => '2', 'time2' => date('Y-m-d H:i:s')]);

        }
        sysoplog('财务管理', '拒绝提现');
        $this->success('success');
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
        sysoplog('财务管理', '删除提现记录');
        $this->_delete($this->table);
    }
    
    /**
     * 系统钱包列表
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function sys_wallet_list()
    {
        $rrid = $this->request->param('rrid');
        $this->assign('rrid', $rrid);
        
        $query = $this->_query($this->sysWalletTable);
        $query->order('id asc')->page();
        
    }
    /**
     * 优盾代付付
     * @auth true 
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function pay()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $withdraw = Db::name('LcUserWithdrawRecord')->find($id);
        if(empty($withdraw)) $this->error("订单不存在");
        $wallet = Db::name('LcUserWallet')->find($withdraw['wid']);
        if(empty($wallet)) $this->error("用户钱包不存在");
        
        if($withdraw['status']!=0) $this->error("订单状态异常");
        //判断地址有效性
        $json= json_decode(checkAddress($wallet['account']), true);
        if($json['code']!=200){
            $this->error($json['message']);
        }
        //发起代付
        $json= json_decode(proxypay($wallet['account'],$withdraw['money']-$withdraw['charge'],$withdraw['orderNo']), true);
        if($json['code']!=200){
            $this->error($json['message']);
        }
        //状态：审核中 3
        Db::name('LcUserWithdrawRecord')->where('id', $withdraw['id'])->update(['status' => 3]);
        sysoplog('财务管理', '发起优盾代付');
        $this->success('代付成功，请到优盾钱包APP确认');
    }
}
