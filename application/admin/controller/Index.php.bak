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
        if($auth->isLogin()){
            $user = $this->app->session->get('user');
            $authorize = $user['authorize'];
            $rechargeState = $this->request->param("rechargeState");
            if(!empty($authorize)){
                $auth_node = Db::name("system_auth_node")->where("auth=$authorize AND (node = 'admin/recharge_record/agree' OR node = 'admin/withdraw_record/agree')")->count();
            }
            if($user['id']==10000||$auth_node>0){
                $withdraw_count = Db::name("LcUserWithdrawRecord")->where(['status'=>0,'warn'=>1])->count();
                $recharge_count = Db::name("LcUserRechargeRecord")->where(['status'=>0,'warn'=>1])->count();
                if($withdraw_count>0&&$recharge_count>0){
                    $url = "";
                    if ($rechargeState) {
                        $url = "/static/mp3/cztx.mp3";
                    }
                    $this->success("<a style='color:#FFFFFF' data-open='/admin/recharge_record/index.html'>您有{$withdraw_count}条新的提现记录和{$recharge_count}条新的充值记录，请查看！</a>",['url'=>$url]);
                }
                if($withdraw_count>0&&$recharge_count==0){
                    $url = "";
                    if ($rechargeState) {
                        $url = "/static/mp3/tx.mp3";
                    }
                    $this->success("<a style='color:#FFFFFF' data-open='/admin/withdraw_record/index.html'>您有{$withdraw_count}条新的提现记录，请查看！</a>",['url'=>$url]);
                }
                if ($withdraw_count == 0 && 0 < $recharge_count){
                    $url = "";
                    if ($rechargeState) {
                        $url = "/static/mp3/cz.mp3";
                    }
                    $this->success("<a style='color:#FFFFFF' data-open='/admin/recharge_record/index.html'>您有{$recharge_count}条新的充值记录，请查看！</a>",['url'=>$url]);
                }
                $this->error("暂无记录");
            }
        }else{
            $this->error("请先登录");
        }
        
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
            $now = date('Y-m-d H:i:s');//现在
            $today = date('Y-m-d 00:00:00');//今天0点
            $yesterday = date('Y-m-d 00:00:00', strtotime($now)-86400);//昨天0点
            $tomorrow = date('Y-m-d 00:00:00', strtotime($now)+86400);//明天0点
            $i_time = $this->request->param('i_time');
            $user_id = $this->request->param('user_id');
            $system_user_id = $this->request->param('system_user_id');
            $auth = $this->app->session->get('user');
            $ids = [];
            if ($auth['username'] != 'admin') {
                $ids = Db::table('system_user_relation')->where('parentid',$auth['id'])->column('uid');
                $ids[] = $auth['id'];
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
            
            //用户数量
            $this->user_count = Db::name('LcUser')->whereIn('system_user_id', $ids)->count();
            //用户余额
            $this->user_money_sum = Db::name('LcUser')->whereIn('system_user_id', $ids)->sum('money');
            //用户可提现余额
            $this->withdrawable = Db::name('LcUser')->whereIn('system_user_id', $ids)->sum('withdrawable');
            //今日注册
            $this->user_count_today = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("time BETWEEN '$today' AND '$now'")->count();
            //昨日注册
            $this->user_count_yesterday = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("time BETWEEN '$yesterday' AND '$today'")->count();
            //今日登录
            $this->user_login_count_today = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("logintime BETWEEN '$today' AND '$now'")->count();
            //充值笔数
            $this->recharge_count = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("status = 1")->count();
            //充值人数
            $this->recharge_p = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("status = 1")->group('uid')->select());
            //首充人数
            $first_charge_count = Db::query("select count(*) as num from (select count(uid) as num, uid, rr.time from lc_user_recharge_record as rr inner join lc_user u on rr.uid=u.id where u.system_user_id in (".implode(',', $ids).") and status = 1 group by uid) as a where a.num=1 limit 1");
            $this->first_charge_count = $first_charge_count[0]['num'];
            //今日首充人数
            $first_charge_count_today = Db::query("select count(*) as num from (select count(uid) as num, uid, rr.time from lc_user_recharge_record as rr inner join lc_user u on rr.uid=u.id where u.system_user_id in (".implode(',', $ids).") and status = 1 group by uid) as a where a.num>0 and time BETWEEN '$today' AND '$now' limit 1");
            $this->first_charge_count_today = $first_charge_count_today[0]['num'];
            //昨日首充人数
            $first_charge_count_yesterday = Db::query("select count(*) as num from (select count(uid) as num, uid, rr.time from lc_user_recharge_record as rr inner join lc_user u on rr.uid=u.id where u.system_user_id in (".implode(',', $ids).") and status = 1 group by uid) as a where a.num>1 and time BETWEEN '$yesterday' AND '$today' limit 1");
            $this->first_charge_count_yesterday = $first_charge_count_yesterday[0]['num'];
            //复充人数
            $recharging_count = Db::query("select count(*) as num from (select count(uid) as num, uid from lc_user_recharge_record as rr inner join lc_user u on rr.uid=u.id where u.system_user_id in (".implode(',', $ids).") and rr.status = 1 group by rr.uid) as a where a.num>1 limit 1");
            $this->recharging_count = $recharging_count[0]['num'];
            //充值金额
            $this->recharge_sum = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.status = 1")->sum('rr.money');
            //提现笔数
            $this->withdraw_count = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.status = 1")->count();
            //提现金额
            $this->withdraw_sum = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.status = 1")->sum('rr.money');
            //投资笔数
            $this->invest_count = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn("rr.source", [1,2])->whereIn('u.system_user_id', $ids)->count();
            //投资人数
            $this->invest_user_count = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->group('uid')->count();
            //投资金额
            $this->invest_sum = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->sum('rr.money');
            //投资收益
            $this->invest_reward = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.type = 1 AND rr.fund_type in (6,19)")->sum('rr.money');
            //系统操作（增加）
            $funding_sys_1_sum = Db::name('LcUserFunding')->where("type = 1 AND fund_type = 1")->sum('money');
            //系统操作（减少）
            $funding_sys_2_sum = Db::name('LcUserFunding')->where("type = 2 AND fund_type = 1")->sum('money');
            //系统操作
            $this->funding_sys_sum = $funding_sys_1_sum - $funding_sys_2_sum;
            //系统奖励
            $this->sys_reward = Db::name('LcUserFunding')->where("type = 1 AND fund_type IN (7,8,9,10,11,12,13,14,19,20,21)")->sum('money');
            // 总结余（充值-提现）
            $this->aggregate_balance = $this->recharge_sum - $this->withdraw_sum;
            // 兑换红包
            $this->residue_num = Db::table('lc_red_envelope')->whereIn("f_user_id", $ids)->sum('money2');
            // 波比（提现 / 充值）
            $this->poby = $this->recharge_sum ? bcmul($this->withdraw_sum/$this->recharge_sum, 100, 2) . "%" : '--';
            // 待处理提现数量
            $this->wait_withdraw_count = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.status = 0")->sum("rr.money");
            
            
            $table = $this->finance_report($now,$today,$yesterday,$i_time, $ids);
            $this->today = $table['today'];
            $this->yesterday = $table['yesterday'];
            $this->month = $table['month'];
            $this->last_month = $table['last_month'];
            $this->day = $table['day'];
            
            $this->fetch();
        }
        $this->error("请先登录");
    }

    private function finance_report($now,$today,$yesterday,$i_time, $ids=[]){
        //综合报表
        //今日
        $today1 = $this->getDatas($today,$now,$ids);
        
        //昨日
        $yesterday1 = $this->getDatas($yesterday,$today,$ids);
        
        
        //本月
        $firstDate = date('Y-m-01 00:00:00', strtotime(date("Y-m-d")));
        $lastDate = date('Y-m-d 23:59:59',strtotime("last day of this month",strtotime(date("Y-m-d"))));
        $month = $this->getDatas($firstDate,$lastDate,$ids);
        
        
        //上月
        $lastMonthFirstDate = date('Y-m-01 00:00:00',strtotime('-1 month'));
        $lastMonthLastDate = date('Y-m-d 23:59:59',strtotime('-1 month'));
        $lastMonth = $this->getDatas($lastMonthFirstDate,$lastMonthLastDate,$ids);
        
        //明细
        if(empty($i_time)){
            $monthDays = $this->getMonthDays();
        }else{
            $monthDays = $this->getDays($i_time);  
        }
        
        foreach($monthDays as $k=>$v){
            $first = date('Y-m-d 00:00:00', strtotime($v));
            $last = date('Y-m-d 23:59:59', strtotime($v));
            if ($first > date('Y-m-d 00:00:00')) {
                break;
            }
            
            $day[$k] = $this->getDatas($first,$last,$ids);
            
            $day[$k]['date'] = $v;
            
        }

        $day = array_reverse($day);
        return array('day' => $day,'today' => $today1,'yesterday' => $yesterday1,'month' => $month,'last_month'=>$lastMonth);
    }
    /**
     * 获取当前月已过日期
     * @return array
     */
    private function getDatas($time1,$time2,$ids=[])
    {
        $data['recharge'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->sum('rr.money');
        $data['recharge_count'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->count();
        $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
        $data['withdraw'] = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->sum('rr.money');
        $data['withdraw_count'] = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->count();
        $data['withdraw_p'] = count(Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group("rr.uid")->select());
        $data['new_user'] = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("time BETWEEN '$time1' AND '$time2'")->count();
        $data['invest'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn("rr.source", [1,2])->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2'")->count();
        $data['invest_user_count'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2'")->group('rr.uid')->count();
        $data['invest_sum'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2'")->sum('rr.money');
        $data['invest_reward'] = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type in (6,19)")->sum('rr.money');
        $data_sys_sum_1 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type = 1")->sum('rr.money');
        $data_sys_sum_2 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 2 AND rr.fund_type = 1")->sum('rr.money');
        $data['sys_sum'] = $data_sys_sum_1 - $data_sys_sum_2;
        $data['sys_reward'] = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type IN (7,8,9,10,11,12,13,14,19,20,21)")->sum('rr.money');
        // 兑换红包
        $data['residue_num'] = Db::table('lc_red_envelope_record')->alias('rer')->join('lc_red_envelope re', 'rer.pid=re.id')->whereIn("re.f_user_id", $ids)->where("rer.time BETWEEN '$time1' AND '$time2'")->sum('rer.money');
        // var_dump(Db::getLastSql());die;
        // 首充人数
        $first_charge_count = Db::query("select count(*) as num from (select count(uid) as num, uid, urr.time from lc_user_recharge_record as urr inner join lc_user u on urr.uid=u.id where u.system_user_id in (".implode(',', $ids).") and urr.status = 1 group by urr.uid) as a where a.num>0 and a.time BETWEEN '$time1' AND '$time2' limit 1");
        $data['first_charge_count'] = $first_charge_count[0]['num'];
        // 首充金额
        $first_charge_price = Db::query("select sum(money) as num from (select count(uid) as num, uid, urr.time, urr.money from lc_user_recharge_record as urr inner join lc_user u on urr.uid=u.id where urr.status = 1 and u.system_user_id in (".implode(',', $ids).") group by urr.uid) as a where a.num=1 and time BETWEEN '$time1' AND '$time2' limit 1");
        $data['first_charge_price'] = $first_charge_price[0]['num'];
        // 结余
        $data['aggregate_balance'] = $data['recharge'] - $data['withdraw'];
        
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
        while (date('Y-m-d', strtotime("$firstDay +$i days")) <= $lastDay) {
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
        $firstDay = date('Y-m-01', time());
        $i = 0;
        $now_day = date('d');
        $lastDay = date('Y-m-d', strtotime("$firstDay +1 month -1 day"));
        while (date('Y-m-d', strtotime("$firstDay +$i days")) <= $lastDay) {
            // if($i>=$now_day) break;
            $monthDays[] = date('Y-m-d', strtotime("$firstDay +$i days"));
            $i++;
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
