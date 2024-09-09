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
 * 下级统计
 * Class Index
 * @package app\admin\controller
 */
class DownstreamReport extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'SystemUser';
    protected $table_user = 'LcUser';
    protected $table_funding = 'LcUserFunding';

    /**
     * 下级统计
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
        $where = 'is_deleted=0 ';
        if (isset($auth['username']) and $auth['username'] != 'admin') {
            $where .= " and authorize=2 and id in (select uid from system_user_relation where parentid={$auth['id']})";
        }else {
            $where .= " and authorize=3 ";
        }
        $query = $this->_query($this->table)->where($where)->equal('username#u_username,id#u_id');
        $query->dateBetween('login_at,create_at')->order('id desc')->page();
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
        $date = date("Y-m-d");
        $now = date("Y-m-d H:i:s");
        $today = date('Y-m-d 00:00:00');//今天0点

        foreach($data as &$vo){
            $ids = [];
            if ($vo['authorize'] == 3) {
                $ids = Db::table('system_user_relation')->where('parentid',$vo['id'])->column('uid');
            }
            $ids[] = $vo['id'];
            //今日注册
            $vo['reg_num'] = Db::table('lc_user')->where("system_user_id in (".implode(',', $ids).")and is_real =0 and time BETWEEN '$today' AND '$now'")->count();

            //今日充值金额
            $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u 
            where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and  r.status =1 and r.time BETWEEN '$today' AND '$now' ");
            if(!empty($recharge)){
                $vo['today_price']  = intval($recharge[0]['money']);
                //今日充值笔数
                $vo['today_count'] = $recharge[0]['count'];
            }else{
                $vo['today_price']  = 0;
                $vo['today_count'] = 0;
            }
            
            $first_charge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r, lc_user u 
            where u.id=r.uid and u.system_user_id in (".implode(',', $ids).") and 
            r.count=1 and r.status =1 and r.time BETWEEN '$today' AND '$now' ");
            if(!empty($first_charge)){
                //今日首充金额
                $vo['today_first_charge_money'] =intval($first_charge[0]['money']);
                //今日首充人数
                $vo['today_first_charge'] =$first_charge[0]['count'];
            }else{
                $vo['today_first_charge_money']  = 0;
                $vo['today_first_charge'] = 0;
            }

            //今日提现金额
            $withdraws= Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$today' AND '$now' AND rr.status = 1")
            ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2','count(rr.id)' => 'count'])->find();
            $vo['today_withdraw_price'] =  $withdraws['withdraw1'] - $withdraws['withdraw2'];
            //今日提现笔数
            $vo['today_withdraw_count'] = $withdraws['count'];

            //今日结余
            $vo['last_price'] = number_format($vo['today_price'] - $vo['today_withdraw_price'],2);
            
            // 总人数
            // $vo['total_people'] = Db::table('lc_user')->whereIn('system_user_id',$ids)->count();

            $system_statement = Db::name('LcSystemStatement')->whereIn('system_id', $ids)
                ->field(['SUM(regis_num)' => 'total_people', 'SUM(frist_topup_num)' => 'recharge_p',
                'SUM(topup_money)' => 'recharge_sum','SUM(withdraw)'=> 'withdraw_sum','SUM(withdrwa_order_num)'=> 'withdrwa_order_num',
                'SUM(order_dividend)'=> 'invest_reward','SUM(red_packet)'=>'residue_num'])
                ->find();
            //用户数量
            $user_count = $system_statement['total_people'];
            $vo['total_people'] = $user_count + $vo['reg_num'];

            // 有效用户
            $vo['valid_user'] =  $system_statement['recharge_p'] + $vo['today_first_charge'];

            // 总充值金额
            $vo['recharge_sum'] = $system_statement['recharge_sum'] + $vo['today_price'];

            // 总提现金额
            $vo['withdraw_sum'] = $system_statement['withdraw_sum'] + $vo['today_withdraw_price'];

            //提现笔数 
            $vo['withdraw_count'] = $system_statement['withdrwa_order_num'] + $vo['today_withdraw_count'];

            $today_system_statement = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time='$date'")
                ->field(['SUM(order_dividend)'=> 'invest_reward','SUM(red_packet)'=>'residue_num'])
                ->find();

            //今日订单分红
            $vo['invest_reward'] = $today_system_statement['invest_reward'];
                  
            // 兑换红包
            $vo['residue_num'] = $today_system_statement['residue_num'];
            
              // 待处理提现数量
            $vo['wait_withdraw_count'] = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.status = 0")->sum('rr.money');
            // 总结余（充值-提现）
            $vo['aggregate_balance'] = $vo['recharge_sum'] - $vo['withdraw_sum'];
            // 总余额钱包
            $vo['total_balance'] = Db::table('lc_user')->where("system_user_id in (".implode(',', $ids).") ")->sum("withdrawable");
            // 波比（提现 / 充值）
            $vo['poby'] = $vo['recharge_sum'] ? bcmul($vo['withdraw_sum']/$vo['recharge_sum'], 100, 2) . "%" : '--';
        }
    }

}
