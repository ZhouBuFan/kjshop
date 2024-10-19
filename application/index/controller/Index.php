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

namespace app\index\controller;

use library\Controller;
use library\service\AdminService;
use library\service\MenuService;
use think\Db;
use app\libs\onePay\Tool;
use think\facade\Cache;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class Index extends Controller
{

    /**
     * @description：首页
     * @date: 2020/5/13 0013
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function indexs()
    {
        // if(getInfo("pc_open")) $this->fetch();
        // if(check_wap()) $this->fetch();
        $this->title = '系统管理后台';
        $auth = AdminService::instance()->apply(true);
        if (!$auth->isLogin()) $this->redirect('@admin/login');
        $this->menus = MenuService::instance()->getTree();
        if (empty($this->menus) && !$auth->isLogin()) {
            $this->redirect('@admin/login');
        } else {
            $this->redirect('/admin.html#/admin/users');
        }
    }

    /**
     * Describe:回调通知
     * DateTime: 2020/12/07 2:07
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function _callback()
    {
        //回调示例
        $call_back_data = array(
            'timestamp' => (string) $_POST['timestamp'],
            'nonce' => (string) $_POST['nonce'],
            'sign' => (string) $_POST['sign'],
            'body' => (string) $_POST['body'],
        );
        $merchant = Db::name('LcMerchant')->find(1);
        $sign = md5($call_back_data['body'] . $merchant['api_key'] . $call_back_data['nonce'] . $call_back_data['timestamp']);

        if ($call_back_data['sign'] == $sign) {
            $body = json_decode($call_back_data['body']);

            $merchant = Db::name('LcMerchant')->find(1);

            //$body->tradeType 1充币回调 2提币回调
            if ($body->tradeType == 1) {
                //$body->status 0待审核 1审核成功 2审核驳回 3交易成功 4交易失败
                if ($body->status == 3) {
                    //判断币种类型
                    if ($merchant['main_coin_type'] == $body->mainCoinType && $merchant['coin_type'] == $body->coinType) {
                        //业务处理 
                        //优盾USDT免提交
                        $userAddress = Db::name('LcUser')->where(['usdt_address' => $body->address])->find();

                        if (!empty($userAddress)) {

                            $money = $body->amount / pow(10, $body->decimals);

                            $money2 = changeMoneyByLanguage($money, getLanguageByTimezone($userAddress['time_zone']));

                            $uid = $userAddress['id'];

                            $currency = Db::name('LcCurrency')->where(['time_zone' => $userAddress['time_zone']])->find();
                            $rechargeMethod = Db::name('LcUserRechargeMethod')->where(['cid' => $currency['id'], 'type' => 6])->find();
                            //如果所在语言不存在此充值方式，则默认选择第一个创建的此充值方式
                            if (empty($rechargeMethod)) $rechargeMethod = Db::name('LcUserRechargeMethod')->where(['type' => 6])->find();

                            //添加充值记录
                            $time = date('Y-m-d H:i:s');
                            $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $userAddress['time_zone'], 'Y-m-d H:i:s');
                            $insert = array(
                                "uid" => $uid,
                                "rid" => empty($rechargeMethod) ? 0 : $rechargeMethod['id'],
                                "orderNo" => 'IN' . date('YmdHis') . rand(1000, 9999) . rand(100, 999),
                                "money" => $money,
                                "money2" => $money2,
                                "currency" => $currency['symbol'],
                                "time_zone" => $userAddress['time_zone'],
                                "act_time" => $act_time,
                                "time" => $time,
                                "status" => 1
                            );
                            Db::name('LcUserRechargeRecord')->insertGetId($insert);

                            //流水添加
                            addFunding($uid, $money, $money2, 1, 2, getLanguageByTimezone($userAddress['time_zone']));
                            //添加余额
                            setNumber('LcUser', 'money', $money, 1, "id = $uid");
                            //添加积分
                            setNumber('LcUser', 'point', $money, 1, "id = $uid");
                            //更新会员等级
                            $user_1 = Db::name("LcUser")->find($uid);
                            // setUserMember($uid,$user_1['value']);
                            //添加冻结金额
                            $info = Db::name('LcInfo')->find(1);
                            if ($info['recharge_need_flow']) {
                                setNumber('LcUser', 'frozen_money', $money, 1, "id = $uid");
                            }
                            //团队充值奖励
                            setTemRechargeReward($uid, $money);
                        }
                        //优盾USDT需提交Hash
                        else {
                            $rechargeRecord = Db::name('LcUserRechargeRecord')->where(['hash' => $body->txId, 'status' => 0])->find();
                            if (!empty($rechargeRecord)) {
                                $money = $body->amount / pow(10, $body->decimals);
                                $money2 = changeMoneyByLanguage($money, getLanguageByTimezone($rechargeRecord['time_zone']));

                                $uid = $rechargeRecord['uid'];

                                Db::name('LcUserRechargeRecord')->where('id', $rechargeRecord['id'])->update(['status' => 1, 'money' => $money, 'money2' => $money2]);
                                //流水添加
                                addFunding($uid, $money, $money2, 1, 2, getLanguageByTimezone($rechargeRecord['time_zone']));
                                //添加余额
                                setNumber('LcUser', 'money', $money, 1, "id = $uid");
                                //添加积分
                                setNumber('LcUser', 'point', $money, 1, "id = $uid");
                                //更新会员等级
                                $user_1 = Db::name("LcUser")->find($uid);
                                // setUserMember($uid,$user_1['value']);
                                //添加冻结金额
                                $info = Db::name('LcInfo')->find(1);
                                if ($info['recharge_need_flow']) {
                                    setNumber('LcUser', 'frozen_money', $money, 1, "id = $uid");
                                }
                                //团队充值奖励
                                setTemRechargeReward($uid, $money);
                            }
                        }
                    }
                    return "success";
                }
                //无论业务方处理成功与否（success,failed），回调都认为成功
                return "success";
            } elseif ($body->tradeType == 2) {
                //判断币种类型
                if ($merchant['main_coin_type'] == $body->mainCoinType && $merchant['coin_type'] == $body->coinType) {
                    $businessId = $body->businessId;
                    $withdrawRecord = Db::name('LcUserWithdrawRecord')->where("orderNo = '$businessId' AND (status = 3 OR status = 4)")->find();

                    if (!empty($withdrawRecord)) {
                        $uid = $withdrawRecord['uid'];

                        //$body->status 0待审核 1审核成功 2审核驳回 3交易成功 4交易失败
                        if ($body->status == 0) {
                            //业务处理
                        } else if ($body->status == 1) {
                            //审核通过，状态：代付中 4
                            Db::name('LcUserWithdrawRecord')->where('id', $withdrawRecord['id'])->update(['status' => 3]);
                        } else if ($body->status == 2) {
                            //审核失败，返还提现金额
                            //流水添加
                            addFunding($uid, $withdrawRecord['money'], $withdrawRecord['money2'], 1, 4, getLanguageByTimezone($withdrawRecord['time_zone']), 2);
                            //余额返还
                            setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = $uid");
                            //设置提现状态为失败 2 
                            Db::name('LcUserWithdrawRecord')->where('id', $withdrawRecord['id'])->update(['status' => 2, 'time2' => date('Y-m-d H:i:s')]);
                        } else if ($body->status == 3) {
                            //成功，状态：1
                            Db::name('LcUserWithdrawRecord')->where('id', $withdrawRecord['id'])->update(['status' => 1, 'time2' => date('Y-m-d H:i:s')]);
                        } else if ($body->status == 4) {
                            //失败，返还提现金额
                            //流水添加
                            addFunding($uid, $withdrawRecord['money'], $withdrawRecord['money2'], 1, 4, getLanguageByTimezone($withdrawRecord['time_zone']), 2);
                            //余额返还
                            setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = $uid");
                            //设置提现状态为失败 2 
                            Db::name('LcUserWithdrawRecord')->where('id', $withdrawRecord['id'])->update(['status' => 2, 'time2' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
                //无论业务方处理成功与否（success,failed），回调都认为成功
                return "success";
            }
        } else {
            echo 'sign error';
        }
    }
    /**
     * Describe:定时结算任务
     * DateTime: 2022/8/15
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function invest_settle()
    {
        $task_ip = getInfo("task_ip");
        //判断ip白名单
        if (strstr($task_ip, $this->request->ip())) {
            $noInvest = true;

            $now = date('Y-m-d H:i:s');
            $invest_list1 = [];
            $invest_list2 = [];
            $savings_list1 = [];
            //每日付息到期还本
            // $invest_list1 = Db::name("LcInvest")->where("type=1 AND status = 0")->select();
            //到期还本付息
            // $invest_list2 = Db::name("LcInvest")->where("time2 <= '$now' AND status = 0 AND ( type=2 OR type=3 )")->select();
            //储蓄金定期
            $savings_list1 = Db::name("LcSavingsSubscribe")->where("type=2 AND status = 0")->select();

            if (empty($invest_list1) && empty($invest_list2) && empty($savings_list1)) exit('No records');

            //每日付息到期还本处理
            foreach ($invest_list1 as $k => $v) {
                //判断返还时间
                $return_num = $v['wait_num'] - 1;
                $return_time = date('Y-m-d H:i:s', strtotime($v['time2'] . '-' . $return_num . ' day'));
                if ($return_time > $now) continue;

                $time_zone = $v['time_zone'];
                $language = getLanguageByTimezone($time_zone);

                $money = $v['money'];
                //每日利息=总利息/总期数
                $day_interest = $v['total_interest'] / $v['total_num'];

                //最后一期
                if ($v['wait_num'] == 1) {
                    Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1, 'wait_num' => 0, 'wait_interest' => 0]);
                    //返还本金
                    addFunding($v['uid'], $money, changeMoneyByLanguage($money, $language), 1, 15, $language);
                    setNumber('LcUser', 'money', $money, 1, "id = {$v['uid']}");
                } else {
                    Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num'] - 1, 'wait_interest' => $v['wait_interest'] - $day_interest]);
                }

                //利息
                addFunding($v['uid'], $day_interest, changeMoneyByLanguage($day_interest, $language), 1, 6, $language);
                setNumber('LcUser', 'money', $day_interest, 1, "id = {$v['uid']}");

                //添加收益
                setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");

                $noInvest = false;
            }
            //到期还本付息处理
            foreach ($invest_list2 as $k => $v) {
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1, 'wait_num' => 0, 'wait_interest' => 0]);

                $time_zone = $v['time_zone'];
                $language = getLanguageByTimezone($time_zone);

                $money = $v['money'];
                $total_interest = $v['total_interest'];

                //利息
                addFunding($v['uid'], $total_interest, changeMoneyByLanguage($total_interest, $language), 1, 6, $language);
                setNumber('LcUser', 'money', $total_interest, 1, "id = {$v['uid']}");

                //本金
                addFunding($v['uid'], $money, changeMoneyByLanguage($money, $language), 1, 15, $language);
                setNumber('LcUser', 'money', $money, 1, "id = {$v['uid']}");


                //添加收益
                setNumber('LcUser', 'income', $total_interest, 1, "id = {$v['uid']}");

                $noInvest = false;
            }
            //储蓄金定期收益处理
            foreach ($savings_list1 as $k => $v) {
                //判断返还时间
                $return_num = $v['wait_day'] - 1;
                $return_time = date('Y-m-d H:i:s', strtotime($v['time2'] . '-' . $return_num . ' day'));
                if ($return_time > $now) continue;

                $time_zone = $v['time_zone'];
                $language = getLanguageByTimezone($time_zone);

                $money = $v['money'];
                //每日利息=申购金额*利率
                $day_interest = $v['money'] * $v['rate'] / 100;

                //最后一期
                if ($v['wait_day'] == 1) {
                    Db::name('LcSavingsSubscribe')->where('id', $v['id'])->update(['status' => 1, 'wait_day' => 0]);
                    //添加赎回记录
                    $orderNo = 'RE' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
                    $insert = array(
                        "uid" => $v['uid'],
                        "orderNo" => $orderNo,
                        "money" => $money,
                        "money2" => $v['money2'],
                        "type" => 2,
                        "currency" => $v['currency'],
                        "time_zone" => $v['time_zone'],
                        "time" => $now,
                        "time_actual" => $time_actual = dateTimeChangeByZone($now, 'Asia/Shanghai', $v['time_zone'], 'Y-m-d H:i:s'),
                    );
                    Db::name('LcSavingsRedeem')->insertGetId($insert);

                    //自动赎回
                    addFunding($v['uid'], $money, changeMoneyByLanguage($money, $language), 1, 17, $language);
                    setNumber('LcUser', 'savings_fixed', $money, 2, "id = {$v['uid']}");
                    setNumber('LcUser', 'money', $money, 1, "id = {$v['uid']}");
                } else {
                    Db::name('LcSavingsSubscribe')->where('id', $v['id'])->update(['wait_day' => $v['wait_day'] - 1]);
                }

                //利息流水
                addFunding($v['uid'], $day_interest, changeMoneyByLanguage($day_interest, $language), 1, 18, $language, 2);
                //利息
                setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");


                $noInvest = false;
            }
            if ($noInvest) {
                exit('No records');
            }
            exit('Finish');
        } else {
            echo ("IP does not support");
        }
    }
    /**
     * Describe:储蓄金活期收益结算任务
     * DateTime: 2022/8/15
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function savings_settle()
    {
        $task_ip = getInfo("task_ip");
        //判断ip白名单
        if (strstr($task_ip, $this->request->ip())) {
            $noRecord = true;

            $now = date('Y-m-d H:i:s');
            //活期
            $savings_list = Db::name("LcUser")->where("savings_flexible > 0")->select();

            if (empty($savings_list)) exit('No records');

            $savings = Db::name('LcSavings')->find(1);
            $flexible_min_day = $savings['flexible_min_day'];

            //活期处理
            foreach ($savings_list as $k => $v) {
                $uid  = $v['id'];

                //时区转换，按当前用户时区统计
                $time_zone = $v['time_zone'];
                $language = getLanguageByTimezone($time_zone);
                $now = date('Y-m-d H:i:s');
                $start = date('Y-m-d H:i:s', strtotime("-1 day"));
                //判断持有未超过指定天数金额
                $flexible_no_sum = Db::name('lc_savings_subscribe')->where("time BETWEEN '$start' AND '$now' AND uid = $uid AND type = 1")->sum('money');
                //活期金额 = 用户活期余额-活期持有未超过24小时金额
                $money = $v['savings_flexible'] - $flexible_no_sum;

                //每日利息=活期金额*活期利率
                $day_interest = $money * $savings['flexible_rate'] / 100;

                //判断最低收益
                if ($day_interest < $savings['min_income']) continue;

                //利息
                addFunding($v['id'], $day_interest, changeMoneyByLanguage($day_interest, $language), 1, 18, $language);
                setNumber('LcUser', 'money', $day_interest, 1, "id = {$v['id']}");

                $noRecord = false;
            }
            if ($noRecord) {
                exit('No records');
            }
            exit('Finish');
        } else {
            echo ("IP does not support");
        }
    }
    /**
     * Describe:设置货币汇率
     * DateTime: 2022/6/14
     * 
     */
    public function set_currency_price()
    {

        //判断ip白名单
        if (strstr(getInfo("task_ip"), $this->request->ip())) {

            $req_url = getInfo('rate_api');
            $response_json = file_get_contents($req_url);
            if (false !== $response_json) {
                $response = json_decode($response_json);
                if ('success' === $response->result) {
                    $currency = $response->conversion_rates;
                    $currencies = Db::name('LcCurrency')->where(['type' => 2])->select();
                    foreach ($currencies as $k => $v) {
                        $name = $v['name'];
                        $update = ['price' => $currency->$name];
                        $this->updateCurrencyByName($name, $update);
                    }
                    echo ("success");
                } else {
                    echo ($currency->result);
                }
            } else {
                echo ("failed");
            }
        } else {
            echo ("IP does not support");
        }
    }

    function updateCurrencyByName($name, $update)
    {
        Db::name('LcCurrency')->where('name', $name)->update($update);
    }


    public function vip_update()
    {
        // set_time_limit(24*3600);
        $page=$this->request->param('page',1);
        $limit=1000;
        $time_zone = getTimezoneByLanguage('en_us');
        $datetime = date('Y-m-d');
        //当前用户时区时间
        $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d');
        $vips = Db::name('LcUserMember')->where("value > 0")->select();
        foreach ($vips as $item) {
            if ($item['value'] == 8) {
                break;
            }
            $user_level = Db::name('LcUser')->where("mid = {$item['id']}")->whereTime('logintime', '>=', $act_date_time)->page($page, $limit)->select();
            foreach ($user_level as $val) {
                switch ($item['value']) {
                    case 1:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level=1 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        if ($num > 2) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                    case 2:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        if ($num > 2) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                    case 3:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $member_num = $member_num['num'] ?? 0;
                        // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
                        if ($num > 2 && $member_num > 99) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                    case 4:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
                        $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $member_num = $member_num['num'] ?? 0;
                        if ($num > 1 && $member_num > 399) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                    case 5:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
                        $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $member_num = $member_num['num'] ?? 0;
                        if ($num > 0 && $member_num > 999) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                    case 6:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
                        $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $member_num = $member_num['num'] ?? 0;
                        if ($num > 0 && $member_num > 1999) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                    case 7:
                        $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $num = $num['num'] ?? 0;
                        // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
                        $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
                        $member_num = $member_num['num'] ?? 0;
                        if ($num > 0 && $member_num > 4999) {
                            Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid'] + 1)]);
                        }
                        break;
                }
            }
        }
        echo "success";
        die;
    }


    // public function vip_update() {
    //     // set_time_limit(24*3600);
    //     $time_zone = getTimezoneByLanguage('en_us');
    //     $datetime = date('Y-m-d');
    //     //当前用户时区时间
    //     $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d');
    //     $vips = Db::name('LcUserMember')->where("value > 0")->select();
    //     foreach($vips as $item) {
    //         if ($item['value'] == 8) {
    //             break;
    //         }
    //         $user_level = Db::name('LcUser')->where("mid = {$item['id']}")->whereTime('logintime', '>=', $act_date_time)->select();
    //         foreach ($user_level as $val) {
    //             switch ($item['value']) {
    //                 case 1:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level=1 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     if($num > 2) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break;
    //                 case 2:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     if($num > 2) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break;
    //                 case 3:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $member_num = $member_num['num'] ?? 0;
    //                     // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
    //                     if($num > 1 && $member_num > 99) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break;
    //                 case 4:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
    //                     $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $member_num = $member_num['num'] ?? 0;
    //                     if($num > 1 && $member_num > 399) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break;
    //                 case 5:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
    //                     $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $member_num = $member_num['num'] ?? 0;
    //                     if($num > 0 && $member_num > 999) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break;
    //                 case 6:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
    //                     $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $member_num = $member_num['num'] ?? 0;
    //                     if($num > 0 && $member_num > 1999) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break; 
    //                 case 7:
    //                     $num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>={$val['mid']} and ur.level in (1,2,3) and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $num = $num['num'] ?? 0;
    //                     // $member_num = Db::name("LcUserRelation")->where("parentid={$val['id']}")->count();
    //                     $member_num = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.uid=u.id')->where("u.mid>=8006 and ur.parentid={$val['id']}")->group('ur.parentid')->field('count(ur.parentid) as num,ur.parentid')->find();
    //                     $member_num = $member_num['num'] ?? 0;
    //                     if($num > 0 && $member_num > 4999) {
    //                         Db::name('LcUser')->where("id = {$val['id']}")->update(['mid' => ($val['mid']+1)]);
    //                     }
    //                     break;     
    //             }
    //         }
    //     }
    //     echo "success";die;

    // }
    /**
     * ff_pay 支付回调
     */
    public function ff_pay_callback()
    {
        // $tool = new Tool();
        $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        $res = json_decode($str, true);
        // $res = json_decode($str, true);
        $curr_date = date('Y-m-d H:i:s');
        file_put_contents('pay.log', "【" . $curr_date . "】:" . json_encode($str) . PHP_EOL, FILE_APPEND);
        $language = 'en_us';
        if ($res) {
            if ($res['return_code'] == 00) { // 充值
                $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                    'status' => 0,
                    'orderNo' => $res['order_no']
                ])->find();
                if ($rechargeRecord) {
                    $update_data = [
                        'voucher' => $res['transaction_id'],
                        'remark' => $res['remark'] ?? '',
                        'time2' => date('Y-m-d H:i:s')
                    ];
                    $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                    $num = 0;
                    if ($count > 0) {
                        //首充
                        $num = 1;
                    }

                    $update_data['status'] = 1;
                    $update_data['count'] = $num;
                    Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);
                    //添加余额
                    addFunding($rechargeRecord['uid'], $res['amount'], changeMoneyByLanguage($res['amount'], $language), 1, 2, $language);
                    setNumber('LcUser', 'money', $res['amount'], 1, "id = {$rechargeRecord['uid']}");
                }
            }
        }
        echo 'OK';
        die;
    }

    /**
     * ff_pay 代付回调
     */
    public function ff_out_pay_callback()
    {
        // $tool = new Tool();
        $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        parse_str($str, $res);
        // $res = json_decode($str, true);
        // $res = json_decode($str, true);
        $curr_date = date('Y-m-d H:i:s');
        file_put_contents('pay_out.log', "【" . $curr_date . "】:" . ($str) . PHP_EOL, FILE_APPEND);
        $language = 'en_us';
        if ($res) {
            $withdrawRecord = Db::name('LcUserWithdrawRecord')->where([
                'status' => 4,
                'orderNo' => $res['out_trade_no']
            ])->find();
            if ($withdrawRecord) {
                $update_data = [
                    'remark' => $res['msg'] ?? '',
                    'payment_received_time' => date('Y-m-d H:i:s')
                ];
                if ($res['status'] == 'ok') { // 提现
                    $update_data['status'] = 1;
                    Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                } else {
                    $update_data['status'] = 2;
                    Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                    //失败时返还提现金额
                    //流水添加
                    addFunding($withdrawRecord['uid'], $withdrawRecord['money'], $withdrawRecord['money2'], 1, 4, getLanguageByTimezone($withdrawRecord['time_zone']));
                    //余额返还
                    setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = {$withdrawRecord['uid']}");
                }
            }
        }
        echo 'OK';
        die;
    }

    public function xxpp()
    {
        echo "已停用";
        die;
        // 剔除所有收益
        $fund_list = Db::name("LcUserFunding")->where("fund_type=19")->select();
        foreach ($fund_list as $val) {
            // 扣减收益
            setNumber('LcUser', 'withdrawable', $val['money'], 2, "id = {$val['uid']}");
            // 扣减总收益
            setNumber('LcUser', 'income', $val['money'], 2, "id = {$val['uid']}");
            Db::name("LcUserFunding")->where("id={$val['id']}")->delete();
        }
        $invest_list3 = Db::name("LcInvest")->where("is_distribution=1")->select();
        //按日反息 到期不反本（日）
        foreach ($invest_list3 as $k => $v) {
            $time_zone = $v['time_zone'];
            $language = getLanguageByTimezone($time_zone);

            //每日利息=总利息/总期数
            $day_interest = $v['total_interest'] / $v['total_num'];

            // 添加返利
            for ($i = 0; $i < ($v['total_num'] - $v['wait_num']); $i++) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->order('ur.level asc')->where("ur.uid = {$v['uid']}")->limit(3)->select();
                foreach ($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if (empty($ProductNumber)) {
                        continue;
                    }
                    $level = 0;
                    switch ($key) {
                        case 0:
                            $level = $val['level_b'];
                            break;
                        case 1:
                            $level = $val['level_c'];
                            break;
                        case 2:
                            $level = $val['level_d'];
                            break;
                    }
                    if ($level == 0) {
                        continue;
                    }
                    $interest_rate = floor($day_interest * $level) / 100;
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'], $interest_rate, changeMoneyByLanguage($interest_rate, $language), 1, 19, $language);
                }
            }
        }
    }

    /**
     * 6小时收益领取
     */
    public function lingqushouyi()
    {
        $time_zone = getTimezoneByLanguage('en_us');
        $datetime = date('Y-m-d');
        //当前用户时区时间
        $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d');

        $datetimenow = date('Y-m-d H:i:s');
        //当前用户时区时间
        $now_date_time = dateTimeChangeByZone($datetimenow, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');

        //三天后的日期
        $nextDate = date('Y-m-d', strtotime($act_date_time . ' +3 days'));

        $result = date('Y-m-d H:i:s', strtotime($now_date_time) + (60 * 60 * 6)); // 转换成Unix时间戳并加上6小时的秒数

        //获取今天星期几
        $w = date("w", strtotime($act_date_time)); //获取星期几;
        $is_w = 0;
        if (in_array($w, [1, 2, 3, 4, 5])) {
            $is_w = 1;
        } else if ($w == 6) {
            $is_w = 2;
        } else if ($w == 0) {
            $is_w = 3;
        }

        $where = array();
        // $where['type']=7;
        // $where['status']=0;
        // $where['time_actual']=['<>', $act_date_time];
        // $where['time2_actual']=['<=', $now_date_time];
        // $act_date_time_last_time= date('Y-m-d H:i:s', strtotime($act_date_time . ' 23:59:59'));
        //   $invest_list3 = Db::name("LcInvest")->where("type=7 and status=0")->where('time2_actual','not between',[$result,$nextDate])->whereNotIn('time_actual', $act_date_time)->select();

        $invest_list3 = Db::name("LcInvest")->where("type=7 and status=0 and  TIMESTAMPDIFF(HOUR, time2_actual, '{$now_date_time}') >= 6 and time2_actual <'{$nextDate}' and time_actual < '{$act_date_time}'")->select();


        // echo $result;
        echo count($invest_list3);

        if (empty($invest_list3)) {

            die;
        }

        $item = Db::name("LcItem")->where("type = 7")->limit(1)->select();

        if (empty($item)) {
            die;
        }

        $invest['not_receive'] = empty($item['not_receive']) ? [] : json_decode($item['not_receive']);
        if (in_array($is_w, $invest['not_receive'])) {
            die;
        }

        $language = getLanguageByTimezone($time_zone);
        //6小时定投 自动领取下发收益
        foreach ($invest_list3 as $k => $v) {
            //每日利息=总利息/总期数
            $day_interest = $v['total_interest'] / $v['total_num'];


            // 添加返利
            $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->order('ur.level asc')->where("ur.uid = {$v['uid']}")->limit(3)->select();
            foreach ($fusers as $key => $val) {
                //如果上级没有购买相同的产品类型 则不返利跳过
                $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                if (empty($ProductNumber)) {
                    continue;
                }
                $level = 0;
                switch ($key) {
                    case 0:
                        $level = $val['level_b'];
                        break;
                    case 1:
                        $level = $val['level_c'];
                        break;
                    case 2:
                        $level = $val['level_d'];
                        break;
                }
                if ($level == 0) {
                    continue;
                }
                $interest_rate = floor($day_interest * $level) / 100;
                // 添加收益
                setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                // 添加总收益
                setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                //流水添加
                addFunding($val['parentid'], $interest_rate, changeMoneyByLanguage($interest_rate, $language), 1, 19, $language);
            }
            //最后一期
            if ($v['wait_num'] == 1) {
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1, 'wait_num' => 0, 'wait_interest' => 0]);
                // //返还本金
                // addFunding($v['uid'],$v['money2'],changeMoneyByLanguage($v['money2'],$language),1,15,$language);
                // setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");

            } else {



                Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num'] - 1, 'wait_interest' => $v['wait_interest'] - $day_interest,   'time2_actual' => $now_date_time]);
            }

            //利息
            addFunding($v['uid'], $day_interest, changeMoneyByLanguage($day_interest, $language), 1, 6, $language, 2);
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");

            //添加收益
            setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");
        }
        echo 'OK';
        die;
    }

    //支付回调通知
    public function yomi_pay_callback()
    {
        // $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        // $data = json_decode($str, true);
        $data = $_POST;

        $sign = $data['sign'];
        unset($data['sign']);
        // $platform_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC73q+WOQs+l0SQFaULJz19rIlU
        // KoBqoa5DlV68PwtknUk9dkU83arKOgI1kq9zSGhujeo/jdCPHqecTil+8cr/71Sy
        // yj1FprITauzFOg5pp/h1nlyTSvP/Q+0EKwMn6Fdjt9Sx6H8nNfV0meTGjW+qrv8Y
        // VqSKFv72Hx4crhMEmQIDAQAB';//平台公钥

        ksort($data);
        $params_str = "";
        foreach ($data as $key => $val) {
            if (!empty($val)) {
                $params_str = $params_str . $val;
            }
        }
        $language = 'en_us';
        if ($data["returncode"] == "00") {

            // $str = "交易成功！订单号：".$data["orderid"];
            $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                'status' => 0,
                'orderNo' => $data['orderid']
            ])->find();
            if ($rechargeRecord) {
                $update_data = [
                    'voucher' => $data['transaction_id'],
                    'remark' => '',
                    'time2' => date('Y-m-d H:i:s')
                ];
                $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                $num = 1;
                if ($count > 0) {
                    //非首充
                    $num = 0;
                } else {
                    //首充
                    $num = 1;
                }

                $update_data['status'] = 1;
                $update_data['count'] = $num;
                Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);

                //添加余额
                addFunding($rechargeRecord['uid'], $data['amount'], changeMoneyByLanguage($data['amount'], $language), 1, 2, $language);
                setNumber('LcUser', 'money', $data['amount'], 1, "id = {$rechargeRecord['uid']}");
            }
            echo 'OK';
            die;
        }

        // $decryptSign = $this->public_key_decrypt($sign,$platform_public_key);
        // print_r($decryptSign);
        //     echo 'OK';die;
        // if ($params_str == $decryptSign) {


        // }
    }

    //yomi代付回调
    public function yomi_out_pay_callback()
    {
        // $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        // $data = json_decode($str, true);
        $data = $_POST;

        $sign = $data['sign'];
        unset($data['sign']);
        // $platform_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC73q+WOQs+l0SQFaULJz19rIlU
        // KoBqoa5DlV68PwtknUk9dkU83arKOgI1kq9zSGhujeo/jdCPHqecTil+8cr/71Sy
        // yj1FprITauzFOg5pp/h1nlyTSvP/Q+0EKwMn6Fdjt9Sx6H8nNfV0meTGjW+qrv8Y
        // VqSKFv72Hx4crhMEmQIDAQAB';//平台公钥

        ksort($data);
        $params_str = "";
        foreach ($data as $key => $val) {
            if (!empty($val)) {
                $params_str = $params_str . $val;
            }
        }
        $language = 'en_us';
        if ($data["status"] == "success") {


            $withdrawRecord = Db::name('LcUserWithdrawRecord')->where([
                'status' => 4,
                'orderNo' => $data['out_trade_no']
            ])->find();
            if ($withdrawRecord) {
                $update_data = [
                    'remark' => $data['msg'] ?? '',
                    'payment_received_time' => date('Y-m-d H:i:s')
                ];
                if ($data['ref_code'] == '1') { // 提现
                    $update_data['status'] = 1;
                    Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                } else {
                    $update_data['status'] = 2;
                    Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                    //失败时返还提现金额
                    //流水添加
                    // addFunding($withdrawRecord['uid'],$withdrawRecord['money'],$withdrawRecord['money2'],1,4,getLanguageByTimezone($withdrawRecord['time_zone']));
                    //余额返还
                    setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = {$withdrawRecord['uid']}");
                }
            }
            echo 'OK';
            die;
        }

        // $decryptSign = $this->public_key_decrypt($sign,$platform_public_key);
        // print_r($decryptSign);
        //     echo 'OK';die;
        // if ($params_str == $decryptSign) {


        // }
    }

    //支付回调通知
    public function wow_pay_callback()
    {
        $headers = getallheaders();
        //      header('Content-Type: application/json');
        // echo json_encode([
        //     'success' => true,
        //     'headers' => $headers
        // ]);
        // die;

        // 获取特定的 x-sn 头部参数
        $x_Sn = isset($headers['X-Sn']) ? $headers['X-Sn'] : null;
        if ($x_Sn != null) {
            echo '1111';
            die;
        } else {

            echo '{"success": true}';
            die;
        }
        // $tool = new Tool();
        $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组

        $res = json_decode($str, true);
        // $res = json_decode($str, true);
        $curr_date = date('Y-m-d H:i:s');
        file_put_contents('pay.log', "【" . $curr_date . "】:" . json_encode($str) . PHP_EOL, FILE_APPEND);
        $language = 'en_us';
        if ($res) {
            if ($res['referenceId']) { // 充值

                if ($res['orders'][0]['status'] == 'SUCCEED') {

                    $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                        'status' => 0,
                        'orderNo' => $res['referenceId']
                    ])->find();
                    if ($rechargeRecord) {
                        $update_data = [
                            'voucher' => $res['orders'][0]['id'],
                            'remark' => '',
                            'time2' => date('Y-m-d H:i:s')
                        ];
                        $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                        $num = 0;
                        if ($count > 0) {
                            //非首充
                            $num = 0;
                        } else {
                            $num = 1;
                        }

                        $update_data['status'] = 1;
                        $update_data['count'] = $num;
                        Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);
                        //添加余额
                        addFunding($rechargeRecord['uid'], $res['amount'], changeMoneyByLanguage($res['amount'], $language), 1, 2, $language);
                        setNumber('LcUser', 'money', $res['amount'], 1, "id = {$rechargeRecord['uid']}");
                    }
                }
            }
        }
        echo '{"success": true}';
        die;
    }




    //baoxue支付回调通知
    public function baoxue_pay_callback()
    {
        // $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        // $data = json_decode($str, true);
        $data = $_POST;
        // $sign = $data['sign'];
        // unset($data['sign']);
        // // $platform_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC73q+WOQs+l0SQFaULJz19rIlU
        // // KoBqoa5DlV68PwtknUk9dkU83arKOgI1kq9zSGhujeo/jdCPHqecTil+8cr/71Sy
        // // yj1FprITauzFOg5pp/h1nlyTSvP/Q+0EKwMn6Fdjt9Sx6H8nNfV0meTGjW+qrv8Y
        // // VqSKFv72Hx4crhMEmQIDAQAB';//平台公钥

        // ksort($data);
        // $params_str = "";
        // foreach ($data as $key => $val) {
        //     if (!empty($val)) {
        //         $params_str = $params_str. $val;
        //     }
        // }
        $language = 'en_us';
        if ($data["payStatus"] == "SUCCESS") {

            // $str = "交易成功！订单号：".$data["orderid"];
            $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                'status' => 0,
                'orderNo' => $data['outTradeNo']
            ])->find();
            if ($rechargeRecord) {
                $update_data = [
                    'voucher' => $data['orderNo'],
                    'remark' => '',
                    'time2' => date('Y-m-d H:i:s')
                ];
                $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                $num = 1;
                if ($count > 0) {
                    //非首充
                    $num = 0;
                } else {
                    //首充
                    $num = 1;
                }

                $update_data['status'] = 1;
                $update_data['count'] = $num;
                Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);

                //添加余额
                addFunding($rechargeRecord['uid'], $data['amountTrue'], 0, 1, 2, $language);
                setNumber('LcUser', 'money', $data['amountTrue'], 1, "id = {$rechargeRecord['uid']}");
            }
        }
        echo 'SUCCESS';
        die;
    }









    /**
     * Onepay回调
     */
    public function one_pay_callback()
    {

        // $data = $_POST;
        $str = file_get_contents("php://input");   //获取post数据
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        $res = json_decode($str, true);
        // print_r($data);
        // echo $res['data'];
        // die;
        $language = 'en_us';
        if ($res['data']) {
            $tool = new Tool();
            $datas = $tool->decryptAes($res['data']);
            // echo $datas['amount'];
            // die;
            if ($datas['orderType'] == 1) {  //代收
                // $str = "交易成功！订单号：".$data["orderid"];
                $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                    'status' => 0,
                    'orderNo' => $datas['merchantNo']
                ])->find();

                if ($rechargeRecord) {
                    if ($datas['status'] == 2) {  //1=进行中,2=成功,3=失败
                        $update_data = [
                            'voucher' => $datas['channelNo'],
                            'remark' => '',
                            'time2' => date('Y-m-d H:i:s')
                        ];
                        $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                        $num = 1;
                        if ($count > 0) {
                            //非首充
                            $num = 0;
                        } else {
                            //首充
                            $num = 1;
                        }

                        $update_data['status'] = 1;
                        $update_data['count'] = $num;
                        $update_data['money'] = $datas['amount'] / 100;
                        Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);

                        //添加余额
                        addFunding($rechargeRecord['uid'], $datas['amount'] / 100, 0, 1, 2, $language);

                        setNumber('LcUser', 'money', $datas['amount'] / 100, 1, "id = {$rechargeRecord['uid']}");
                    }
                }
            } else if ($datas['orderType'] == 2) {  //代付


                $withdrawRecord = Db::name('LcUserWithdrawRecord')->where([
                    'status' => 4,
                    'orderNo' => $datas['merchantNo']
                ])->find();
                if ($withdrawRecord) {
                    $update_data = [
                        'remark' => $datas['remark'] ?? '',
                        'payment_received_time' => date('Y-m-d H:i:s')
                    ];
                    if ($datas['status'] == '2') { // 1=进行中,2=成功,3=失败
                        $update_data['status'] = 1;
                        Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                        //缓存中添加提现金额
                        $withdraw = Cache::store('redis')->hget('withdraw', $withdrawRecord['uid']);
                        if (!empty($withdraw)) {
                            Cache::store('redis')->hset('withdraw', $withdrawRecord['uid'], $withdraw + $withdrawRecord['money']);
                        }
                    } else {
                        $update_data['status'] = 2;
                        Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                        //失败时返还提现金额
                        //流水添加
                        addFunding($withdrawRecord['uid'], $withdrawRecord['money'], $withdrawRecord['money2'], 1, 4, getLanguageByTimezone($withdrawRecord['time_zone']));
                        //余额返还
                        setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = {$withdrawRecord['uid']}");
                    }
                }
            }
        }
        echo 'success';
        die;
    }



    /**
     * jmpay代收回调
     */
    public function jm_pay_callback()
    {

        $str = file_get_contents("php://input");   //获取post数据
        $res = json_decode($str, true);
        $language = 'en_us';
        if ($res['status'] == 'success') {
            $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                'status' => 0,
                'orderNo' => $res['trace_no']
            ])->find();

            if ($rechargeRecord) {
                //1=进行中,2=成功,3=失败
                $update_data = [
                    'voucher' => $res['trade_order'],
                    'remark' => '',
                    'time2' => date('Y-m-d H:i:s')
                ];
                $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                $num = 1;
                if ($count > 0) {
                    //非首充
                    $num = 0;
                } else {
                    //首充
                    $num = 1;
                }

                $update_data['status'] = 1;
                $update_data['count'] = $num;
                $update_data['money'] = $res['actual_amount'] / 100;
                Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);

                //添加余额
                addFunding($rechargeRecord['uid'], $res['actual_amount'] / 100, 0, 1, 2, $language);

                setNumber('LcUser', 'money', $res['actual_amount'] / 100, 1, "id = {$rechargeRecord['uid']}");
            }
        }
        echo 'success';
        die;
    }



    /**
     * jmpay代付回调
     */
    public function jm_payout_callback()
    {

        $str = file_get_contents("php://input");   //获取post数据
        $res = json_decode($str, true);
        $language = 'en_us';
        $withdrawRecord = Db::name('LcUserWithdrawRecord')->where([
            'status' => 4,
            'orderNo' => $res['trace_no']
        ])->find();
        if ($withdrawRecord) {
            $update_data = [
                'remark' => $res['status'] ?? '',
                'payment_received_time' => date('Y-m-d H:i:s')
            ];
            if ($res['status'] == 'success') { // success：出款成功并已完成。
                // wait：等待出款，尚未完成出款。
                // fail：出款失败。
                $update_data['status'] = 1;
                Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                //缓存中添加提现金额
                $withdraw = Cache::store('redis')->hget('withdraw', $withdrawRecord['uid']);
                if (!empty($withdraw)) {
                    Cache::store('redis')->hset('withdraw', $withdrawRecord['uid'], $withdraw + $withdrawRecord['money']);
                }
            } else if ($res['status'] == 'wait') {
            } else {
                $update_data['status'] = 2;
                Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                //失败时返还提现金额
                //流水添加
                addFunding($withdrawRecord['uid'], $withdrawRecord['money'], $withdrawRecord['money2'], 1, 4, getLanguageByTimezone($withdrawRecord['time_zone']));
                //余额返还
                setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = {$withdrawRecord['uid']}");
            }
        }
        echo 'success';
        die;
    }


    /**
     * xlpay代收回调
     */
    public function xl_pay_callback()
    {

        $str = file_get_contents("php://input");   //获取post数据
        $res = json_decode($str, true);
        $language = 'en_us';
        if ($res['status'] == 'SUCCESS' && !empty($res['sign'])) {
            $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
                'status' => 0,
                'orderNo' => $res['merchantCode']
            ])->find();

            if ($rechargeRecord) {
                //1=进行中,2=成功,3=失败
                $update_data = [
                    'voucher' => $res['orderCode'],
                    'remark' => '',
                    'time2' => date('Y-m-d H:i:s')
                ];
                $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$rechargeRecord['uid']}'")->count();
                $num = 1;
                if ($count > 0) {
                    //非首充
                    $num = 0;
                } else {
                    //首充
                    $num = 1;
                }

                $update_data['status'] = 1;
                $update_data['count'] = $num;
                $update_data['money'] = $res['paidAmount'];
                Db::name('LcUserRechargeRecord')->where("id='{$rechargeRecord['id']}'")->update($update_data);

                //添加余额
                addFunding($rechargeRecord['uid'], $res['paidAmount'], 0, 1, 2, $language);

                setNumber('LcUser', 'money', $res['paidAmount'], 1, "id = {$rechargeRecord['uid']}");
            }
        }
        echo 'success';
        die;
    }



    /**
     * xlpay代付回调
     */
    public function xl_payout_callback()
    {

        $str = file_get_contents("php://input");   //获取post数据
        $res = json_decode($str, true);
        $language = 'en_us';
        $withdrawRecord = Db::name('LcUserWithdrawRecord')->where([
            'status' => 4,
            'orderNo' => $res['merchantCode']
        ])->find();
        if ($withdrawRecord) {
            $update_data = [
                'remark' => $res['status'] ?? '',
                'payment_received_time' => date('Y-m-d H:i:s')
            ];
            if ($res['status'] == 'SUCCESS') { // success：出款成功并已完成。
                // wait：等待出款，尚未完成出款。
                // fail：出款失败。
                $update_data['status'] = 1;
                Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                //缓存中添加提现金额
                $withdraw = Cache::store('redis')->hget('withdraw', $withdrawRecord['uid']);
                if (!empty($withdraw)) {
                    Cache::store('redis')->hset('withdraw', $withdrawRecord['uid'], $withdraw + $withdrawRecord['money']);
                }
            } else if ($res['status'] == 'wait') {
            } else {
                $update_data['status'] = 2;
                Db::name('LcUserWithdrawRecord')->where("id='{$withdrawRecord['id']}'")->update($update_data);
                //失败时返还提现金额
                //流水添加
                addFunding($withdrawRecord['uid'], $withdrawRecord['money'], $withdrawRecord['money2'], 1, 4, getLanguageByTimezone($withdrawRecord['time_zone']));
                //余额返还
                setNumber('LcUser', 'withdrawable', $withdrawRecord['money'], 1, "id = {$withdrawRecord['uid']}");
            }
        }
        echo 'success';
        die;
    }

    /**
     * 每天跨点清除当天的流水记录缓存 
     */
    public function cleanfunding()
    {
        Cache::store('redis')->del('todayfunding');
        $this->success('清理成功');
    }




    /**
     * 系统报表刷新 根据指定时间更新
     * 
     */
    public function updatemain()
    {
        $params = $this->request->param();
        $datetime = $params["datetime"];
        $time1 = date('Y-m-d 00:00:00', strtotime($datetime));
        $time2 = date('Y-m-d 23:59:59', strtotime($datetime));
        $systemusers = Db::name('SystemUser')->where("status = 1 and is_deleted = 0 and authorize = 2")->select();
        for ($i = 0; $i < count($systemusers); $i++) {
            if ($systemusers[$i]['id']) {
                $ids = [];
                $ids[] = $systemusers[$i]['id'];
            }

            //充值金额
            $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u 
            where u.id=r.uid and u.system_user_id in (" . implode(',', $ids) . ") and  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
            if (!empty($recharge)) {
                $data['recharge']  = $recharge[0]['money'];

                //充值笔数
                $data['recharge_count'] = $recharge[0]['count'];
            } else {
                $data['recharge']  = 0;
                $data['recharge_count'] = 0;
            }
            //充值人数
            $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
            //首充金额
            $first_charge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r, lc_user u 
            where u.id=r.uid and u.system_user_id in (" . implode(',', $ids) . ") and 
            r.count=1 and r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
            if (!empty($first_charge)) {
                $data['first_charge_price'] = $first_charge[0]['money'];
                //首充订单数/人数
                $data['first_charge_count'] = $first_charge[0]['count'];
            } else {
                $data['first_charge_price']  = 0;
                $data['first_charge_count'] = 0;
            }

            // $data['recharge'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->sum('rr.money');
            // $data['recharge_count'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->count();
            // $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
            //提现金额
            $withdraws = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2', 'count(rr.id)' => 'withdraw3'])->find();
            $data['withdraw'] = $withdraws['withdraw1'] - $withdraws['withdraw2'];
            $data['withdrawcount'] = $withdraws['withdraw3'];

            //注册人数
            $data['new_user'] = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("is_real = 0 and time BETWEEN '$time1' AND '$time2'")->count();
            //订单数量
            $data['invest'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.itemid != 235 and rr.time BETWEEN '$time1' AND '$time2'")->count();

            //订单分红
            $invest_reward1 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type =6")->sum('rr.money');
            $invest_reward2 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type =19")->sum('rr.money');
            $data['invest_reward'] = $invest_reward1 + $invest_reward2;

            //红包
            $data['residue_num'] = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND  rr.fund_type =20")->sum('rr.money');

            $systemstatement = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time = '$datetime'")->find();
            if (empty($systemstatement)) {
                //新增数据
                $insert = array(
                    "system_id" => $systemusers[$i]['id'],
                    "time" => $datetime,
                    "regis_num" => $data['new_user'],
                    "order_num" => $data['invest'],
                    "topup_money" => $data['recharge'],
                    "frist_topup_money" => $data['first_charge_price'],
                    "frist_topup_num" => $data['first_charge_count'],  //首充人数
                    "withdraw" => $data['withdraw'],
                    "topup_num" => $data['recharge_p'],
                    "topup_order_num" => $data['recharge_count'],
                    "withdrwa_order_num" => $data['withdrawcount'],
                    "order_dividend" => $data['invest_reward'],  //订单分红
                    "red_packet" => $data['residue_num'] //红包

                );
                Db::name('LcSystemStatement')->insertGetId($insert);
            } else {
                //修改数据
                Db::name('LcSystemStatement')->where('id', $systemstatement['id'])->update([
                    "regis_num" => $data['new_user'],
                    "order_num" => $data['invest'],
                    "topup_money" => $data['recharge'],
                    "frist_topup_money" => $data['first_charge_price'],
                    "frist_topup_num" => $data['first_charge_count'],  //首充人数
                    "withdraw" => $data['withdraw'],
                    "topup_num" => $data['recharge_p'],
                    "topup_order_num" => $data['recharge_count'],
                    "withdrwa_order_num" => $data['withdrawcount'],
                    "order_dividend" => $data['invest_reward'],  //订单分红
                    "red_packet" => $data['residue_num'] //红包
                ]);
            }
        }
        echo ('SUCCESS');
        die;
    }


    /**
     * 系统报表刷新，更新昨天的数据(包含订单分红,红包)
     * 
     */
    public function updatemainyestday()
    {
        // $params = $this->request->param();
        // $datetime = $params["datetime"];
        $now = date('Y-m-d');
        $datetime = date('Y-m-d', strtotime($now) - 86400); //昨天
        //昨天
        $time1 = date('Y-m-d 00:00:00', strtotime($datetime));
        $time2 = date('Y-m-d 23:59:59', strtotime($datetime));
        $systemusers = Db::name('SystemUser')->where("status = 1 and is_deleted = 0 and authorize = 2")->select();
        for ($i = 0; $i < count($systemusers); $i++) {
            if ($systemusers[$i]['id']) {
                $ids = [];
                $ids[] = $systemusers[$i]['id'];
            }

            //充值金额
            $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u 
            where u.id=r.uid and u.system_user_id in (" . implode(',', $ids) . ") and  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
            if (!empty($recharge)) {
                $data['recharge']  = $recharge[0]['money'];

                //充值笔数
                $data['recharge_count'] = $recharge[0]['count'];
            } else {
                $data['recharge']  = 0;
                $data['recharge_count'] = 0;
            }
            //充值人数
            $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
            //首充金额
            $first_charge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r, lc_user u 
            where u.id=r.uid and u.system_user_id in (" . implode(',', $ids) . ") and 
            r.count=1 and r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
            if (!empty($first_charge)) {
                $data['first_charge_price'] = $first_charge[0]['money'];
                //首充订单数/人数
                $data['first_charge_count'] = $first_charge[0]['count'];
            } else {
                $data['first_charge_price']  = 0;
                $data['first_charge_count'] = 0;
            }

            // $data['recharge'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->sum('rr.money');
            // $data['recharge_count'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->count();
            // $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
            //提现金额
            $withdraws = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2', 'count(rr.id)' => 'withdraw3'])->find();
            $data['withdraw'] = $withdraws['withdraw1'] - $withdraws['withdraw2'];
            $data['withdrawcount'] = $withdraws['withdraw3'];

            //注册人数
            $data['new_user'] = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("is_real = 0 and time BETWEEN '$time1' AND '$time2'")->count();
            //订单数量
            $data['invest'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.itemid != 235 and rr.time BETWEEN '$time1' AND '$time2'")->count();

            //订单分红
            $invest_reward1 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type =6")->sum('rr.money');
            $invest_reward2 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type =19")->sum('rr.money');
            $data['invest_reward'] = $invest_reward1 + $invest_reward2;

            //红包
            $data['residue_num'] = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND  rr.fund_type =20")->sum('rr.money');

            $systemstatement = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time = '$datetime'")->find();
            if (empty($systemstatement)) {
                //新增数据
                $insert = array(
                    "system_id" => $systemusers[$i]['id'],
                    "time" => $datetime,
                    "regis_num" => $data['new_user'],
                    "order_num" => $data['invest'],
                    "topup_money" => $data['recharge'],
                    "frist_topup_money" => $data['first_charge_price'],
                    "frist_topup_num" => $data['first_charge_count'],  //首充人数
                    "withdraw" => $data['withdraw'],
                    "topup_num" => $data['recharge_p'],
                    "topup_order_num" => $data['recharge_count'],
                    "withdrwa_order_num" => $data['withdrawcount'],
                    "order_dividend" => $data['invest_reward'],  //订单分红
                    "red_packet" => $data['residue_num'] //红包

                );
                Db::name('LcSystemStatement')->insertGetId($insert);
            } else {
                //修改数据
                Db::name('LcSystemStatement')->where('id', $systemstatement['id'])->update([
                    "regis_num" => $data['new_user'],
                    "order_num" => $data['invest'],
                    "topup_money" => $data['recharge'],
                    "frist_topup_money" => $data['first_charge_price'],
                    "frist_topup_num" => $data['first_charge_count'],  //首充人数
                    "withdraw" => $data['withdraw'],
                    "topup_num" => $data['recharge_p'],
                    "topup_order_num" => $data['recharge_count'],
                    "withdrwa_order_num" => $data['withdrawcount'],
                    "order_dividend" => $data['invest_reward'],  //订单分红
                    "red_packet" => $data['residue_num'] //红包
                ]);
            }
        }
        echo ('SUCCESS');
        die;
    }

    /**
     * 系统报表刷新，更新前天的数据(不包含订单分红,红包，提现)
     * 
     */
    public function updatemainqiantian()
    {
        // $params = $this->request->param();
        // $datetime = $params["datetime"];
        $now = date('Y-m-d');
        $datetime = date('Y-m-d', strtotime($now) - 86400 * 2); //前天
        //前天
        $time1 = date('Y-m-d 00:00:00', strtotime($datetime));
        $time2 = date('Y-m-d 23:59:59', strtotime($datetime));
        $systemusers = Db::name('SystemUser')->where("status = 1 and is_deleted = 0 and authorize = 2")->select();
        for ($i = 0; $i < count($systemusers); $i++) {
            if ($systemusers[$i]['id']) {
                $ids = [];
                $ids[] = $systemusers[$i]['id'];
            }

            //充值金额
            $recharge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r  , lc_user u 
            where u.id=r.uid and u.system_user_id in (" . implode(',', $ids) . ") and  r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
            if (!empty($recharge)) {
                $data['recharge']  = $recharge[0]['money'];

                //充值笔数
                $data['recharge_count'] = $recharge[0]['count'];
            } else {
                $data['recharge']  = 0;
                $data['recharge_count'] = 0;
            }
            //充值人数
            $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
            //首充金额
            $first_charge = Db::query("select sum(r.money) as money,count(r.id) as count from lc_user_recharge_record r, lc_user u 
            where u.id=r.uid and u.system_user_id in (" . implode(',', $ids) . ") and 
            r.count=1 and r.status =1 and r.time BETWEEN '$time1' AND '$time2' ");
            if (!empty($first_charge)) {
                $data['first_charge_price'] = $first_charge[0]['money'];
                //首充订单数/人数
                $data['first_charge_count'] = $first_charge[0]['count'];
            } else {
                $data['first_charge_price']  = 0;
                $data['first_charge_count'] = 0;
            }

            // $data['recharge'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->sum('rr.money');
            // $data['recharge_count'] = Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->count();
            // $data['recharge_p'] = count(Db::name('LcUserRechargeRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.status = 1")->group('rr.uid')->column('rr.uid'));
            //提现金额
            $withdraws = Db::name('LcUserWithdrawRecord')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->whereIn('u.system_user_id', $ids)->where("rr.time2 BETWEEN '$time1' AND '$time2' AND rr.status = 1")
                ->field(['SUM(rr.money)' => 'withdraw1', 'SUM(rr.charge)' => 'withdraw2', 'count(rr.id)' => 'withdraw3'])->find();
            $data['withdraw'] = $withdraws['withdraw1'] - $withdraws['withdraw2'];
            $data['withdrawcount'] = $withdraws['withdraw3'];

            //注册人数
            $data['new_user'] = Db::name('LcUser')->whereIn('system_user_id', $ids)->where("is_real = 0 and time BETWEEN '$time1' AND '$time2'")->count();
            //订单数量
            $data['invest'] = Db::name('LcInvest')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.itemid != 235 and rr.time BETWEEN '$time1' AND '$time2'")->count();

            //订单分红
            $invest_reward1 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type =6")->sum('rr.money');
            $invest_reward2 = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND rr.type = 1 AND rr.fund_type =19")->sum('rr.money');
            $data['invest_reward'] = $invest_reward1 + $invest_reward2;

            //红包
            $data['residue_num'] = Db::name('LcUserFunding')->alias('rr')->join('lc_user u', 'u.id=rr.uid')->where("u.is_real = 0")->whereIn('u.system_user_id', $ids)->where("rr.time BETWEEN '$time1' AND '$time2' AND  rr.fund_type =20")->sum('rr.money');

            $systemstatement = Db::name('LcSystemStatement')->whereIn('system_id', $ids)->where("time = '$datetime'")->find();
            if (empty($systemstatement)) {
                //新增数据
                $insert = array(
                    "system_id" => $systemusers[$i]['id'],
                    "time" => $datetime,
                    "regis_num" => $data['new_user'],
                    "order_num" => $data['invest'],
                    "topup_money" => $data['recharge'],
                    "frist_topup_money" => $data['first_charge_price'],
                    "frist_topup_num" => $data['first_charge_count'],  //首充人数
                    "withdraw" => $data['withdraw'],
                    "topup_num" => $data['recharge_p'],
                    "topup_order_num" => $data['recharge_count'],
                    "withdrwa_order_num" => $data['withdrawcount'],
                    "order_dividend" => $data['invest_reward'],  //订单分红
                    "red_packet" => $data['residue_num'] //红包

                );
                Db::name('LcSystemStatement')->insertGetId($insert);
            } else {
                //修改数据
                Db::name('LcSystemStatement')->where('id', $systemstatement['id'])->update([
                    "regis_num" => $data['new_user'],
                    "order_num" => $data['invest'],
                    "topup_money" => $data['recharge'],
                    "frist_topup_money" => $data['first_charge_price'],
                    "frist_topup_num" => $data['first_charge_count'],  //首充人数
                    "withdraw" => $data['withdraw'],
                    "topup_num" => $data['recharge_p'],
                    "topup_order_num" => $data['recharge_count'],
                    "withdrwa_order_num" => $data['withdrawcount'],
                    "order_dividend" => $data['invest_reward'],  //订单分红
                    "red_packet" => $data['residue_num'] //红包
                ]);
            }
        }
        echo ('SUCCESS');
        die;
    }



    /**
     * 每五分钟刷新一次订单分红和兑换红包金额
     */
    public function updateredpack()
    {
        $date = date('Y-m-d');
        //获取订单分红
        $investArray = [];
        for ($i = 0; $i < 100; $i++) {
            $minScorePacket = Cache::store('redis')->lpop('investmoney');
            // print_r($minScorePacket);
            if (!empty($minScorePacket)) {
                $money = $minScorePacket;
                $parts = explode('-', $money);

                $User = Cache::store('redis')->hget('user', $parts[0]);
                if (!empty($User)) {
                    $User = json_decode($User, true);
                    $investmoney['id'] = $User['system_user_id'];
                    $investmoney['money'] = $parts[1];
                    $investArray[] = $investmoney;
                }
            } else {
                break;
            }
        }

        //获取兑换红包
        $redpacketArray = [];
        for ($i = 0; $i < 100; $i++) {
            $minScorePacket = Cache::store('redis')->lpop('redpacket');
            // print_r($minScorePacket);
            if (!empty($minScorePacket)) {
                $money = $minScorePacket;
                $parts = explode('-', $money);
                $User = Cache::store('redis')->hget('user', $parts[0]);
                if (!empty($User)) {
                    $User = json_decode($User, true);
                    $redpackmoney['id'] = $User['system_user_id'];
                    $redpackmoney['money'] = $parts[1];
                    $redpacketArray[] = $redpackmoney;
                }
            } else {
                break;
            }
        }
        if (!empty($investArray)) {
            $this->batchUpdateInvest($date, $investArray, 'order_dividend');
        }

        if (!empty($redpacketArray)) {
            $this->batchUpdateInvest($date, $redpacketArray, 'red_packet');
        }
        echo ('SUCCESS');
        die;
    }

    private function batchUpdateInvest($date, $investArray, $str)
    {
        $updateData = [];
        foreach ($investArray as $invest) {
            $updateData[$invest['id']] = isset($updateData[$invest['id']]) ? $updateData[$invest['id']] + $invest['money'] : $invest['money'];
        }

        // $batchSize = 100; // 每批次处理的记录数
        // $updateChunks = array_chunk($updateData, $batchSize, true);
        Db::startTrans();
        try {
            foreach ($updateData as $chunk => $money) {

                $record = Db::name('LcSystemStatement')
                    ->where('system_id', $chunk)
                    ->where('time', $date)
                    ->order('id', 'asc')
                    ->limit(1)
                    ->find();

                if ($record) {
                    Db::name('LcSystemStatement')
                        ->where('id', $record['id'])
                        ->where('time', $date)
                        ->inc($str, $money)
                        ->update();
                } else {
                    Db::name('LcSystemStatement')->insert([
                        'system_id' => $chunk,
                        'time' => $date,
                        $str => $money
                    ]);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            // 记录错误日志或者处理错误
        }
    }
    /**
     * 查询onepay回调
     */
    public function bcWithdarw()
    {
        $withdrawRecord = Db::name('LcUserWithdrawRecord')->where([
            'status' => 4,
        ])->select();
        $tools = new Tool();
        foreach ($withdrawRecord as $key => $value) {
            $data['orderType'] = 2;
            $data['orderNo'] = $value['orderNo'];
            // $data['nonce'] = $tools->GetRandStr();
            $postdata['data'] = $tools->encryptionAes($data);

            $res = $tools->postRes('https://api-pkr.onepay.news/api/v1/order/query', $data);
            $return_data = json_decode($res, true);
            if ($return_data['code'] == 200) {
                $update_data = ['payment_received_time' => date('Y-m-d H:i:s')];
                if ($return_data['data']['status'] == '2') { // 1=进行中,2=成功,3=失败
                    $update_data['status'] = 1;
                    Db::name('LcUserWithdrawRecord')->where("id='{$value['id']}'")->update($update_data);
                    //缓存中添加提现金额
                    $withdraw = Cache::store('redis')->hget('withdraw', $value['uid']);
                    if (!empty($withdraw)) {
                        Cache::store('redis')->hset('withdraw', $value['uid'], $withdraw + $value['money']);
                    }
                } else {
                    $update_data['status'] = 2;
                    Db::name('LcUserWithdrawRecord')->where("id='{$value['id']}'")->update($update_data);
                    //失败时返还提现金额
                    //流水添加
                    addFunding($value['uid'], $value['money'], $value['money2'], 1, 4, getLanguageByTimezone($value['time_zone']));
                    //余额返还
                    setNumber('LcUser', 'withdrawable', $value['money'], 1, "id = {$value['uid']}");
                }
                echo $value['orderNo'];
            }
        }
        echo 'success';
        exit;
    }

    /**
     * 查询onepay充值回调
     */
    public function bcRecharge()
    {
        //    $return_time = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s').'-3day'));
        $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
            'orderNo' => 'IN202409052246307377969',
            'status' => 0
        ])->find();
        // dump($rechargeRecord);exit;
        // $tools=new Tool();
        //     $data['orderType']=1;
        //     $data['orderNo']='IN202408301649369469468';
        //           // $data['nonce'] = $tools->GetRandStr();
        // $postdata['data']=$tools->encryptionAes($data);

        // $res=$tools->postRes('https://api-pkr.onepay.news/api/v1/order/query',$data);
        // $return_data=json_decode($res,true);
        // $datas=$return_data['data'];
        // if($return_data['code']==200)
        // {
        //     if($datas['status'] == 2){  //1=进行中,2=成功,3=失败
        //     dump($datas);exit;
        $value = $rechargeRecord;
        $update_data = [
            'voucher' => 202409030000,
            'remark' => '',
            'time2' => date('Y-m-d H:i:s')
        ];
        $count = Db::name('LcUserRechargeRecord')->where("status=1 and uid='{$value['uid']}'")->count();
        $num = 1;
        if ($count > 0) {
            //非首充
            $num = 0;
        } else {
            //首充
            $num = 1;
        }

        $update_data['status'] = 1;
        $update_data['count'] = $num;
        Db::name('LcUserRechargeRecord')->where("id='{$value['id']}'")->update($update_data);

        //添加余额
        addFunding($value['uid'], $value['money'], 0, 1, 2, 'en_us');

        setNumber('LcUser', 'money', $value['money'], 1, "id = {$value['uid']}");
        // }
        // }

        echo 'success';
        exit;
    }
    public function bcRecharge2()
    {
        $return_time = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . '-3day'));
        $rechargeRecord = Db::name('LcUserRechargeRecord')->where([
            'rid' => 22,
            'status' => 0,
        ])->where('time', '>=', $return_time)->select();
        dump(count($rechargeRecord));
        die();

        echo 'success';
        exit;
    }
}
