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
use library\service\AdminService;
use library\service\MenuService;
use library\tools\Data;
use think\Console;
use think\Db;
use think\exception\HttpResponseException;

/**
 * 系统公共操作
 * Class Index
 * @package app\admin\controller
 */
class Index extends Controller
{

    /**
     * 显示后台首页
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '系统管理后台';
        $auth = AdminService::instance()->apply(true);
        if(!$auth->isLogin()) $this->redirect('@admin/login');
        $this->menus = MenuService::instance()->getTree();

        $user = $this->app->session->get('user');

        if($user['authorize']!=4){
            foreach($this->menus as $k=>$v){
                if($v['id'] == 69){
                    foreach($v['sub'] as $k1=>$v1){
                        if($v1['id'] == 156){
                            unset($this->menus[$k]['sub'][$k1]);
                        }
                    }
                }
            }
        }

        if (empty($this->menus) && !$auth->isLogin()) {
            $this->redirect('@admin/login');
        } else {
            $this->fetch();
        }
    }

    /**
     * Describe:查询充值提现记录
     * DateTime: 2020/5/15 0:54
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function check(){
        $auth = AdminService::instance()->apply(true);
        // if($auth->isLogin()){
        //     $user = $this->app->session->get('user');
        //     $authorize = $user['authorize'];
        //     $rechargeState = $this->request->param("rechargeState");
        //     if(!empty($authorize)){
        //         $auth_node = Db::name("system_auth_node")->where("auth=$authorize AND (node = 'admin/recharge_record/agree' OR node = 'admin/withdraw_record/agree')")->count();
        //     }
        //     if($user['id']==10000||$auth_node>0){
        //         $withdraw_count = Db::name("LcUserWithdrawRecord")->where(['status'=>0,'warn'=>1])->count();
        //         $recharge_count = Db::name("LcUserRechargeRecord")->where(['status'=>0,'warn'=>1])->count();
        //         if($withdraw_count>0&&$recharge_count>0){
        //             $url = "";
        //             if ($rechargeState) {
        //                 $url = "/static/mp3/cztx.mp3";
        //             }
        //             $this->success("<a style='color:#FFFFFF' data-open='/admin/recharge_record/index.html'>您有{$withdraw_count}条新的提现记录和{$recharge_count}条新的充值记录，请查看！</a>",['url'=>$url]);
        //         }
        //         if($withdraw_count>0&&$recharge_count==0){
        //             $url = "";
        //             if ($rechargeState) {
        //                 $url = "/static/mp3/tx.mp3";
        //             }
        //             $this->success("<a style='color:#FFFFFF' data-open='/admin/withdraw_record/index.html'>您有{$withdraw_count}条新的提现记录，请查看！</a>",['url'=>$url]);
        //         }
        //         if ($withdraw_count == 0 && 0 < $recharge_count){
        //             $url = "";
        //             if ($rechargeState) {
        //                 $url = "/static/mp3/cz.mp3";
        //             }
        //             $this->success("<a style='color:#FFFFFF' data-open='/admin/recharge_record/index.html'>您有{$recharge_count}条新的充值记录，请查看！</a>",['url'=>$url]);
        //         }
        $this->error("暂无记录");



    }

    /**
     * Describe:忽略提醒
     * DateTime: 2020/5/15 0:56
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function system_ignore(){
        $auth = AdminService::instance()->apply(true);
        if($auth->isLogin()){
            Db::name("LcUserWithdrawRecord")->where(['warn'=>1])->update(['warn'=>0]);
            Db::name("LcUserRechargeRecord")->where(['warn'=>1])->update(['warn'=>0]);
            $this->success("操作成功");
        }
        $this->error("请先登录");
    }

    /**
     * 系统报表
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function main()
    {
        $auth = AdminService::instance()->apply(true);
        if($auth->isLogin()){
            $now = date('Y-m-d');//现在
            $today = date('Y-m-d');//今天0点
            $yesterday = date('Y-m-d', strtotime($now)-86400);//昨天
            // $yesterday = date('Y-m-d 00:00:00', strtotime($now)-86400);//昨天0点
            // $tomorrow = date('Y-m-d 00:00:00', strtotime($now)+86400);//明天0点
            $i_time = $this->request->param('i_time');
            $user_id = $this->request->param('user_id');
            $system_user_id = $this->request->param('system_user_id');
            $auth = $this->app->session->get('user');
            $ids = [];
            if ($auth['username'] != 'admin') {
                $ids = Db::table('system_user_relation')->where('parentid',$auth['id'])->column('uid');
                $ids[] = $auth['id'];
                if($auth['id']==10257){
                    $ids[] = 10261;
                }
                if($auth['id']==10194){
                    $ids[]=10193;
                }
                $this->users = Db::table('system_user')->alias('su')->join("system_user_relation sur", "sur.uid=su.id")->where('sur.parentid',$auth['id'])->select();
            }else {
                $this->users = Db::table('system_user')->select();
                $ids = Db::table('system_user')->column('id');
            }

            if ($user_id) {
                $ids = [];
                $ids[] = $user_id;
            }

            if ($system_user_id) {
                $ids = [];
                $ids = Db::table('system_user_relation')->where('parentid',$system_user_id)->column('uid');
                $ids[] = $system_user_id;
            }

            if ($auth['username'] != 'admin' || ($auth['username'] == 'admin' && $system_user_id)) {
                //今日注册
                $user_count_today = Db::name('LcUser')->field('id')->whereIn('system_user_id', $ids)->where("is_real=0 and time>='$today'")->count();
                $this->user_count_today  =   $user_count_today;

                //今日首充人数
                $first_charge_count_today = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id = rr.uid')->field('rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time>='$today'")->where(['status' => 1,'count' => 1])->count();
                $this->first_charge_count_today = $first_charge_count_today;
                //今日充值金额
                $recharge = Db::query("select sum(r.money) as money from lc_user_recharge_record r  , lc_user u 
                where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and  r.status =1 and r.time>='$today' ");
                if(!empty($recharge)){
                    $recharge  = $recharge[0]['money'];
                }else{
                    $recharge = 0;
                }

                //提现金额
                $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 >='$today' AND rr.status = 1")
                    ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2'])->find();
                $todaywithdraw = $withdraws['withdraw1'] - $withdraws['withdraw2'];

                //今日投资笔数
                $invest_count_today = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("u.is_real = 0")->where("rr.time>='$today'")->count();

                $system_statement = Db::name('LcSystemStatement')->whereIn('system_id', $ids)
                    ->field(['SUM(regis_num)' => 'user_count', 'SUM(frist_topup_num)' => 'recharge_p',
                             'SUM(topup_money)' => 'recharge_sum','SUM(withdraw)'=> 'withdraw_sum','SUM(order_num)'=> 'invest_count',
                             'SUM(order_dividend)'=> 'invest_reward','SUM(red_packet)'=>'residue_num'])
                    ->find();
                //用户数量
                $user_count = $system_statement['user_count'];
                $this->user_count = $user_count_today + $user_count;
                //用户可提现余额
                $this->withdrawable = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("is_real = 0")->sum('withdrawable');
                //昨日注册
                $this->user_count_yesterday = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time='$yesterday'")->sum('regis_num');
                //昨日订单笔数
                $this->invest_count_yesterday = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time='$yesterday'")->sum('order_num');

                //充值人数
                $recharge_p = $system_statement['recharge_p'];
                $this->recharge_p = $recharge_p+$first_charge_count_today;

                //首充人数
                $this->first_charge_count = $recharge_p;
                //昨日首充人数
                $this->first_charge_count_yesterday = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time='$yesterday'")->sum('frist_topup_num');
                //充值金额
                $this->recharge_sum = $system_statement['recharge_sum'] + $recharge;
                //提现金额
                $this->withdraw_sum = $system_statement['withdraw_sum'] + $todaywithdraw;
                //投资笔数
                $this->invest_count = $system_statement['invest_count']+$invest_count_today;
                //投资收益
                $this->invest_reward  =$system_statement['invest_reward'];
                // 兑换红包
                $this->residue_num = $system_statement['residue_num'];

                // 总结余（充值-提现）
                $this->aggregate_balance = $this->recharge_sum - $this->withdraw_sum;

                // 波比（提现 / 充值）
                $this->poby = $this->recharge_sum ? bcmul($this->withdraw_sum/$this->recharge_sum, 100, 2) . "%" : '--';
                // 待处理提现数量
                $this->wait_withdraw_count = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.status = 0")->sum("rr.money");
                //管理员角色
            }else {
                //今日注册
                $user_count_today = Db::name('LcUser')->field('id')->where("is_real = 0 and time>='$today'")->count();
                $this->user_count_today  =   $user_count_today;

                //今日首充人数
                $first_charge_count_today = Db::name('LcUserRechargeRecord')->where("time>='$today'")->where(['status' => 1,'count' => 1])->count();
                $this->first_charge_count_today = $first_charge_count_today;

                //今日充值金额
                $recharge = Db::query("select sum(r.money) as money from lc_user_recharge_record r where r.status =1 and r.time>='$today' ");
                if(!empty($recharge)){
                    $recharge  = $recharge[0]['money'];
                }else{
                    $recharge = 0;
                }

                //提现金额
                $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->where("rr.time2 >='$today' AND rr.status = 1")
                    ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2'])->find();
                $todaywithdraw = $withdraws['withdraw1'] - $withdraws['withdraw2'];


                //今日投资笔数
                $invest_count_today = Db::name('LcInvest')->where("time>='$today'")->count();

                $system_statement = Db::name('LcSystemStatement')
                    ->field(['SUM(regis_num)' => 'user_count', 'SUM(frist_topup_num)' => 'recharge_p',
                             'SUM(topup_money)' => 'recharge_sum','SUM(withdraw)'=> 'withdraw_sum','SUM(order_num)'=> 'invest_count',
                             'SUM(order_dividend)'=> 'invest_reward','SUM(red_packet)'=>'residue_num'])
                    ->find();
                //用户数量
                $user_count = $system_statement['user_count'];

                $this->user_count = $user_count_today + $user_count;
                //用户可提现余额
                $this->withdrawable = Db::name('LcUser')->where("is_real = 0")->sum('withdrawable');
                //昨日注册
                $this->user_count_yesterday = Db::name('LcSystemStatement')->where("time='$yesterday'")->sum('regis_num');
                //昨日订单笔数
                $this->invest_count_yesterday = Db::name('LcSystemStatement')->where("time='$yesterday'")->sum('order_num');


                //充值人数
                $recharge_p = $system_statement['recharge_p'];
                $this->recharge_p = $recharge_p+$first_charge_count_today;

                //首充人数
                $this->first_charge_count = $recharge_p;
                //昨日首充人数
                $this->first_charge_count_yesterday = Db::name('LcSystemStatement')->where("time='$yesterday'")->sum('frist_topup_num');
                //充值金额
                $this->recharge_sum = $system_statement['recharge_sum'] + $recharge;

                //提现金额
                $this->withdraw_sum = $system_statement['withdraw_sum'] + $todaywithdraw;

                //投资笔数
                $this->invest_count = $system_statement['invest_count']+$invest_count_today;

                //投资收益
                $this->invest_reward  =$system_statement['invest_reward'];

                // 兑换红包
                $this->residue_num = $system_statement['residue_num'];


                // 总结余（充值-提现）
                $this->aggregate_balance = $this->recharge_sum - $this->withdraw_sum;


                // 波比（提现 / 充值）
                $this->poby = $this->recharge_sum ? bcmul($this->withdraw_sum/$this->recharge_sum, 100, 2) . "%" : '--';
                // 待处理提现数量
                $this->wait_withdraw_count = Db::name('LcUserWithdrawRecord')->where("status = 0")->sum("money");
            }
            $now = date('Y-m-d H:i:s');//现在

            $today = date('Y-m-d 00:00:00');//今天0点
            $yesterday = date('Y-m-d 00:00:00', strtotime($now)-86400);//昨天
            $table = $this->finance_report($now,$today,$yesterday,$i_time, $ids,$auth['username']);
            $this->today = $table['today'];
            $this->yesterday = $table['yesterday'];
            $this->month = $table['month'];
            $this->last_month = $table['last_month'];
            $this->day = $table['day'];

            $this->fetch();
        }
        $this->error("请先登录");
    }

    private function finance_report($now,$today,$yesterday,$i_time, $ids=[],$uname){
        $nows = date('Y-m-d');//现在
        $yesterdays = date('Y-m-d', strtotime($now)-86400);//昨天
        //综合报表
        //今日
        $today1 = $this->getDatas($nows,$today,$now,$ids,$uname);

        //昨日
        $yesterday1 = $this->getDatas($yesterdays,$yesterday,$today,$ids,$uname);


        //本月
        // $firstDate = date('Y-m-01 00:00:00', strtotime(date("Y-m-d")));
        // $lastDate = date('Y-m-d 23:59:59',strtotime("last day of this month",strtotime(date("Y-m-d"))));
        // $month = $this->getDatas($firstDate,$lastDate,$ids);
        $month = null;

        //上月
        // $lastMonthFirstDate = date('Y-m-01 00:00:00',strtotime('-1 month'));
        // $lastMonthLastDate = date('Y-m-d 23:59:59',strtotime('-1 month'));
        // $lastMonth = $this->getDatas($lastMonthFirstDate,$lastMonthLastDate,$ids);

        $lastMonth = null;
        //明细
        if(empty($i_time)){
            $monthDays = $this->getMonthDays();
        }else{
            $monthDays = $this->getDays($i_time);
        }

        foreach($monthDays as $k=>$v){
            $first = date('Y-m-d 00:00:00', strtotime($v));
            $last = date('Y-m-d 23:59:59', strtotime($v));
            if ($first >= $today) {
                if($first == $yesterday){
                    $day[$k] = $yesterday1;
                    $day[$k]['date'] = $v;
                }elseif($first == $today){
                    $day[$k] = $today1;
                    $day[$k]['date'] = $v;
                }
                break;
            }

            $day[$k] = $this->getDatas($v,$first,$last,$ids,$uname);

            $day[$k]['date'] = $v;

        }

        $day = array_reverse($day);
        return array('day' => $day,'today' => $today1,'yesterday' => $yesterday1,'month' => $month,'last_month'=>$lastMonth);
    }
    /**
     * 获取当前月已过日期
     * @return array
     */
    private function getDatas($date,$time1,$time2,$ids=[],$uname)
    {
        $now = date('Y-m-d');//现在
        if($date == $now){  //如果是今天
            if($uname != 'admin' || ($uname == 'admin' && $ids)){

                $system_statement = Db::name('LcSystemStatement')->where("time='$now'")->whereIn('system_id', $ids)
                    ->field(['SUM(order_dividend)' => 'invest_reward','SUM(red_packet)'=>'residue_num'])
                    ->find();


                $data['new_user'] = Db::name('LcUser')->where("time BETWEEN '$time1' AND '$time2' and is_real =0")->whereIn('system_user_id', $ids)->count();
                $data['invest'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real=0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2'")->count();
                //订单分红
                $data['invest_reward'] = $system_statement['invest_reward'];
                //兑换红包
                $data['residue_num'] = $system_statement['residue_num'];
                //充值金额
                $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u 
                where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
                if(!empty($recharge)){
                    $data['recharge']  = $recharge[0]['money'];

                    //充值笔数
                    $data['recharge_count'] = $recharge[0]['count'];
                }else{
                    $data['recharge']  = 0;
                    $data['recharge_count'] = 0;
                }

                //充值人数
                $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
                //首充金额
                $first_charge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r, lc_user u 
                where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and 
                r.count=1 and r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
                if(!empty($first_charge)){
                    $data['first_charge_price'] =$first_charge[0]['money'];
                    //首充人数
                    $data['first_charge_count'] =$first_charge[0]['count'];
                }else{
                    $data['first_charge_price']  = 0;
                    $data['first_charge_count'] = 0;
                }

                //提现金额
                $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                    ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2'])->find();
                $data['withdraw'] = $withdraws['withdraw1'] - $withdraws['withdraw2'];
                //提现待处理
                $data['withdraw_now'] = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.act_time BETWEEN '$time1' AND '$time2'")->where("rr.status = 0")->sum('rr.money');
                // 结余
                $data['aggregate_balance'] = $data['recharge'] - $data['withdraw'];

            }else{

                $system_statement = Db::name('LcSystemStatement')->where("time='$now'")
                    ->field(['SUM(order_dividend)' => 'invest_reward','SUM(red_packet)'=>'residue_num'])
                    ->find();


                $data['new_user'] = Db::name('LcUser')->where("time BETWEEN '$time1' AND '$time2' and is_real = 0")->count();
                $data['invest'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn("rr.source", [1,2])->where("rr.time BETWEEN '$time1' AND '$time2'")->count();
                //订单分红
                $data['invest_reward'] = $system_statement['invest_reward'];
                //兑换红包
                $data['residue_num'] = $system_statement['residue_num'];
                //充值金额
                $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r where  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
                if(!empty($recharge[0])){
                    $data['recharge']  = $recharge[0]['money'];
                    //充值笔数
                    $data['recharge_count'] = $recharge[0]['count'];
                }else{
                    $data['recharge']  = 0;
                    $data['recharge_count'] = 0;
                }
                //充值人数
                $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));

                //首充金额
                $first_charge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r where  r.count=1 and r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
                if(!empty($first_charge[0])){
                    $data['first_charge_price'] =$first_charge[0]['money'];
                    //首充人数
                    $data['first_charge_count'] =$first_charge[0]['count'];
                }else{
                    $data['first_charge_price']  = 0;
                    $data['first_charge_count'] = 0;
                }

                //提现金额
                $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                    ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2'])->find();
                $data['withdraw'] = $withdraws['withdraw1'] - $withdraws['withdraw2'];
                //提现待处理
                $data['withdraw_now'] = Db::name('LcUserWithdrawRecord')->alias('rr')->where("rr.act_time BETWEEN '$time1' AND '$time2'")->where("rr.status = 0")->sum('rr.money');
                // 结余
                $data['aggregate_balance'] = $data['recharge'] - $data['withdraw'];

            }
        }else{
            //如果不是今天
            if($uname != 'admin' || ($uname == 'admin' && $ids)){
                // 通过日期查询统计数据
                $system_statement = Db::name('LcSystemStatement')->where("time='$date'")->whereIn('system_id', $ids)
                    ->field(['SUM(regis_num)' => 'new_user','SUM(order_num)'=>'invest'
                             ,'SUM(order_dividend)'=>'invest_reward','SUM(red_packet)'=>'residue_num'
                             ,'SUM(topup_money)'=>'recharge','SUM(topup_num)'=>'recharge_p','SUM(topup_order_num)'=>'recharge_count'
                             ,'SUM(frist_topup_money)'=>'first_charge_price','SUM(frist_topup_num)'=>'first_charge_count'
                             ,'SUM(withdraw)'=>'withdraw'])
                    ->find();


                //注册
                $data['new_user'] = $system_statement['new_user'];
                //订单数
                $data['invest'] = $system_statement['invest'];
                //订单分红
                $data['invest_reward'] = $system_statement['invest_reward'];
                //兑换红包
                $data['residue_num'] = $system_statement['residue_num'];
                //充值金额
                // $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u
                // where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
                $data['recharge']  = $system_statement['recharge'];
                //充值人数
                $data['recharge_p'] = $system_statement['recharge_p'];
                //充值笔数
                $data['recharge_count']= $system_statement['recharge_count'];
                //首充金额
                $data['first_charge_price'] =$system_statement['first_charge_price'];
                //首充人数
                $data['first_charge_count'] =$system_statement['first_charge_count'];
                //提现金额
                // $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                // ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2'])->find();
                $data['withdraw'] = $system_statement['withdraw'];
                //提现待处理
                $data['withdraw_now'] = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.act_time BETWEEN '$time1' AND '$time2'")->where("rr.status = 0")->sum('rr.money');
                // 结余
                $data['aggregate_balance'] = $data['recharge'] - $data['withdraw'];

            }else{
                // 通过日期查询统计数据
                $system_statement = Db::name('LcSystemStatement')->where("time='$date'")
                    ->field(['SUM(regis_num)' => 'new_user','SUM(order_num)'=>'invest'
                             ,'SUM(order_dividend)'=>'invest_reward','SUM(red_packet)'=>'residue_num'
                             ,'SUM(topup_money)'=>'recharge','SUM(topup_num)'=>'recharge_p','SUM(topup_order_num)'=>'recharge_count'
                             ,'SUM(frist_topup_money)'=>'first_charge_price','SUM(frist_topup_num)'=>'first_charge_count'
                             ,'SUM(withdraw)'=>'withdraw'])
                    ->find();



                //注册
                $data['new_user'] = $system_statement['new_user'];
                //订单数
                $data['invest'] = $system_statement['invest'];
                //订单分红
                $data['invest_reward'] = $system_statement['invest_reward'];
                //兑换红包
                $data['residue_num'] = $system_statement['residue_num'];
                //充值金额
                // $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u
                // where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
                $data['recharge']  = $system_statement['recharge'];
                //充值人数
                $data['recharge_p'] = $system_statement['recharge_p'];
                //充值笔数
                $data['recharge_count']= $system_statement['recharge_count'];
                //首充金额
                $data['first_charge_price'] =$system_statement['first_charge_price'];
                //首充人数
                $data['first_charge_count'] =$system_statement['first_charge_count'];
                //提现金额
                // $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                // ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2'])->find();
                $data['withdraw'] = $system_statement['withdraw'];
                //提现待处理
                $data['withdraw_now'] = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("rr.act_time BETWEEN '$time1' AND '$time2'")->where("rr.status = 0")->sum('rr.money');
                // 结余
                $data['aggregate_balance'] = $data['recharge'] - $data['withdraw'];
            }

        }

        return $data;

    }

    /**
     * 获取当前月已过日期
     * @return array
     */
    private function getDays($i_time)
    {

        $monthDays = [];
        $time = explode(" - ",$i_time);
        $firstDay = $time[0];
        $i = 0;
        $lastDay = $time[1];
        while (date('Y-m-01', strtotime("$firstDay +$i days")) <= $lastDay) {
            // if($i>=$now_day) break;
            $monthDays[] = date('Y-m-d', strtotime("$firstDay +$i days"));
            $i++;
        }
        return $monthDays;
    }

    /**
     * 获取当前月已过日期
     * @return array
     */
    private function getMonthDays()
    {
        $monthDays = [];

        // 获取今天的日期
        $today = date('Y-m-d');

        // 从今天开始往前五天计算日期
        for ($i = 4; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i day", strtotime($today)));
            $monthDays[] = $date;
        }
        return $monthDays;
    }

    /**
     * 修改密码
     * @login true
     * @param integer $id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function pass($id)
    {
        $this->applyCsrfToken();
        if (intval($id) !== intval(session('user.id'))) {
            $this->error('只能修改当前用户的密码！');
        }
        if (!AdminService::instance()->isLogin()) {
            $this->error('需要登录才能操作哦！');
        }
        if ($this->request->isGet()) {
            $this->verify = true;
            $this->_form('SystemUser', 'admin@user/pass', 'id', [], ['id' => $id]);
        } else {
            $data = $this->_input([
                'password'    => $this->request->post('password'),
                'repassword'  => $this->request->post('repassword'),
                'oldpassword' => $this->request->post('oldpassword'),
            ], [
                'oldpassword' => 'require',
                'password'    => 'require|min:4',
                'repassword'  => 'require|confirm:password',
            ], [
                'oldpassword.require' => '旧密码不能为空！',
                'password.require'    => '登录密码不能为空！',
                'password.min'        => '登录密码长度不能少于4位有效字符！',
                'repassword.require'  => '重复密码不能为空！',
                'repassword.confirm'  => '重复密码与登录密码不匹配，请重新输入！',
            ]);
            $user = Db::name('SystemUser')->where(['id' => $id])->find();
            if (md5($data['oldpassword']) !== $user['password']) {
                $this->error('旧密码验证失败，请重新输入！');
            }
            if (Data::save('SystemUser', ['id' => $user['id'], 'password' => md5($data['password'])])) {
                $this->app->session->delete('user');
                $this->success('密码修改成功，下次请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 修改用户资料
     * @login true
     * @param integer $id 会员ID
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function info($id = 0)
    {
        if (!AdminService::instance()->isLogin()) {
            $this->error('需要登录才能操作哦！');
        }
        $this->applyCsrfToken();
        if (intval($id) === intval(session('user.id'))) {
            $this->_form('SystemUser', 'admin@user/form', 'id', [], ['id' => $id]);
        } else {
            $this->error('只能修改登录用户的资料！');
        }
    }

    /**
     * 清理运行缓存
     * @auth true
     */
    // public function clearRuntime()
    // {
    //     try {
    //         Console::call('clear');
    //         Console::call('xclean:session');
    //         $this->success('清理运行缓存成功！');
    //     } catch (HttpResponseException $exception) {
    //         throw $exception;
    //     } catch (\Exception $e) {
    //         $this->error("清理运行缓存失败，{$e->getMessage()}");
    //     }
    // }

    /**
     * 压缩发布系统
     * @auth true
     */
    // public function buildOptimize()
    // {
    //     try {
    //         Console::call('optimize:route');
    //         Console::call('optimize:schema');
    //         Console::call('optimize:autoload');
    //         Console::call('optimize:config');
    //         $this->success('压缩发布成功！');
    //     } catch (HttpResponseException $exception) {
    //         throw $exception;
    //     } catch (\Exception $e) {
    //         $this->error("压缩发布失败，{$e->getMessage()}");
    //     }
    // }
    /**
     * 导出报表
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    // public function export_excel()
    // {
    //     $this->title = '';
    //     $this->fetch();
    // }
    /**
     * 确定导出报表
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    // public function export_save()
    // {
    // }
    /**
     * 导出Excel
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    // public function exportExcel()
    // {

    // }

}
