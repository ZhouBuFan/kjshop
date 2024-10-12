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

namespace app\api\controller;
use app\libs\baoxue\baoxuepay;
use app\libs\ffpay\Ff;
use app\libs\onePay\Tool;
use app\libs\jmpay\jmpay;
use app\libs\yomipay\yomipay;
use app\libs\wowpay\wowPay;
use library\Controller;
use Endroid\QrCode\QrCode;
use think\Db;
use think\facade\Session;
use library\File;
use think\facade\Cache;
use app\libs\xlpay\xlpay;

/**
 * 用户中心
 * Class Index
 * @package app\index\controller
 */
class User extends Controller
{
    /**
     * Describe:用户信息
     * DateTime: 2022/3/15 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function info()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        
        //更新登录环境
        //更新登录环境
        Db::name('LcUser')->update(['device' => $params["isapp"],'id'=>$uid]);
        
        //判断每日登录奖励
        // $reward = Db::name('LcReward')->find(1);
        // if($reward['login']>0){
        //     $now = date('Y-m-d H:i:s');//现在
        //     $today = date('Y-m-d');//今天0点
        //     $login_today = getFundingByTime($today,$now,$uid,10);
        //     //判断今日是否奖励
        //     if(empty($login_today)){
        //         //流水添加
        //         addFunding($uid,$reward['login'],changeMoneyByLanguage($reward['login'],$language),1,10,$language);
        //         //添加余额
        //         setNumber('LcUser', 'money', $reward['login'], 1, "id = $uid");
        //         //添加冻结金额
        //         if(getInfo('reward_need_flow')){
        //             setNumber('LcUser', 'frozen_money', $reward['login'], 1, "id = $uid");
        //         }
        //     }
        // }
        
        $user = Db::name("LcUser")->find($uid);
        // $uname = substr($user['username'],0,2).'***'.substr($user['username'],strlen($user['username'])-2,strlen($user['username']));
        $uname = $user['username'];
        // $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
        $member = Cache::store('redis')->hget("member",$user['mid']);
        if(empty($member)){
            $memberList = Db::name("LcUserMember")->select();
            foreach ($memberList as &$member) {
                Cache::store('redis')->hset("member",$member['id'],json_encode($member));
            }
            $member =  json_decode(Cache::store('redis')->hget("member",$user['mid']),true);
        }else{
            $member = json_decode($member,true);
        }
        
        //判断今日是否签到
        // $signin = true;
        // $now = date('Y-m-d H:i:s');//现在
        // $today = date('Y-m-d');//今天0点
        // $signin_today = Db::name('LcUserSignin')->where("time >= '$today' AND time <= '$now' AND uid = '{$uid}'")->select();
        //判断今日是否奖励
        // if(empty($signin_today)) $signin = false;

        $version = '';
        if(Cache::store('redis')->get('version')){ 
            $version =Cache::store('redis')->get("version",$version);
        }else{
            $version = Db::name('LcVersion')->field("app_name as name ,down_url as url , app_logo as logo,show")->find(1);
            Cache::store('redis')->set("version",$version);
        }
        $qrCode = new QrCode();
        $qrCode->setText(getInfo('domain') . '/#/register?code=' . $user['invite_code']);
        $qrCode->setSize(300);
        $shareCode = $qrCode->getDataUri();
        $shareLink = getInfo('domain') . '/#/register?code=' . $user['invite_code'];
        //缓存中获取充值金额
        // $recharge_sum = Cache::store('redis')->hget('recharge',$uid);
        // if(empty($recharge_sum)){
            //充值金额
            $recharge_sum = Db::name('LcUserRechargeRecord')->where('uid', $uid)->where("status = 1")->sum('money');
            Cache::store('redis')->hset('recharge',$uid,$recharge_sum);
        // }
        //缓存中获取提现金额
        // $withdraw_sum = Cache::store('redis')->hget('withdraw',$uid);
        // if(empty($withdraw_sum)){
            //提现金额
            $withdraw_sum = Db::name('LcUserWithdrawRecord')->where('uid', $uid)->where("status = 1")->sum('money');
            Cache::store('redis')->hset('withdraw',$uid,$withdraw_sum);
        // }
        //缓存中获取总投资金额
        // $invest_sum = Cache::store('redis')->hget('invest',$uid);
        // if(empty($invest_sum)){
            //總資產
            $invest_sum = Db::name('LcInvest')->where('uid', $uid)->where(" source!=3")->sum('money');
            Cache::store('redis')->hset('invest',$uid,$invest_sum);
        // }
        
        //总收益
        //缓存中获取总收益
        // $invest_reward = Cache::store('redis')->hget('funding',$uid);
        // if(empty($invest_reward)){
            //总收益
            $invest_reward = Db::name('LcUserFunding')->where('uid', $uid)->where("type = 1 AND fund_type in (6,11,14,19,20)")->sum('money');
            Cache::store('redis')->hset('funding',$uid,$invest_reward);
        // }
        
        //今日收益
        // $day_invest_reward = Cache::store('redis')->hget('todayfunding',$uid);
        // if(empty($day_invest_reward)){
            $time_zone = getTimezoneByLanguage($language);
            $now = dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
            $today = date('Y-m-d 00:00:00',strtotime($now));//当前用户时区 今天0点
            $day_invest_reward = Db::name('LcUserFunding')->where('uid', $uid)->where("act_time BETWEEN '$today' AND '$now' AND type = 1 AND fund_type in (6,19,14)")->sum('money');
            $red_envel = Db::name('LcRedEnvelopeRecord')->where('uid', $uid)->where("act_time BETWEEN '$today' AND '$now' ")->sum('money');
            $day_invest_reward = $day_invest_reward+$red_envel;
            Cache::store('redis')->hset('todayfunding',$uid,$day_invest_reward);
        // }
        

        
        $data = array(
            "username" => $uname,
            "fundBalance" => 0,
            "fundBalanceUsd" => $user['money'],
            // "currency" => $currency['symbol'],
            "uid" => $uid,
            "auth_email" => $user['auth_email'],
            "auth_phone" => $user['auth_phone'],
            "auth_google" => $user['auth_google'],
            "withdrawable" => $user['withdrawable'],
            "integral" => $user['integral'],
            "point" => $user['point'],
            "recharge_sum" => $recharge_sum,
            "withdraw_sum" => $withdraw_sum,
            "invest_sum" => $invest_sum,
            "invest_reward" => $invest_reward,
            "day_invest_reward" => round($day_invest_reward, 2),
            "address" => $user['address'],
            "address_name" => $user['address_name'],
            "address_phone" => $user['address_phone'],
            "invite_code" => $user['invite_code'],
            "user_icon" => getInfo('user_img'),
            "vip_name" => $member['name'],
            "vip_img" => $member['logo'],
            "share_code" => $shareCode,
            "share_link" => $shareLink,
            // "signin" => $signin,
            "version" => $version,
        );
        
        
        $this->success("success", $data);
    }
    

    /**
     * Describe:用户钱包信息
     * DateTime: 2022/3/15 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function walletinfo()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        
        //更新登录环境
        //更新登录环境
        // Db::name('LcUser')->update(['device' => $params["isapp"],'id'=>$uid]);
        
        //判断每日登录奖励
        // $reward = Db::name('LcReward')->find(1);
        // if($reward['login']>0){
        //     $now = date('Y-m-d H:i:s');//现在
        //     $today = date('Y-m-d');//今天0点
        //     $login_today = getFundingByTime($today,$now,$uid,10);
        //     //判断今日是否奖励
        //     if(empty($login_today)){
        //         //流水添加
        //         addFunding($uid,$reward['login'],changeMoneyByLanguage($reward['login'],$language),1,10,$language);
        //         //添加余额
        //         setNumber('LcUser', 'money', $reward['login'], 1, "id = $uid");
        //         //添加冻结金额
        //         if(getInfo('reward_need_flow')){
        //             setNumber('LcUser', 'frozen_money', $reward['login'], 1, "id = $uid");
        //         }
        //     }
        // }
        
        $user = Db::name("LcUser")->find($uid);
        // $uname = substr($user['username'],0,2).'***'.substr($user['username'],strlen($user['username'])-2,strlen($user['username']));
        // $uname = $user['username'];
        // $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
        // $member = Db::name("LcUserMember")->find($user['mid']);
        
        //判断今日是否签到
        // $signin = true;
        // $now = date('Y-m-d H:i:s');//现在
        // $today = date('Y-m-d');//今天0点
        // $signin_today = Db::name('LcUserSignin')->where("time >= '$today' AND time <= '$now' AND uid = '{$uid}'")->select();
        //判断今日是否奖励
        // if(empty($signin_today)) $signin = false;

        // $version = Db::name('LcVersion')->field("app_name as name ,down_url as url , app_logo as logo,show")->find(1);

        // $qrCode = new QrCode();
        // $qrCode->setText(getInfo('domain') . '/#/register?code=' . $user['invite_code']);
        // $qrCode->setSize(300);
        // $shareCode = $qrCode->getDataUri();
        // $shareLink = getInfo('domain') . '/#/register?code=' . $user['invite_code'];

        //充值金额
        // $recharge_sum = Db::name('LcUserRechargeRecord')->where('uid', $uid)->where("status = 1")->sum('money');
        //提现金额
        // $withdraw_sum = Db::name('LcUserWithdrawRecord')->where('uid', $uid)->where("status = 1")->sum('money');
        // 總資產
        // $invest_sum = Db::name('LcInvest')->where('uid', $uid)->where(" source!=3")->sum('money');
        //投资收益
        // $invest_reward = Db::name('LcUserFunding')->where('uid', $uid)->where("type = 1 AND fund_type in (6,19,20)")->sum('money');
        // 今日收益
        // $time_zone = getTimezoneByLanguage($language);
        // $now = dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
        // $today = date('Y-m-d 00:00:00',strtotime($now));//当前用户时区 今天0点
        // $day_invest_reward = Db::name('LcUserFunding')->where('uid', $uid)->where("act_time BETWEEN '$today' AND '$now' AND type = 1 AND fund_type in (6,19)")->sum('money');
        // $red_envel = Db::name('LcRedEnvelopeRecord')->where('uid', $uid)->where("act_time BETWEEN '$today' AND '$now' ")->sum('money');
        // $day_invest_reward = $day_invest_reward+$red_envel;

        
        $data = array(
            // "username" => $uname,
            // "fundBalance" => changeMoneyByLanguage($user['money'],$language),
            "fundBalanceUsd" => $user['money'],
            // "currency" => $currency['symbol'],
            "uid" => $uid,
            // "auth_email" => $user['auth_email'],
            // "auth_phone" => $user['auth_phone'],
            // "auth_google" => $user['auth_google'],
            "withdrawable" => $user['withdrawable']
            // "integral" => $user['integral'],
            // "point" => $user['point'],
            // "recharge_sum" => $recharge_sum,
            // "withdraw_sum" => $withdraw_sum,
            // "invest_sum" => $invest_sum,
            // "invest_reward" => $invest_reward,
            // "day_invest_reward" => $day_invest_reward,
            // "address" => $user['address'],
            // "address_name" => $user['address_name'],
            // "address_phone" => $user['address_phone'],
            // "invite_code" => $user['invite_code'],
            // "user_icon" => getInfo('user_img'),
            // "vip_name" => $member['name'],
            // "vip_img" => $member['logo'],
            // "share_code" => $shareCode,
            // "share_link" => $shareLink,
            // "signin" => $signin,
            // "version" => $version,
        );
        
        
        $this->success("success", $data);
    }

    /**
     * Describe:签到
     * DateTime: 2023/3/27
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function signin()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        
        //判断今日是否已签到
        $reward = Db::name('LcReward')->find(1);
        if($reward['signin']>0){
            $now = date('Y-m-d H:i:s');//现在
            $today = date('Y-m-d');//今天0点
            $signin_today = Db::name('LcUserSignin')->where("time >= '$today' AND time <= '$now' AND uid = '{$uid}'")->select();
            //判断今日是否奖励
            if(!empty($signin_today)) $this->error('utils.parameterError',"",218);
            
            //时区转换
            $time = date('Y-m-d H:i:s');
            $time_zone = getTimezoneByLanguage($language);
            $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            //插入签到记录
            $add = array(
                'uid' => $uid,
                'point' => $reward['signin'],
                'time' => $time,
                "time_zone" =>$time_zone,
                "act_time" =>$act_time,
            );
            Db::name('LcUserSignin')->insert($add);
            //添加签到积分
            setNumber('LcUser', 'point', $reward['signin'], 1, "id = $uid");
        }
        $this->success("success");
    }
    /**
     * @description：获取邮箱验证码
     * @date: 2020/6/2 0002
     */
    public function getEmailCode()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        //判断参数
        if(empty($params['email'])) $this->error('utils.parameterError',"",218);
        //判断邮箱是否正确
        if (!judge($params['email'],"email")) $this->error('auth.emailError',"",218);
        
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($userInfo['id']);
        $email = $params['email'];
        //判断是否验证过
        if ($user['auth_email'] == 1) $this->error('auth.authed',"",218);
        
        $sms_time = Db::name("LcEmailCode")->where("email = '$email'")->order("id desc")->value('time');
        if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error('auth.codeValid',"",218);
        
        $rand_code = rand(1000, 9999);
        
        $msg = getTipsByLanguage('eamil_tips1',$language).$rand_code.getTipsByLanguage('eamil_tips2',$language);
        
        $data = array('email' => $email, 'msg' => $msg, 'code' => $rand_code, 'time' => date('Y-m-d H:i:s'), 'ip' => $this->request->ip());
        
        if(!sendMail($email,getTipsByLanguage('auth_eamil',$language),$msg)) $this->error('auth.sendFail',"",218);
        
        Db::name('LcEmailCode')->insert($data);
        
        $this->success("success");
    }
    
    /**
     * @description：邮箱认证
     * @date: 2020/5/15 0015
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function authEmail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        //判断参数
        if(empty($params['email'])||empty($params['code'])) $this->error('utils.parameterError',"",218);
        //判断邮箱是否正确
        if (!judge($params['email'],"email")) $this->error('auth.emailError',"",218);
       
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($userInfo['id']);
        $uid = $userInfo['id'];
        $email = $params['email'];
        
        if ($user['auth_email'] == 1) $this->error('auth.emailAuthed',"",218);
        
         //判断邮箱是否被使用
        if (Db::name('LcUser')->where(['email' => $params['email']])->find()) $this->error('auth.emailUsed',"",218);
        
        $sms_code = Db::name("LcEmailCode")->where("email = '$email'")->order("id desc")->find();
        //判断验证码是否获取
        if(!$sms_code) $this->error('auth.codeFirst',"",218);
        //判断验证码是否过期
        if ((strtotime($sms_code['time']) + 300) < time()) $this->error('auth.codeExpired',"",218);
        //判断验证码
        if ($params['code'] != $sms_code['code']) $this->error('auth.codeError',"",218);
        //认证奖励
        $reward = Db::name('LcReward')->find(1);
        if($reward['authentication']>0){
            //流水添加
            addFunding($uid,$reward['authentication'],0,1,9,$language);
            //添加余额
            setNumber('LcUser', 'money', $reward['authentication'], 1, "id = $uid");
            //添加冻结金额
            if(getInfo('reward_need_flow')){
                setNumber('LcUser', 'frozen_money', $reward['authentication'], 1, "id = $uid");
            }
        }
        $data = ['auth_email' => 1,'email' => $email];
        $res = Db::name('LcUser')->where('id', $userInfo['id'])->update($data);
        if($res){
            $this->success("success");
        }
        
        $this->error('utils.authFail',"",218);
    }
    /**
     * @description：获取手机验证码
     * @date: 2022/7/30
     */
    public function getSmsCode()
    {
        $params = $this->request->param();
        $language = $params["language"];
        // $this->checkToken($language);
        $phone = $params["phone"];
        $code = $params["verify_code"];
        $country_code = $params["country_code"];
        $phone_code = $country_code.$phone;//拼接国家区号后的手机号
        $ip = $this->request->ip();
        
        //判断参数
        if(empty($phone)||empty($code)||empty($country_code)) $this->error('utils.parameterError',"",218);
        //校验验证码
        $this->type = input('login_captcha_type'.$ip, 'login_captcha_type'.$ip);
        if(strtolower(Cache::get($this->type)) != strtolower($code)) $this->error('auth.charError',"",218);
        
        //判断手机号是否正确
        if (!judge($phone,"mobile_phone")) $this->error('auth.phoneError',"",218);
        
        if (strlen($phone) < 6 || 16 < strlen($phone)) $this->error('auth.phoneError',"",218);
        //判断是否有发送过验证码，且是否还在5分钟有效期
        $sms_time = Db::name("LcSmsCode")->where("phone = '$phone_code'")->order("id desc")->value('time');
        if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error('auth.codeValid',"",218);
        //判断ip是否频繁发送，每5分钟只可发送一次
        $sms_time2 = Db::name("LcSmsCode")->where("ip = '$ip'")->order("id desc")->value('time');
        
        if ($sms_time2 && (strtotime($sms_time2) + 300) > time()) $this->error('auth.frequently',"",218);
        
        $rand_code = rand(1000, 9999);
        $msg = getTipsByLanguage('eamil_tips1',$language).$rand_code.getTipsByLanguage('eamil_tips2',$language);
        
        $lcSms = Db::name('LcSms')->find(1);
        
        $smsapi = $lcSms['host_wo'];
        $user = $lcSms['username']; //短信平台帐号
        $pass = md5($lcSms['password']); //短信平台密码
        $content=$msg;//要发送的短信内容
        $sendPhone = urlencode($phone_code);//要发送短信的手机号码
        
        if($country_code=="+86"){
            $smsapi = $lcSms['host_cn'];
            $sendPhone = $phone;
        }
        
        $sendurl = $smsapi."?u=".$user."&p=".$pass."&m=".$sendPhone."&c=".urlencode($content);
        $result  = file_get_contents($sendurl);
        if($result !=0){
            if($result ==51){
                $this->error('auth.phoneError',"",218);
            }else{
                $this->error('auth.frequently',"",218);
            }
        }
        
        $data = array('phone' => $phone_code, 'msg' => $msg, 'code' => $rand_code, 'time' => date('Y-m-d H:i:s'), 'ip' => $ip);
        
        Db::name('LcSmsCode')->insert($data);
        
        $this->success("success");
    }
    
    /**
     * @description：手机认证
     * @date: 2020/5/15 0015
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function authPhone()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        
        //判断参数
        if(empty($params['phone'])||empty($params['code'])||empty($params['country_code'])) $this->error('utils.parameterError',"",218);
        //判断手机号是否正确
        if (!judge($params['phone'],"mobile_phone")) $this->error('auth.phoneError',"",218);
       
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($userInfo['id']);
        $uid = $user['id'];
        $phone = $params['phone'];
        $country_code = $params["country_code"];
        $phone_code = $country_code.$phone;//拼接国家区号后的手机号
        
        if ($user['auth_phone'] == 1) $this->error('auth.phoneAuthed',"",218);
        
         //判断手机号是否被使用
        if (Db::name('LcUser')->where(['phone' => $params['phone']])->find()) $this->error('auth.phoneUsed',"",218);
        
        $sms_code = Db::name("LcSmsCode")->where("phone = '$phone_code'")->order("id desc")->find();
        //判断验证码是否获取
        if(!$sms_code) $this->error('auth.codeFirst',"",218);
        //判断验证码是否过期
        if ((strtotime($sms_code['time']) + 300) < time()) $this->error('auth.codeExpired',"",218);
        //判断验证码
        if ($params['code'] != $sms_code['code']) $this->error('auth.codeError',"",218);
        //认证奖励
        $reward = Db::name('LcReward')->find(1);
        if($reward['authentication']>0){
            //流水添加
            addFunding($uid,$reward['authentication'],0,1,9,$language);
            //添加余额
            setNumber('LcUser', 'money', $reward['authentication'], 1, "id = $uid");
            //添加冻结金额
            if(getInfo('reward_need_flow')){
                setNumber('LcUser', 'frozen_money', $reward['authentication'], 1, "id = $uid");
            }
        }
        $data = ['auth_phone' => 1,'phone' => $phone_code];
        $res = Db::name('LcUser')->where('id', $userInfo['id'])->update($data);
        if($res){
            $this->success("success");
        }
        
        $this->error('utils.authFail',"",218);
    }
    
    
    /**
     * Describe:认证状态
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAuthStatus()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        $info = Db::name('LcInfo')->find(1);
        if(empty($user['google_key'])){
            $google_key = $this->createSecret();
            $google_qrcode = ($this->getQRCodeGoogleUrl($user['username'],$google_key));
            Db::name('LcUser')->where(['id' => $uid])->update(['google_key' => $google_key,'google_qrcode' => $google_qrcode]);
        }
        $data = array(
            "auth_phone" =>$user['auth_phone'],
            "phone" =>$user['phone'],
            "auth_email" =>$user['auth_email'],
            "email" =>$user['email'],
            "auth_authenticator" =>$user['auth_google'],
            "authenticator_key" =>$user['google_key'],
            "authenticator_qrcode" =>$user['google_qrcode'],
            "auth_phone_status" =>$info['auth_phone'],
            "auth_email_status" =>$info['auth_email'],
            "auth_authenticator_status" =>$info['auth_google'],
        );
        $this->success("success", $data);
    }
    /**
     * Describe:Authenticator认证
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function authAuthenticator()
    {
        $params = $this->request->param();
        $language = $params["language"];
        if(empty($params['code'])) $this->error('utils.parameterError',"",218);
        
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        if(!$this->verifyCode($user['google_key'],$params["code"])){
            $this->error('auth.codeError',"",218);
        }
        
        Db::name('LcUser')->where(['id' => $uid])->update(['auth_google' => 1]);
        //认证奖励
        $reward = Db::name('LcReward')->find(1);
        if($reward['authentication']>0){
            //流水添加
            addFunding($uid,$reward['authentication'],0,1,9,$language);
            //添加余额
            setNumber('LcUser', 'money', $reward['authentication'], 1, "id = $uid");
            //添加冻结金额
            if(getInfo('reward_need_flow')){
                setNumber('LcUser', 'frozen_money', $reward['authentication'], 1, "id = $uid");
            }
        }
        $this->success("success");
    }
    /**
     * Describe:获取提现方式
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getWithdrawalMethod()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name("LcUser")->find($uid);
        
        //判断认证状态
        // if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //获取指定国别的提现方式
        $currency = Cache::store('redis')->hget("withdrawal_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("withdrawal_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
         
        if(empty($currency)) $this->error('utils.parameterError',"",218);
        //获取提现方式
        $withdrawMethod = Cache::store('redis')->hget("withdrawal_method","method");
        if(empty($withdrawMethod)){

            $withdrawMethod = Db::name('LcUserWithdrawMethod')->where(['show' => 1,'delete' => 0,'cid' => $currency['id']])->order('sort asc,id desc')->select();
        
            Cache::store('redis')->hset("withdrawal_method","method",json_encode($withdrawMethod));
        }else{
            $withdrawMethod =  json_decode($withdrawMethod,true);
        }
        
        $wallets = Db::name('LcUserWallet')->field("id,wid,type,wname,account,bid")->where(['uid' => $uid,'cid' => $currency['id'], 'deleted_at' => '0000-00-00 00:00:00'])->select();
        foreach ($wallets as &$wallet) {
            if($wallet['type']==1){
                $wallet['account'] = substr($wallet['account'],0,4).'******'.substr($wallet['account'],strlen($wallet['account'])-4,strlen($wallet['account']));
            }else{
                $wallet['account'] = substr($wallet['account'],0,2).'******'.substr($wallet['account'],strlen($wallet['account'])-2,strlen($wallet['account']));
                
                if($wallet['type']==4){
                    $wbank = Db::name("LcUserWithdrawBank")->field('logo')->find($wallet['bid']);
                    $wallet['bank'] = $wbank;
                }
            }
        }
        
        $data = array(
            "withdrawMethod" =>$withdrawMethod,
            "wallets" =>$wallets,
        );
        $this->success("success", $data);
    }
    /**
     * Describe:获取提现方式详情
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getWithdrawMethodById()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $withdraw = Cache::store('redis')->hget("withdrawal_method","method_by_".$params['id']);
        if(empty($withdraw)){

            $withdraw = Db::name('LcUserWithdrawMethod')->where(['show' => 1,'delete' => 0])->find($params['id']);
        
            if(!empty($withdraw))Cache::store('redis')->hset("withdrawal_method","method_by_".$params['id'],json_encode($withdraw));
        }else{
            $withdraw =  json_decode($withdraw,true);
        }
            
        if(empty($withdraw)) $this->error('utils.parameterError',"",218);
        //银行卡则获取可用银行列表
        if($withdraw['type']==4){
            //获取指定国别的银行卡
        $currency = Cache::store('redis')->hget("withdrawal_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("withdrawal_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        if(empty($currency)) $this->error('utils.parameterError',"",218);
        
        $banks = Cache::store('redis')->hget("withdrawal_method","bank_by_".$currency['id']);
        if(empty($banks)){

            $banks = Db::name('LcUserWithdrawBank')->field("id,logo,name,code")->where(['cid' => $currency['id']])->order('sort asc,id desc')->select();
        
            if(!empty($banks))Cache::store('redis')->hset("withdrawal_method","bank_by_".$currency['id'],json_encode($banks));
        }else{
            $banks =  json_decode($banks,true);
        }
        
        
        $withdraw['banks'] = $banks;
        }
        
        $data = array(
            "withdraw" =>$withdraw,
        );
        $this->success("success", $data);
    }
    /**
     * Describe:添加提现方式
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setWallet()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name("LcUser")->find($uid);
        
        $withdraw = Db::name('LcUserWithdrawMethod')->find($params['id']);
        if(empty($withdraw)) $this->error('utils.parameterError',"",218);
        //判断是否绑定过
        $wallet = Db::name('LcUserWallet')->where(['uid' => $uid,'wid' => $withdraw['id'], 'deleted_at' => '0000-00-00 00:00:00'])->select();
        if(!empty($wallet)) $this->error('utils.parameterError',"",218);

        $currency = Cache::store('redis')->hget("withdrawal_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("withdrawal_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        $add = array(
            "uid"=>$uid,
            "wid"=>$withdraw['id'],
            'cid' => $currency['id'],
            "wname"=>$withdraw['name'],
            "type"=>$withdraw['type'],
            'time' => date('Y-m-d H:i:s'),
        );
        
        switch($withdraw['type']){
            //USDT
            case 1:
                //判断参数
                if(empty($params['account'])||empty($params['img'])) $this->error('utils.parameterError',"",218);
                if (strlen($params['account']) < 2 || 34 < strlen($params['account'])) $this->error('utils.parameterError',"",218);
                //判断地址有效性
                if(getInfo("check_address")){
                    //判断地址有效性
                    $json= json_decode(checkAddress($params['account']), true);
                    if($json['code']!=200){
                        $this->error('wallet.addressError',"",218);
                    }
                }
                $add['account'] = $params['account'];
                $add['img'] = $params['img'];
                break;
            //银行卡
            case 4:
                //判断参数
                if(empty($params['name'])||empty($params['account'])) $this->error('utils.parameterError',"",218);
                
                if (!judge($params['account'],"digit")) $this->error('utils.parameterError',"",218);
                if (strlen($params['name']) < 2 || 50 < strlen($params['name'])) $this->error('utils.parameterError',"",218);
                if (strlen($params['account']) < 2 || 50 < strlen($params['account'])) $this->error('utils.parameterError',"",218);
                $add['name'] = $params['name'];
                
                $add['account'] = $params['account'];
                //限制相同账户最多三个
                $walletnumber = Db::name('LcUserWallet')->where(['account' => $add['account'], 'deleted_at' => '0000-00-00 00:00:00'])->count();
                if($walletnumber >3){
                    $this->error('utils.parameterError',"",218);
                }
                if($params['account']=='01916243000' || $params['account']=='01837621836' || $params['account']=='01739002387' || $params['account']=='0895621185020' || $params['account']=='082112480170' || $params['account']=='081282399330' || $params['account']=='08999360624'){
                    $this->error('Error',"",218);
                }                $add['name'] = $params['name'];
                $add['account'] = $params['account'];
                $add['wname'] = $params['bank'];
                $add['bid'] = $params['bank_id'];
                break;
            //扫码
            default:
                //判断参数
                if(empty($params['account'])||empty($params['img'])) $this->error('utils.parameterError',"",218);
                if (strlen($params['account']) < 2 || 32 < strlen($params['account'])) $this->error('utils.parameterError',"",218);
                
                $add['account'] = $params['account'];
                $add['img'] = $params['img'];
        }
        
        
        Db::name('LcUserWallet')->insert($add);
        $this->success("success");
    }
    /**
     * Describe: 删除提现账户
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function delWallets() {
        $params = $this->request->param();
        $language = $params["language"];
        $id = $params["id"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];

        //判断是否绑定过
        $wallet = Db::name('LcUserWallet')->where(['uid' => $uid, 'id' => $id, 'deleted_at' => '0000-00-00 00:00:00'])->select();
        if(empty($wallet)) $this->error('utils.parameterError',"",218);

        Db::name('LcUserWallet')->where(['uid' => $uid, 'id' => $id, 'deleted_at' => '0000-00-00 00:00:00'])->update(["deleted_at" => date("Y-m-d H:i:s")]);

        $this->success("success");

    }
    /**
     * Describe:获取提现账户
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getWallets()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        
        //判断认证状态
        // if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //获取指定国别的提现账户
        $currency = Cache::store('redis')->hget("withdrawal_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("withdrawal_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        $wallets = Db::name('LcUserWallet')->field('id,wname,type,account')->where(['uid' => $uid,'cid' => $currency['id'], 'deleted_at' => '0000-00-00 00:00:00'])->select();
        foreach ($wallets as &$wallet) {
            if($wallet['type']==1){
                $wallet['account'] = substr($wallet['account'],0,4).'****'.substr($wallet['account'],strlen($wallet['account'])-4,strlen($wallet['account']));
            }else{
                $wallet['account'] = substr($wallet['account'],0,2).'****'.substr($wallet['account'],strlen($wallet['account'])-2,strlen($wallet['account']));
            }
        }
        // $availableAmount = $user['money'] - $user['frozen_money'];
        $availableAmount = $user['withdrawable'];
        $data = array(
            "wallets" =>$wallets,
            "withdrawNum" =>$currency['withdraw_num'],
            "withdrawMin" =>$currency['withdraw_min'],
            "userBalance" =>$availableAmount,
            "frozenAmount" =>$user['frozen_money'],
        );
        $this->success("success", $data);
    }
    
    /**
     * Describe:图片上传
     * DateTime: 2022/3/15 20:01
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function imgUpload()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        if (!($file = $this->getUploadFile()) || empty($file)) $this->error('upload.uploadError',"",218);
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts')))) $this->error('upload.uploadFailed',"",218);
        if ($file->checkExt('php,sh')) $this->error('upload.uploadFailed',"",218);
        $this->safe = boolval(input('safe'));
        $this->uptype = $this->getUploadType();
        $this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $name = File::name($file->getPathname(), $this->extend, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
        if (is_array($info) && isset($info['url'])) {
            $img = $this->safe ? $name : getInfo('domain_api').$info['url'];
        } else {
            $this->error('upload.uploadFailed',"",218);
        }
        $this->success("success", $img);
    }
    /**
     * 获取文件上传方式
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getUploadType()
    {
        $this->uptype = input('uptype');
        if (!in_array($this->uptype, ['local', 'oss', 'qiniu'])) {
            $this->uptype = sysconf('storage_type');
        }
        return $this->uptype;
    }

    /**
     * Describe:获取本地上传文件
     * DateTime: 2022/3/15 19:46
     * @return array|\think\File|null
     */
    private function getUploadFile()
    {
        try {
            return $this->request->file('file');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }
    
    /**
     * Describe:提现申请
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function withdraw()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // 设置锁
        $cache_key = "withdraw_settle_{$uid}";
        // Cache::store('redis')->rm($cache_key); 
        
        $boolg =Cache::store('redis')->rawCommand('set',$cache_key, '1',"EX",10,"NX");
        if(!$boolg){
            $this->error('Queuing please try again later.',"", 218);
        }
        // if (Cache::store('redis')->get($cache_key)) {
        //     $this->error('Queuing please try again later.',"", 218);
        // }
        // Cache::store('redis')->set($cache_key, time(),600);
        // // 任务结束触发
        //  register_shutdown_function(function () use ($cache_key) {
        //      Cache::store('redis')->rm($cache_key); 
        //  });
        
        $user = Db::name("LcUser")->find($uid);
        
        
		//用户被锁定
        if ($user['clock'] == 1) $this->error('login.userLocked',"",218);

        //是否允许提现
        if ($user['is_withdrawal'] == 0) $this->error('login.userLocked',"",218);

        //vip等级是否足够
        if ($user['mid'] < 8006) $this->error('VIP level not met',"",218);

        //vip等级是否足够
        // if ($user['withdrawable'] < 20000) $this->error('Withdrawal balance less than 20000',"",218);
        
	//	if ($user['withdrawable'] > 5000000) $this->error('Withdrawal balance less than 5000000',"",218);
        //判断认证状态
        // if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //判断参数
        if(empty($params['id'])||empty($params['money'])) $this->error('utils.parameterError',"",218);
        
        $wallet = Db::name('LcUserWallet')->where(['deleted_at' => '0000-00-00 00:00:00'])->field('id,wid,type,name,wname,account')->find($params['id']);
        if(empty($wallet)) $this->error('utils.parameterError',"",218);

        //判断该账户是否被拉黑
        $black_wallet = Db::name('LcBlackWallet')
            ->whereRaw('deleted_at IS NULL')
            ->where('account', $wallet['account'])
            ->find();
        if(!empty($black_wallet)) $this->error('login.userLocked',"",218);
        $wname = '';
        if($wallet['type']==1){
            $wname = $wallet['wname']." (".substr($wallet['account'],0,4).'****'.substr($wallet['account'],strlen($wallet['account'])-4,strlen($wallet['account'])).")";
        }else{
            $wname = $wallet['wname']." (".substr($wallet['account'],0,2).'****'.substr($wallet['account'],strlen($wallet['account'])-2,strlen($wallet['account'])).")";
        }
        
        //判断余额，可提现金额=用户余额-冻结金额
        //判断实际余额
        // $act_user_money = $user['money']-$user['frozen_money'];
        $act_user_money = $user['withdrawable'];
        
        $money_usd = $params['money'];
        
        if($act_user_money<$params['money']) $this->error('utils.parameterError',"",218);
        
        //判断最低提现金额
        $currency = Cache::store('redis')->hget("withdrawal_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("withdrawal_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        if($currency['withdraw_min']>$params['money']) $this->error('Withdrawal balance less than '.$currency['withdraw_min'],"",218);
        
        $orderNo = 'OUT' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        //提现状态：处理中0
        $status = 0;
        
        //时区转换
        $time = date('Y-m-d H:i:s');
        $time_zone = $currency['time_zone'];
        $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        // $currency = getCurrencyByLanguage($language);
        
        //判断手续费
        $charge = 0;
        $withdrawMethod = Cache::store('redis')->hget("withdrawal_method","method_by_".$wallet['wid']);
        if(empty($withdrawMethod)){

            $withdrawMethod = Db::name('LcUserWithdrawMethod')->where(['show' => 1,'delete' => 0])->find($wallet['wid']);
        
            if(!empty($withdrawMethod))Cache::store('redis')->hset("withdrawal_method","method_by_".$wallet['wid'],json_encode($withdrawMethod));
        }else{
            $withdrawMethod =  json_decode($withdrawMethod,true);
        }
        if($withdrawMethod['charge']>0){
            $charge = $withdrawMethod['charge']*$money_usd/100;
        }
        
        //提现类型为USDT
        if($wallet['type']==1){
            //判断优盾钱包
            $merchant = Db::name('LcMerchant')->find(1);
            //开启了自动代付
            if (!empty($merchant)&&$merchant['auto']) {
                //判断代付金额
                $dfMoney = explode(",",$merchant['df_money']);
                
                if($money_usd>=$dfMoney[0]&&$money_usd<=$dfMoney[1]){
                    //判断地址有效性
                    $json= json_decode(checkAddress($wallet['account']), true);
                    if($json['code']==200){
                        //发起代付
                        $json= json_decode(proxypay($wallet['account'],$money_usd-$charge,$orderNo), true);
                        if($json['code']==200){
                            //判断是否开启自动审核
                            if ($merchant['auto_audit']) {
                                $status = 4;
                            }else{
                                $status = 3;
                            }
                        }
                    }
                }
            }
            
        }
        
        //添加提现记录
        $insert = array(
            "uid" =>$uid,
            "wid" =>$wallet['id'],
            "wname" =>$wname,
            "orderNo" =>$orderNo,
            "status" =>$status,
            "money" =>$money_usd,
            "money2" => 0,
            "charge" =>$charge,
            "currency" =>$language,
            "time_zone" =>$time_zone,
            "act_time" =>$act_time,
            "time" =>$time
        );
        $wrid = Db::name('LcUserWithdrawRecord')->insertGetId($insert);
        if(!empty($wrid)){
            //流水添加
            addFunding($uid,$money_usd,0,2,3,$language);
            //余额扣除
            setNumber('LcUser', 'withdrawable', $money_usd, 2, "id = $uid");
        }
        
        $this->success("success");
    }
    /**
     * Describe:提现记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function withdrawRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('LcUserWithdrawRecord uwr,lc_user_wallet uw')->field("uwr.money,uwr.money2,uwr.currency,uwr.status,date_format(uwr.act_time, '%d %M %Y · %H:%i') as act_time,uwr.wname,uw.type as wtype")->where("uwr.uid = $uid AND uwr.wid = uw.id AND uwr.money > 0")->order("uwr.act_time desc")->page($page,$listRows)->select();
        $length = Db::name('LcUserWithdrawRecord uwr,lc_user_wallet uw')->where("uwr.uid = $uid AND uwr.wid = uw.id AND uwr.money > 0")->order("uwr.act_time desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:资金记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function fundingRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        $type = $params["type"];
        //tabs状态，0全部1收入2支出
        $where = "";
        if($type==1){
            $where = 'type = 1';
        }elseif ($type==2) {
            $where = 'type = 2';
        }
        
        $list = Db::name('LcUserFunding')->field("money,money2,type,fund_type,currency,date_format(act_time, '%d %M %Y · %H:%i') as act_time")->where("uid = $uid")->where($where)->order("time desc")->page($page,$listRows)->select();
        $length = Db::name('LcUserFunding')->where("uid = $uid")->where($where)->order("time desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:获取充值方式
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRechargeMethod()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name("LcUser")->find($uid);
        
        //判断认证状态
        // if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //获取指定国别的充值方式
        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        
        if(empty($currency)) $this->error('utils.parameterError',"",218);
        
        $rechargeMethod = Cache::store('redis')->hget("recharge_method","method_".$currency['id']);
        if(empty($rechargeMethod)){

            $rechargeMethod = Db::name('LcUserRechargeMethod')->where(['show' => 1,'delete' => 0,'cid' => $currency['id']])->order('sort asc,id desc')->select();
            Cache::store('redis')->hset("recharge_method","method_".$currency['id'],json_encode($rechargeMethod));
        }else{
            $rechargeMethod =  json_decode($rechargeMethod,true);
        }
        
        
        $commonMoney = explode(",",$currency['common_money']);
        
        $data = array(
            "rechargeMethod" =>$rechargeMethod,
            "minMoney" =>$currency['recharge_min'],
            "commonMoney" =>$commonMoney,
            // "userBalance" =>changeMoneyByLanguage($user['money'],$language),
        );
        $this->success("success", $data);
    }
    /**
     * Describe:获取充值详情
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRechargeById()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        
        
        //判断认证状态
        // if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        $recharge = Cache::store('redis')->hget("recharge_method","method_id_". $params['id']);
        if(empty($recharge)){

            $recharge = Db::name('LcUserRechargeMethod')->where(['show' => 1,'delete' => 0 ])->find($params['id']);
            Cache::store('redis')->hset("recharge_method","method_id_". $params['id'],json_encode($recharge));
        }else{
            $recharge =  json_decode($recharge,true);
        }
        if(empty($recharge)) $this->error('utils.parameterError',"",218);
        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        // if($currency['recharge_min']-$params['money']>0) $this->error('utils.parameterError',"",218);
        
        //充值方式为优盾USDT免提交
        if($recharge['type']==6){
            $user = Db::name("LcUser")->find($uid);
            $usdt_qrcode = $user["usdt_qrcode"];
            $usdt_address = $user["usdt_address"];
            
            //判断用户钱包地址是否创建
            if(empty($user['usdt_address'])){
                //创建usdt钱包
                $json= json_decode(createAddress(), true);
                if($json['code']!=200){
                    $this->error('utils.networkException',"",218);
                }
                $qrCode = new QrCode();
                $qrCode->setText($json['data']['address']);
                $qrCode->setSize(300);
                $usdt_qrcode = $qrCode->getDataUri();
                $usdt_address = $json['data']['address'] ;
                Db::name('LcUser')->where('id', $uid)->update(['usdt_address'=>$usdt_address,'usdt_qrcode'=>$usdt_qrcode]);
            }
            
            $recharge['account'] = $usdt_address;
            $recharge['img'] = $usdt_qrcode;
        }
        
        $data = array(
            "recharge" =>$recharge,
            "rate" =>$currency['price'],
            "currency" => $currency['symbol']
        );
        $this->success("success", $data);
    }
   /**
     * Describe:充值
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recharge()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        
        //判断认证状态
        if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);

        // 判断是否允许充值
        if(empty($user['is_recharge'])) $this->error('login.userLocked',"",218);
        
        //判断参数
        if(empty($params['id'])||empty($params['money'])) $this->error('utils.parameterError',"",218);

        $rechargeMethod = Cache::store('redis')->hget("recharge_method","method_id_". $params['id']);
        if(empty($rechargeMethod)){

            $rechargeMethod = Db::name('LcUserRechargeMethod')->find($params['id']);
            Cache::store('redis')->hset("recharge_method","method_id_". $params['id'],json_encode($rechargeMethod));
        }else{
            $rechargeMethod =  json_decode($rechargeMethod,true);
        }
        if(empty($rechargeMethod)) $this->error('utils.parameterError',"",218);

        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){
            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }

        $hash = '';
        $voucher = '';
        
        //金额转换
        $money_usd = $params['money'];
        
        $orderNo = 'IN' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        
        //时区转换
        $time = date('Y-m-d H:i:s');
        $time_zone = $currency['time_zone'];
        $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        // $currency = getCurrencyByLanguage($language);
        $data = [];
        //ffpay
        if($rechargeMethod['type']==3){
            $tool = new Ff();
            $data['order_no'] = $orderNo;
            $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; //金额是到分,平台金额是元需要除100
            $res = $tool->send_pay($data);
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // var_dump($res);die;
            if ($res['status'] != 1) {
                $this->error("error");
            }

            if ($res['status'] != 1) {
                $this->error('Payment failed',"",218);
            }
            //yomipay 
        }else if($rechargeMethod['type']==10){
            $tool = new yomipay();
            $data['order_no'] = $orderNo;
            $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; //金额是到分,平台金额是元需要除100
            $res = $tool->send_pay($data);
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // print_r('   ');
            // print_r($res);
            // var_dump($res);die;
            if ($res['status'] != 'success') {
                $this->error("error");
            }

            if ($res['status'] != 'success') {
                $this->error('Payment failed',"",218);
            }
            // $res['payurl'] = $res['url'];
            //其他充值方式需上传凭证 
        }else if($rechargeMethod['type']==11){  //wowpay
            $tool = new wowPay();
            $data['order_no'] = $orderNo;
            $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; //金额是到分,平台金额是元需要除100
            $res = $tool->send_pay($data);
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // var_dump($res);die;
            if ($res['code'] != 'SUCCESS') {
                $this->error("error");
            }

            if ($res['code'] != 'SUCCESS') {
                $this->error('Payment failed',"",218);
            }
            //baoxue
        }else if($rechargeMethod['type']==12){  
            $tool = new baoxuepay();
            $data['order_no'] = $orderNo;
            $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; //金额是到分,平台金额是元需要除100
            $res = $tool->send_pay($data);
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // var_dump($res);die;
            if ($res['code'] != '200') {
                $this->error('error');
            }

            if ($res['code'] != '200') {
                $this->error('Payment failed',"",218);
            }
           
        }else if($rechargeMethod['type']==13){    //13 onepay
            $tool = new tool();
            $data['order_no'] = $orderNo;
            // $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; //金额是到分,平台金额是元需要除100
            $res = $tool->send_pay($data);
            // dump($res);exit;
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // var_dump($res);die;
            if ($res['code'] != '200') {
                $this->error('error');
            }

            if ($res['code'] != '200') {
                $this->error('Payment failed',"",218);
            }
           
        }else if($rechargeMethod['type']==14){    //14 jmpay
            $tool = new jmpay();
            $data['order_no'] = $orderNo;
            // $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; //金额是到分,平台金额是元需要除100
            $res = $tool->send_pay($data);
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // var_dump($res);die;
            if ($res['code'] != '200') {
                $this->error('error');
            }

            if ($res['code'] != '200') {
                $this->error('Payment failed',"",218);
            }
           
        }else if($rechargeMethod['type']==15){    //15 xlpay
            $tool = new xlpay();
            $data['order_no'] = $orderNo;
            // $data['pay_code'] = $params['pay_code'];
            $data['amount'] = $money_usd; 
            $res = $tool->send_pay($data);
            $res = !empty($res) ? json_decode($res, true) : [];
            // print_r($data);
            // var_dump($res);die;
            if (!empty($res['status'])) {
                $this->error('error');
            }

            if (!empty($res['status'])) {
                $this->error('Payment failed',"",218);
            }
           
        }else{
            if(empty($params['voucher'])) $this->error('utils.parameterError',"",218);
            $voucher = $params['voucher'];
        }
        
        //添加充值记录
        $insert = array(
            "uid" =>$uid,
            "rid" =>$rechargeMethod['id'],
            "orderNo" =>$orderNo,
            "money" =>$money_usd,
            "money2" =>changeMoneyByLanguage($money_usd,$language),
            "hash" =>$hash,
            "voucher" =>$voucher,
            "currency" =>$language,
            "time_zone" =>$time_zone,
            "act_time" =>$act_time,
            "time" =>$time
        );
        $rrid = Db::name('LcUserRechargeRecord')->insertGetId($insert);
        if(!empty($rrid)){
            $req = [];
            if ($rechargeMethod['type']==3) {
                $req = ['paymentUrl' => $res['payurl']];
            }else if($rechargeMethod['type']==10){
                $req = ['paymentUrl' => $res['url']];
            }else if($rechargeMethod['type']==11){
                $req = ['paymentUrl' => $res['data']['url']];
            }else if($rechargeMethod['type']==12){
                $req = ['paymentUrl' => $res['data']['payUrl']];
            }else if($rechargeMethod['type']==13){
                $req = ['paymentUrl' => $res['data']['paymentUrl']];
            }else if($rechargeMethod['type']==14){
                $req = ['paymentUrl' => $res['data']['pay_url']];
            }else if($rechargeMethod['type']==15){
                $req = ['paymentUrl' => $res['paymentUrl']];
            }

            $this->success("success", $req);
        }
        $this->error("error");
    }
     /**
     * Describe:充值记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rechargeRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('LcUserRechargeRecord urr,lc_user_recharge_method urm')->field("urr.money,urr.money2,urr.currency,urr.status,date_format(urr.act_time, '%d %M %Y · %H:%i') as act_time,urm.name,urm.type")->where("urr.uid = $uid AND urr.rid = urm.id AND urr.money > 0")->order("urr.act_time desc")->page($page,$listRows)->select();
        $length = Db::name('LcUserRechargeRecord urr,lc_user_recharge_method urm')->field("urr.money,urr.money2,urr.currency,urr.status,urr.act_time,urm.name")->where("urr.uid = $uid AND urr.rid = urm.id AND urr.money > 0")->order("urr.act_time desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:我的团队
     * DateTime: 2022/3/15 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function myTeam()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        // $uname = substr($user['username'],0,2).'***'.substr($user['username'],strlen($user['username'])-2,strlen($user['username']));
        $uname = $user['username'];
        
        //时区转换，按当前用户时区统计
        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){
            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        $time_zone = $currency['time_zone'];

        $now = dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
        $today = date('Y-m-d 00:00:00',strtotime($now));//当前用户时区 今天0点
        $yesterday = date('Y-m-d 00:00:00', strtotime($now)-86400);//当前用户时区 昨天0点
        
        //数据统计
        // $direct_count = Db::name('LcUserRelation')->where("parentid = $uid AND level = 1")->count();
        // $indirect_count = Db::name('LcUserRelation')->where("parentid = $uid AND level <> 1")->count();
        
        $add_count = Db::name('lc_user_relation')->where("parentid = $uid")->count();
        // $add_count_to = Db::name('LcUser u,lc_user_relation ur')->where("u.act_time BETWEEN '$today' AND '$now' AND u.id=ur.uid AND ur.parentid = $uid")->count();
        // $add_count_ye = Db::name('LcUser u,lc_user_relation ur')->where("u.act_time BETWEEN '$yesterday' AND '$today' AND u.id=ur.uid AND ur.parentid = $uid")->count();
        //总充值人数
        $total_top_up = Db::name('LcUser u,lc_user_relation ur')->where(" u.id=ur.uid AND u.mid !=8005 AND ur.parentid = $uid")->count();
        
        
        $income_sum = Db::name('lc_user_funding')->where("uid = $uid AND ( fund_type = 19 )")->sum('money');
        $income_sum_to = Db::name('lc_user_funding')->where("act_time BETWEEN '$today' AND '$now' AND uid = $uid AND ( fund_type = 19 )")->sum('money');
        // $income_sum_ye = Db::name('lc_user_funding')->where("act_time BETWEEN '$yesterday' AND '$today' AND uid = $uid AND ( fund_type = 19 )")->sum('money');
        
        $qrCode = new QrCode();
        $qrCode->setText(getInfo('domain') . '/#/register?code=' . $user['invite_code']);
        $qrCode->setSize(300);
        $shareCode = $qrCode->getDataUri();
        $shareLink = getInfo('domain') . '/#/register?code=' . $user['invite_code'];

        $vip = Cache::store('redis')->hget("member",$user['mid']);
        if(empty($vip)){
            $memberList = Db::name("LcUserMember")->select();
            foreach ($memberList as &$vip) {
                Cache::store('redis')->hset("member",$vip['id'],json_encode($vip));
            }
            $vip =  json_decode(Cache::store('redis')->hget("member",$user['mid']),true);
        }else{
            $vip = json_decode($vip,true);
        }

        $user_info = array(
            "username" => $uname,
            "auth_email" => $user['auth_email'],
            "auth_google" => $user['auth_google'],
            "auth_phone" => $user['auth_phone'],
            "invite_code" => $user['invite_code'],
            "user_icon" => getInfo('user_img'),
            "share_code" => $shareCode,
            "vip_img" => $vip['logo'],
            "share_link" => $shareLink
        );
        $report = array(
            // "direct_count" => $direct_count,
            // "indirect_count" => $indirect_count,
            "add_count" => $add_count,
            // "add_count_to" => $add_count_to,
            // "add_count_ye" => $add_count_ye,
            "income_to" => $income_sum_to,
            "income" =>$income_sum,
            // "income_ye" => $income_sum_ye,
            "total_top_up" => $total_top_up
        );
        
        $data = array(
            "user_info" => $user_info,
            "report" => $report
        );
        $this->success("success", $data);
    }
    /**
     * Describe:团队列表
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function teamList()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        $level = $params["level"];
        if($level !='1' && $level!='2' && $level!='3'){
            
            $this->error("error");
        }
        
        $list = Db::name('LcUser u,lc_user_relation ur,lc_user_member um')->field('u.id,u.username,u.act_time,ur.level,um.logo')->where("ur.parentid = $uid and ur.level=$level and um.id=u.mid AND ur.uid = u.id")->order("u.act_time desc")->page($page,$listRows)->select();
        $length = Db::name('LcUser u,lc_user_relation ur')->where("ur.parentid = $uid and ur.level=$level AND ur.uid = u.id")->order("u.act_time desc")->count();
        
        foreach ($list as &$user) {
            $uid2 = $user['id'];
            $recharge_sum = Db::name('lc_user_recharge_record')->where("uid=$uid2 AND status=1")->sum('money');
            $user['recharge_sum'] = $recharge_sum;
            $user['username'] = substr($user['username'],0,3).'***'.substr($user['username'],strlen($user['username'])-3,strlen($user['username']));
            $user['act_time'] = date('d M Y · H:i', strtotime($user['act_time']));
        }
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:获取会员详情
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getVip()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        $uname = substr($user['username'],0,2).'***'.substr($user['username'],strlen($user['username'])-2,strlen($user['username']));

        $vip = Cache::store('redis')->hget("member",$user['mid']);
        if(empty($vip)){
            $memberList = Db::name("LcUserMember")->select();
            foreach ($memberList as &$vip) {
                Cache::store('redis')->hset("member",$vip['id'],json_encode($vip));
            }
            $vip =  json_decode(Cache::store('redis')->hget("member",$user['mid']),true);
        }else{
            $vip = json_decode($vip,true);
        }
        
        $vip_next = Db::name('LcUserMember')->where("value > '{$vip['this_value']}'")->order('value asc')->find();
        //不存在下一个级别则为最高级
        if(empty($vip_next)){
            $vip_next = Db::name('LcUserMember')->order('value desc')->find();
        }
        $vip['next_value'] = $vip_next['value'];
        
        $user_info = array(
            "user_icon" => getInfo('user_img'),
            "username" =>$uname,
            "invite_code" =>$user['invite_code'],
            "user_value" =>$user['value'],
            "balance" => $user['money'],
            "income" =>0,
            );
        
        $data = array(
            "vip" =>$vip,
            "user" =>$user_info,
        );
        $this->success("success", $data);
    }
    /**
     * Describe:获取奖励详情
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getReward()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        
        $reward= Db::name('LcReward')->field("register,invite,authentication,login,invest")->find(1);
        if(empty($reward)) $this->error('utils.parameterError',"",218);
        
        $reward['invite'] = $reward['invite'];
        $reward['authentication'] = $reward['authentication'];
        $reward['invest'] = $reward['invest'];
        $reward['authentication_status'] = false;
        $reward['invest_status'] = false;
        
        //判断领取状态
        $auth_status = $user['auth_email']+$user['auth_phone']+$user['auth_google'];
        
        $invest_status = 0;
        $now = date('Y-m-d H:i:s');//现在
        $today = date('Y-m-d');//今天0点
        $yesterday = date('Y-m-d 00:00:00', strtotime($now)-86400);//当前用户时区 昨天0点
        $invest_today = getFundingByTime($today,$now,$uid,11);
        
        $reward_today = Db::name('LcUserFunding')->where("time >= '$today' AND time <= '$now' AND uid = '{$uid}' AND fund_type in (7,8,9,10,11)")->sum('money');
        // $reward_yesterday = Db::name('LcUserFunding')->where("time >= '$yesterday' AND time <= '$today' AND uid = '{$uid}' AND fund_type in (7,8,9,10,11)")->sum('money');
        $reward_total = Db::name('LcUserFunding')->where("uid = '{$uid}' AND fund_type in (7,8,9,10,11)")->sum('money');
        
        if(!empty($invest_today)) $invest_status = 1;
        
        $user_info = array(
            "balance" =>$user['money'],
            "today" =>$reward_today,
            "yesterday" =>0,
            "total" =>$reward_total,
            "auth_status"=>$auth_status==0?0:($auth_status."/3"),
            "invest_status"=>$invest_status
            );
        
        $data = array(
            "reward" =>$reward,
            "user" =>$user_info,
        );
        $this->success("success", $data);
    }
    /**
     * Describe:奖励记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rewardRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('LcUserFunding')->field("money,money2,type,fund_type,currency,act_time")->where("uid = $uid")->where("fund_type in (7,8,9,10,11)")->order("act_time desc")->page($page,$listRows)->select();
        $length = Db::name('LcUserFunding')->where("uid = $uid")->where("fund_type in (7,8,9,10,11)")->order("act_time desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:投资
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function invest()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        $number = $params["number"];
        
        // 设置锁
        $cache_key = "invest_{$uid}";

        if(Cache::store('redis')->get($cache_key)){
            $this->error('Queuing please try again later.',"", 218);
        }else{
            $boolg =Cache::store('redis')->rawCommand('set',$cache_key, '2',"EX",5,"NX");
            if(!$boolg){
                $this->error('Queuing please try again later.',"", 218);
            }
        }
        //判断认证状态
        if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //判断参数
        if(empty($params['id'])) $this->error('utils.parameterError',"",218);

        $item = Cache::store('redis')->hget("itemsdetail",$params['id']);
        if(Cache::store('redis')->hget('itemsdetail',$params['id'])){
            $item = json_decode(Cache::store('redis')->hget('itemsdetail',$params['id']),true);
        }else{
            $item = Db::name('LcItem')->where(['show' => 1])->find($params['id']);
            $item['title'] = $item['title_en_us'];
            $item['content'] = $item['content_en_us'];
            $item['img'] = $item['img2'];
            Cache::store('redis')->hset('itemsdetail',$params['id'],json_encode($item));
        }
        
        if(empty($item)) $this->error('utils.parameterError',"",218);
    //    dump($item);exit;
       if(!isset($item['vip_level'])){
 $item = Db::name('LcItem')->where(['show' => 1])->find($params['id']);
            $item['title'] = $item['title_en_us'];
            $item['content'] = $item['content_en_us'];
            $item['img'] = $item['img2'];
            Cache::store('redis')->hset('itemsdetail',$params['id'],json_encode($item));
       }
        // 判断vip等级是否满足
        if ($user['mid'] < $item['vip_level']) {
            $this->error('vip level not met',"",218);
        }

        // 判断积分是否足够
        if (!empty($item['need_integral']) && $item['need_integral'] > $user['point'] && !$params['is_withdrawal_purchase']) {
            $this->error('Insufficient points',"",218);
        }

        // 判断项目是否上架
        if (empty($item['show'])) {
            $this->error('Not listed',"",218);
        }
        if($item['type']==5){
            if($number<$item['min']){
                $this->error('invest.investMin',"",218);
            }
            $item['min']=$number;
        }
         $money_usd = $item['min'];
        //金额转换
        //判断余额/提现余额>投资金额
        $is_withdrawal_purchase = 0;
        if ($params['is_withdrawal_purchase']) {
            $money_usd = floor($money_usd*$item['withdrawal_purchase']) / 100;
            $is_withdrawal_purchase=1;
            if($user['withdrawable']<$money_usd) $this->error('invest.moneyNotEnough',"",218);
        }else {
            if($user['money']<$money_usd) $this->error('invest.moneyNotEnough',"",218);
        }
        
        //判断投资金额
        // if ($money_usd - $item['max'] > 0 || $money_usd - $item['min'] < 0) $this->error('utils.parameterError',"",218);
       
        //判断投资次数
       //非定投
        if($item['type']!=5 && $item['type']!=8){
            $investCount = Db::name('LcInvest')->where(['itemid' => $item['id'],'uid' => $uid,'status'=>0])->count();
            if($investCount>=$item['num']) $this->error('invest.investNumEmpty',"",218);
        }else{
            //定投
            $investCount = Db::name('LcInvest')->where(['itemid' => $item['id'],'uid' => $uid])->count();
            if($investCount>=$item['num']) $this->error('invest.investNumEmpty',"",218);
        }
        
        if($item['id']==335){
            
            $investCount = Db::name('LcInvest')->where(['itemid' => $item['id'],'uid' => $uid])->count();
            if($investCount>=$item['num']) $this->error('invest.investNumEmpty',"",218);
        }
        if($item['id']==342){
            
            $investCount = Db::name('LcInvest')->where(['itemid' => $item['id'],'uid' => $uid])->count();
            if($investCount>=$item['num']) $this->error('invest.investNumEmpty',"",218);
        }
        //判断会员投资次数
        // $now = date('Y-m-d H:i:s');//现在
        // $today = date('Y-m-d');//今天0点
        // $investCountToday = Db::name('LcInvest')->where(['itemid' => $item['id'],'uid' => $uid])->where("time >= '$today' AND time <= '$now'")->count();
        $vip = Cache::store('redis')->hget("member",$user['mid']);
        if(empty($vip)){
            $memberList = Db::name("LcUserMember")->select();
            foreach ($memberList as &$vip) {
                Cache::store('redis')->hset("member",$vip['id'],json_encode($vip));
            }
            $vip =  json_decode(Cache::store('redis')->hget("member",$user['mid']),true);
        }else{
            $vip = json_decode($vip,true);
        }
        // if($investCountToday>=$vip['invest_num']) $this->error('invest.investNumEmpty',"",218);
        
        //时区转换
        $time = date('Y-m-d H:i:s');

        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }
        $time_zone = $currency['time_zone'];
        $time_actual = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        
       
        $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $item['day'] . ' day'));
        $total_interest = $item['min'] * $item['rate'] / 100;
        $total_num = 1;
        
        //到期还本付息（时）
        if($item['type']==3){
            //按时
            $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $item['day'] . ' hour'));
        }
        //每日付息到期不还本
        elseif($item['type']==1 || $item['type']==4){
            //日利率
            $total_interest = intval($item['min'] * $item['rate']/ 100 )* $item['day'] ;
            //返息期数
            $total_num = $item['day'];
            //定投
        }elseif($item['type']==5){
            $total_num = $item['day'];
            //8小时
        }elseif($item['type']==6){
            //日利率
            $total_interest = intval($item['min'] * $item['rate']/ 100 )* $item['day'] ;
            //返息期数
            $total_num = $item['day'];
            //6小时
        }elseif($item['type']==7){
            //日利率
            $total_interest = intval($item['min'] * $item['rate']/ 100 )* $item['day'] ;
            //返息期数
            $total_num = $item['day'];
            //机器定投
        }elseif($item['type']==8){
            $total_num = $item['day'];

            //税务机器
        }elseif($item['type']==9){
            //日利率
            $total_interest = intval($item['min'] * $item['rate']/ 100 )* $item['day'] ;
            //返息期数
            $total_num = $item['day'];
            //购买了税务机器后 可以发起提现
            //修改用户的提现的权限
            Db::name('LcUser')->where("id=$uid")->update(['is_withdrawal' => '1']);
            //加速卡
        }elseif($item['type']==10){
            // $total_num = $item['day'];
            //12小时机器
        }elseif($item['type']==12){
            //日利率
            $total_interest = intval($item['min'] * $item['rate']/ 100 )* $item['day'] ;
            //返息期数
            $total_num = $item['day'];
            
        }
        $time2_actual = dateTimeChangeByZone($time2, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        
        //查询是第一次购买该产品还是复购
        $investNum =  Db::name('LcInvest')->where(['uid' => $uid])->where("itemid != 235")->count();
        
        $orderNo = 'ST' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        
        //添加投资记录
        $insert = array(
            "uid" =>$uid,
            "itemid" =>$item['id'],
            "orderNo" =>$orderNo,
            "money" =>$item['min'],
            "money2" =>$money_usd,
            "total_interest" =>$total_interest,
            "wait_interest" =>$total_interest,
            "total_num" =>$total_num,
            "wait_num" =>$total_num,
            "day" =>$item['day'],
            "rate" =>$item['rate'],
            "type" =>$item['type'],
            "not_receive" =>$item['not_receive'],
            "is_distribution" =>$item['is_distribution'],
            "currency" =>$currency['symbol'],
            "time_zone" =>$time_zone,
            "time" =>$time,
            "time_actual" =>$time_actual,
            "time2" =>$time2,
            "time2_actual" =>$time2_actual,
            "is_withdrawal_purchase"=>$is_withdrawal_purchase
        );
        
        Db::startTrans();
        $iid = Db::name('LcInvest')->insertGetId($insert);
        if(!empty($iid)){
           
            if ($params['is_withdrawal_purchase']) {
                 //流水添加
                addFunding($uid,$money_usd,changeMoneyByLanguage($money_usd,$language),2,5,$language,2);
                //提现余额扣除
                setNumber('LcUser', 'withdrawable', $money_usd, 2, "id = $uid");
            }else {
                 //流水添加
                addFunding($uid,$money_usd,changeMoneyByLanguage($money_usd,$language),2,5,$language);
                //余额扣除
                setNumber('LcUser', 'money', $money_usd, 2, "id = $uid");
            }
            //冻结金额扣除
            if($user['frozen_money']>$money_usd){
                setNumber('LcUser', 'frozen_money', $money_usd, 2, "id = $uid");
            }else{
                setNumber('LcUser', 'frozen_money', $user['frozen_money'], 2, "id = $uid");
            }
            if ($item['need_integral']>0) {
                // 积分扣除
                setNumber('LcUser', 'point', $item['need_integral'], 2, "id = $uid");
                addIntegral($uid,$item['need_integral'],2,2,$language);
            }
            // 积分赠送
            if($item['gifts_integral']>0){
                setNumber('LcUser','point',$money_usd, 1, "id = $uid");
                addIntegral($uid,$item['gifts_integral'],1,2,$language);
            }
            //添加每日投资奖励
            // $reward = Db::name('LcReward')->find(1);
            // if($reward['invest']>0){
            //     $now = date('Y-m-d H:i:s');//现在
            //     $today = date('Y-m-d');//今天0点
            //     $login_today = getFundingByTime($today,$now,$uid,11);
            //     //判断今日是否奖励
            //     if(empty($login_today)){
            //         //流水添加
            //         addFunding($uid,$reward['invest'],changeMoneyByLanguage($reward['invest'],$language),1,11,$language);
            //         //添加余额
            //         setNumber('LcUser', 'money', $reward['invest'], 1, "id = $uid");
            //         //添加冻结金额
            //         if(getInfo('recharge_need_flow')){
            //             setNumber('LcUser', 'frozen_money', $reward['invest'], 1, "id = $uid");
            //         }
            //     }
            // }
            //添加抽奖次数
            // $draw = Db::name('LcDraw')->find(1);
            // if($draw['invest']>0){
            //     setNumber('LcUser', 'draw_num', $draw['invest'], 1, "id = $uid");
            // }
            if ($item['superior_draw_num'] > 0) {
                //如果是第一次购买送上级 如果不是就不送
                if($investNum<1){
                    // 上级
                    $parentid = Db::table('lc_user_relation')->where("uid=$uid and level=1")->value('parentid');
                    if ($parentid) {
                        setNumber('LcUser', 'draw_num', $item['superior_draw_num'], 1, "id = $parentid");
                    }
                }
            }
            if($item['superior_money']>0){
                if($investNum<1) {
                    $parentid = Db::table('lc_user_relation')->where("uid=$uid and level=1")->value('parentid');
                    $parentInfo=Db::name('lc_user')->where("id=$parentid")->find();
                    $sysUserInfo=Db::name('system_user')->where('id',$parentInfo['system_user_id'])->whereLike('username','%DD%')->find();
                    if ($parentid && !empty($sysUserInfo)) {
                        addFunding($parentid, $item['superior_money'], $item['superior_money'], 1, 11, $language);
                        //添加余额
                        setNumber('LcUser', 'withdrawable', $item['superior_money'], 1, "id = $parentid");
                    }
                }
            }

            if ($item['draw_num'] > 0) {
                //如果之前购买过该产品 就送抽奖 每购买过不送
                if($investNum>0){
                    // 购买者
                    setNumber('LcUser', 'draw_num', $item['draw_num'], 1, "id = $uid");
                }
            }

            // 当前用户没有等级的话就直接升等级一            
            if ($vip['value'] == 0) {
                $vip_next = Db::name('LcUserMember')->where("value > '{$vip['value']}'")->order('value asc')->find();
                if(!empty($vip_next)){
                    Db::name('LcUser')->where("id = {$user['id']}")->update(['mid' => $vip_next['id']]);
                }
            }
            

            Db::commit();
            $this->success("success");
        }else{
            Db::rollback();
        }
        $this->error("error");
    }
    /**
     * Describe:兑换商品
     * DateTime: 2023/4/15
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_exchange()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        $id = $params["id"];
        
        $goods  = Db::name('LcGoods')->find($id);
        if(empty($goods)) $this->error('utils.parameterError',"",218);
        
        //判断积分
        if($user['point']-$goods['point']<0) $this->error('utils.parameterError',"",218);
        
        
        //时区转换
        $time = date('Y-m-d H:i:s');
        $time_zone = getTimezoneByLanguage($language);
        $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        
        $add = array(
            'uid' => $uid,
            'gid' => $goods['id'],
            'point' => $goods['point'],
            'time_zone' => $time_zone,
            'time' => $time,
            'act_time' => $act_time,
        );
        $iid = Db::name('LcGoodsRecord')->insertGetId($add);
        if(empty($iid)) $this->error('utils.networkException',"",218);
        //积分减少
        $point_now = $user['point'] - $goods['point'];
        Db::name('LcUser')->where(['id' => $uid])->update(['point' => $point_now]);
        
        $this->success("success");
    }
    /**
     * Describe:兑换记录
     * DateTime: 2023/4/16
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goodsRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('lc_goods_record gr,lc_goods g')->field("gr.act_time,g.title_$language as title,g.img,g.point")->where("gr.gid = g.id")->where("uid = $uid")->order("gr.act_time desc")->page($page,$listRows)->select();
        $length = Db::name('lc_goods_record gr,lc_goods g')->field("gr.act_time,g.title_$language as title,g.img,g.point")->where("gr.gid = g.id")->where("uid = $uid")->order("gr.act_time desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:投资记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
   public function investRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('LcInvest')->field("id,itemid,money,day,rate,total_interest,wait_interest,type,status,currency,time_zone,time_actual,time2_actual,time,time2,total_num,wait_num,pause_time,source")->where("uid = $uid and status =0")->order("time desc")->page($page,$listRows)->select();
        $length = Db::name('LcInvest')->where("uid = $uid and status = 0")->count();
        $time = date('Y-m-d');
        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }

        $time_zone = $currency['time_zone'];

        $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d');
        
        // $time1 = date('Y-m-d');
        // $time_zone1 = getTimezoneByLanguage($language);
        // $act_time1 = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone1, 'Y-m-d');
        
        $w = date("w",strtotime($act_time));//获取星期几;
        $is_w = 0;
        if (in_array($w, [1,2,3,4,5])) {
            $is_w = 1;
        } else if($w == 6) {
            $is_w = 2;
        } else if($w == 0) {
            $is_w = 3;
        }
        
        
        
        foreach ($list as &$invest) {
            $invest['is_receive'] = 1;
            $item = Db::name('LcItem')->find($invest['itemid']);
            $invest['img'] = $item['img2'];
            $Date_1=$act_time;
            $Date_2=date("Y-m-d", strtotime($invest['time_actual']));
            // 判断是否有领取
            if ($invest['type'] == 1 || $invest['type'] == 4) {
                
                $d1=strtotime($Date_1);
                $d2=strtotime($Date_2);
                $day_diff=round(($d1-$d2)/3600/24);
               
                if (!empty($day_diff)) {
                    $wait_day = $day_diff - ($invest['total_num'] - $invest['wait_num']);
                    $invest['is_receive'] = $wait_day > 0 ? 1 : 0;
                    
                }
                 if (!empty($day_diff)) {
                     
                    // $invest['is_receive'] = $day_diff > 0 ? 1 : 0;
                }else{
                    $invest['is_receive'] = 0;
                }
                
            }
            elseif($invest['type'] == 6){
                 //待领取数据>=1才可以
                if($invest['wait_num'] <1){
                    $invest['is_receive'] = 0;
                }
                 $d1=strtotime($Date_1);
                $d2=strtotime($Date_2);
                $day_diff=round(($d1-$d2)/3600/24);
                //避免当天购买就可以领
                if (!empty($day_diff)) {
                    $invest['is_receive'] = $day_diff > 0 ? 1 : 0;
                }else{
                    $invest['is_receive'] = 0;
                }
               
                //不是购买同一天  并且 时差超过8小时就可以领取
                $datetime = date('Y-m-d H:i:s');
                //当前用户时区时间
                $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
                //用户最后领取时间
                $datetime2=date("Y-m-d H:i:s", strtotime($invest['time2_actual']));
                
                $d11=strtotime($act_date_time);
                $d21=strtotime($datetime2);   
                
                
                
                //判断时差是否有8小时
                $day_diff2=round(($d11-$d21)/60);
                if(!empty($day_diff2) && ($day_diff2 >=480 || $day_diff2 <-480)){
                    $invest['timenumber'] = $day_diff2;
                }else{
                    $invest['is_receive'] = 0;
                    $invest['timenumber'] = $day_diff2;
                }
                if($day_diff2 <480 && $day_diff2 >=0){
                   //下次领取时间  //加上八小时
                   $invest['lasttime'] = date('d M Y · H:i', $d21 + 28800); 
                   
                   //第一次领取 显示第二天0点
                }elseif($day_diff2 <0){
                    $d222=strtotime($Date_2);
                    $invest['lasttime'] =  date('d M Y · H:i', $d222 + 86400);
                }
            }
            elseif($invest['type'] == 7){  //6小时领一次的产品
                //待领取数据>=1才可以
               if($invest['wait_num'] <1){
                   $invest['is_receive'] = 0;
               }
                $d1=strtotime($Date_1);
               $d2=strtotime($Date_2);
               $day_diff=round(($d1-$d2)/3600/24);
               //避免当天购买就可以领
               if (!empty($day_diff)) {
                   $invest['is_receive'] = $day_diff > 0 ? 1 : 0;
               }else{
                   $invest['is_receive'] = 0;
               }
              
               //不是购买同一天  并且 时差超过6小时就可以领取
               $datetime = date('Y-m-d H:i:s');
               //当前用户时区时间
               $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
               //用户最后领取时间
               $datetime2=date("Y-m-d H:i:s", strtotime($invest['time2_actual']));
               
               $d11=strtotime($act_date_time);
               $d21=strtotime($datetime2);   
               
               
               
               //判断时差是否有6小时
               $day_diff2=round(($d11-$d21)/60);
               if(!empty($day_diff2) && ($day_diff2 >=360 || $day_diff2 <-360)){
                   $invest['timenumber'] = $day_diff2;
               }else{
                   $invest['is_receive'] = 0;
                   $invest['timenumber'] = $day_diff2;
               }
               if($day_diff2 <360 && $day_diff2 >=0){
                  //下次领取时间  //加上6小时
                  $invest['lasttime'] = date('d M Y · H:i', $d21 + 21600); 
                  
                  //第一次领取 显示第二天0点
               }elseif($day_diff2 <0){
                   $d222=strtotime($Date_2);
                   $invest['lasttime'] =  date('d M Y · H:i', $d222 + 86400);
               }
           }
            elseif($invest['type'] == 8){  //机器定投
                $Date_2=date("Y-m-d", strtotime($invest['time2_actual']));
                $invest['is_receive'] = $act_time >= $Date_2 ? 1 : 0;
                $invest['time_actual'] = date('d M Y · H:i', strtotime($invest['time_actual']));
                $invest['lasttime'] = date('d M Y · H:i', strtotime($invest['time2_actual']));
           }
            elseif($invest['type'] == 5){  //定投
                $Date_2=date("Y-m-d", strtotime($invest['time2_actual']));
                $invest['is_receive'] = $act_time >= $Date_2 ? 1 : 0;
                $invest['time_actual'] = date('d M Y · H:i', strtotime($invest['time_actual']));
                $invest['lasttime'] = date('d M Y · H:i', strtotime($invest['time2_actual']));
           }
            elseif($invest['type'] == 9){  //税务机器
                $d1=strtotime($Date_1);
                $d2=strtotime($Date_2);
                $day_diff=round(($d1-$d2)/3600/24);
            
                if (!empty($day_diff)) {
                    $wait_day = $day_diff - ($invest['total_num'] - $invest['wait_num']);
                    $invest['is_receive'] = $wait_day > 0 ? 1 : 0;
                    
                }
                if (!empty($day_diff)) {
                    
                    // $invest['is_receive'] = $day_diff > 0 ? 1 : 0;
                }else{
                    $invest['is_receive'] = 0;
                }
           }
            elseif($invest['type'] == 10){  //加速卡
                $Date_2=date("Y-m-d", strtotime($invest['time2_actual']));
                $invest['is_receive'] = $act_time >= $Date_2 ? 1 : 0;
                $invest['time_actual'] = date('d M Y · H:i', strtotime($invest['time_actual']));
                $invest['lasttime'] = date('d M Y · H:i', strtotime($invest['time2_actual']));
            }
            elseif($invest['type']== 12){ //12小时领一次的机器
                //待领取数据>=1才可以
                if($invest['wait_num'] <1){
                    $invest['is_receive'] = 0;
                }
                $d1=strtotime($Date_1);
                $d2=strtotime($Date_2);
                $day_diff=round(($d1-$d2)/3600/24);
                //避免当天购买就可以领
                if (!empty($day_diff)) {
                    $invest['is_receive'] = $day_diff > 0 ? 1 : 0;
                }else{
                    $invest['is_receive'] = 0;
                }

                //不是购买同一天  并且 时差超过12小时就可以领取
                $datetime = date('Y-m-d H:i:s');
                //当前用户时区时间
                $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
                //用户最后领取时间
                $datetime2=date("Y-m-d H:i:s", strtotime($invest['time2_actual']));

                $d11=strtotime($act_date_time);
                $d21=strtotime($datetime2);   



                //判断时差是否有12小时
                $day_diff2=round(($d11-$d21)/60);
                if(!empty($day_diff2) && ($day_diff2 >=720 || $day_diff2 <-720)){
                    $invest['timenumber'] = $day_diff2;
                }else{
                    $invest['is_receive'] = 0;
                    $invest['timenumber'] = $day_diff2;
                }
                if($day_diff2 <720 && $day_diff2 >=0){
                //下次领取时间  //加上12小时
                $invest['lasttime'] = date('d M Y · H:i', $d21 + 43200); 
                
                //第一次领取 显示第二天0点
                }elseif($day_diff2 <0){
                    $d222=strtotime($Date_2);
                    $invest['lasttime'] =  date('d M Y · H:i', $d222 + 86400);
                }
            }
            if ($invest['status'] == 1) {
                $invest['is_receive'] = 0;
            }
            $invest['not_receive'] = empty($item['not_receive']) ? [] : json_decode($item['not_receive']);
            if(in_array($is_w, $invest['not_receive'])) {
                $invest['is_receive'] = 0;
                $invest['lasttime'] = "Tidak ada pendapatan yang dihitung！";
            }
            
            $invest['title'] = "--";
            if(!empty($item)){
                $invest['title'] = $item["title_$language"];
            }

            $invest['time_actual'] = date('d M Y · H:i', strtotime($invest['time'])-7200);
        }
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    
     /**
     * Describe:基金记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function investjijinRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('LcInvest')->field("id,itemid,money,day,rate,total_interest,wait_interest,type,status,currency,time_zone,time_actual,time2_actual,time,time2,total_num,wait_num,pause_time,source")->where("uid = $uid AND type =5")->order("time_actual desc")->page($page,$listRows)->select();
        $length = Db::name('LcInvest')->where("uid = $uid AND type =5")->count();
        $w = date("w");//获取星期几;
        $is_w = 0;
        if (in_array($w, [1,2,3,4,5])) {
            $is_w = 1;
        } else if($w == 6) {
            $is_w = 2;
        } else if($w == 0) {
            $is_w = 3;
        }
        $time = date('Y-m-d');
        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }

        $time_zone = $currency['time_zone'];
        $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d');
        
        foreach ($list as &$invest) {
            $Date_2=date("Y-m-d", strtotime($invest['time2_actual']));
            $invest['is_receive'] = $act_time >= $Date_2 ? 1 : 0;
            $item = Db::name('LcItem')->find($invest['itemid']);
            $invest['img'] = $item['img2'];
            // 判断是否有领取
            // if ($invest['type'] == 1 || $invest['type'] == 4) {
            //     $Date_1=date("Y-m-d");
            //     $Date_2=date("Y-m-d", strtotime($invest['time_actual']));
            //     $d1=strtotime($Date_1);
            //     $d2=strtotime($Date_2);
            //     $day_diff=round(($d1-$d2)/3600/24);
            //     if (!empty($day_diff)) {
            //         $wait_day = $day_diff - ($invest['total_num'] - $invest['wait_num']);
            //         $invest['is_receive'] = $wait_day > 0 ? 1 : 0;
            //     }
            // }
            if ($invest['status'] == 1) {
                $invest['is_receive'] = 0;
            }
            // $invest['not_receive'] = empty($item['not_receive']) ? [] : json_decode($item['not_receive']);
            // if(in_array($is_w, $invest['not_receive'])) {
            //     $invest['is_receive'] = 0;
            // }
            $invest['title'] = "--";
            if(!empty($item)){
                $invest['title'] = $item["title_$language"];
            }
            $invest['time_actual'] = date('d M Y · H:i', strtotime($invest['time_actual']));
            $invest['last_time'] = date('d M Y · H:i', strtotime($invest['time2_actual']));
        }
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    /**
     * Describe:获取转盘抽奖奖品列表
     * DateTime: 2022/8/27
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function prizeList()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        //判断认证状态
        // if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        $set = Cache::store('redis')->hget('draw','content');
        if(empty($set)){
            $set = Db::name('LcDraw')->field("content_en_us as content")->find(1);
            Cache::store('redis')->hset('draw','content',json_encode($set));
        }else{
            $set  =json_decode($set,true);
        }
        $prizeList  = Cache::store('redis')->hget('draw','prize');
        if(empty($prizeList )){
            $prizeList = Db::name('LcDrawPrize')->field("id,title_en_us as title,img,type")->where("status!=1")->order('sort asc,id asc')->select();
            Cache::store('redis')->hset('draw','prize',json_encode($prizeList));
        }else{
            $prizeList  =json_decode($prizeList,true);
        }
        // $drawRecord = Db::name('LcDrawRecord dr,lcDrawPrize dp,lcUser u')->field("dp.title_en_us as title,u.username")->where('dr.pid = dp.id AND dr.uid = u.id AND dp.type!=3')->order('dr.act_time desc')->limit(8)->select();
       
        // foreach ($drawRecord as &$dr) {
        //     $dr['username'] = dataDesensitization($dr['username'], 2, 4);
        // }
        $data = array(
            'prizeList' => $prizeList,
            // 'drawRecord' => $drawRecord,
            'set' => $set,
            'drawNum' => $user['draw_num'],
            'point_total' => $user['point'],
            // 'point' => $set['point'],
        );
        $this->success("success", $data);
    }
    /**
     * Describe:抽奖
     * DateTime: 2022/8/29 21:59
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function draw()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        // $set  = Db::name('LcDraw')->find(1);
        
        
        
        // if($user['point'] - $set['point'] < 0) $this->error('utils.parameterError',"",218);
        if($user['draw_num'] < 1) $this->error('utils.parameterError',"",218);
        //时区转换
        $time = date('Y-m-d H:i:s');
        $currency = Cache::store('redis')->hget("recharge_method","currency");
        if(empty($currency)){

            $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
            Cache::store('redis')->hset("recharge_method","currency",json_encode($currency));
        }else{
            $currency =  json_decode($currency,true);
        }

        $time_zone = $currency['time_zone'];
        $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        
        //年月日
        $time2 = date('Y-m-d');
        // $act_time2 = dateTimeChangeByZone($time2, 'Asia/Shanghai', $time_zone, 'Y-m-d');

        //限制时间
        $timenumber =strtotime($act_time);
        $start = strtotime(date('Y-m-d',$timenumber).'10:00:00');
        $end = strtotime(date('Y-m-d',$timenumber).'22:00:00');
        if(($timenumber < $start) || ($timenumber > $end)){
            $this->error('ইভেন্টটি সময়-সীমিত, তাই দয়া করে বরাদ্দকৃত সময়ের মধ্যে এটি ব্যবহার করুন',"",218);
        }
        
        // 是否存在必中
        $draw_appoint  = Db::table('lc_draw_appoint')->where('uid', $uid)->whereNull('use_time')->find();
        if ($draw_appoint) {
            $draw = Db::name('LcDrawPrize')->where('id', $draw_appoint['draw_prize_id'])->field("id,title_en_us as title,img,type,probability,money,item_id")->find();
            Db::table('lc_draw_appoint')->where('id', $draw_appoint['id'])->update(['time_zone' => $time_zone, 'act_use_time' => $act_time, 'use_time' => $time]);
        }else {
            $prizeList = '';
            //查询今天是否有中实物的记录
            $recodeList  = Db::table('lc_draw_record')->where("uid={$uid} and dtype = 1")->column('pid');
            // print_r($recodeList);
            $ids[] = $recodeList;
            if($recodeList ){//有中实物的记录 则不让中实物了
                $prizeList  = Db::name('LcDrawPrize')->field("id,title_en_us as title,img,type,probability,money,item_id")->where("status!=1 and id not in (".implode(',', $recodeList).")")->order('sort asc,id desc')->select();
            }else{
                $prizeList  = Cache::store('redis')->hget('draw','prizelist');
                if(empty($prizeList )){
                    $prizeList  = Db::name('LcDrawPrize')->field("id,title_en_us as title,img,type,probability,money,item_id")->where("status!=1")->order('sort asc,id desc')->select();
                    Cache::store('redis')->hset('draw','prizelist',json_encode($prizeList));
                }else{
                    $prizeList = json_decode($prizeList,true);
                }
            }
            //概率算法
            $list = [];
            foreach($prizeList as $k2=>$v2) {
                $list[$k2] = $v2['probability'];
            }
            $draw = $prizeList[get_rand($list)];
        }
        
        $add = array(
            'uid' => $uid,
            'pid' => $draw['id'],
            'dtype' => $draw['type'],
            'time_zone' => $time_zone,
            'time' => $time,
            'act_time' => $act_time,
        );
        $did = Db::name('LcDrawRecord')->insertGetId($add);
        if(empty($did)) $this->error('utils.networkException',"",218);
        //抽奖次数减少
        // $point_now = $user['point'] - $set['point'];
        // Db::name('LcUser')->where(['id' => $uid])->update(['point' => $point_now]);
        Db::name('LcUser')->where(['id' => $uid])->setDec('draw_num');
        
        //现金则添加到账户余额
        if($draw['type']==2){
            //流水添加
            addFunding($uid,$draw['money'],0,1,14,$language);
            //添加余额
            setNumber('LcUser', 'withdrawable', $draw['money'], 1, "id = $uid");
            //添加冻结金额
            if(getInfo('recharge_need_flow')){
                setNumber('LcUser', 'frozen_money', $draw['money'], 1, "id = $uid");
            }
        }

        if ($draw['type'] == 4) {
            //时区转换
            $item = Db::name('LcItem')->find($draw['item_id']);
            $money_usd = $item['min'];
            $time = date('Y-m-d H:i:s');
            // $time_zone = getTimezoneByLanguage($language);
            $time_actual = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            // $currency = getCurrencyByLanguage($language);
        
            $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $item['day'] . ' day'));
            $total_interest = $money_usd * $item['rate'] / 100;
            $total_num = 1;
            $time2_actual = dateTimeChangeByZone($time2, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            $orderNo = 'ST' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
            //到期还本付息（时）
            if($item['type']==3){
                //按时
                $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $item['day'] . ' hour'));
            }
            //每日付息到期还本
            elseif($item['type']==1 || $item['type']==4){
                //日利率
                $total_interest = intval($money_usd * $item['rate']/ 100) * $item['day'] ;
                //返息期数
                $total_num = $item['day'];
            }
            //定投
            elseif($item['type']==5){
                //日利率
                // $total_interest = $money_usd * $item['rate'] * $item['day'] / 100;
                //返息期数
                $total_num = $item['day'];
            }
            //8小时一次次数产品
            elseif($item['type']==6){
                //日利率
                $total_interest = intval($money_usd * $item['rate']/ 100) * $item['day'] ;
                //返息期数
                $total_num = $item['day'];
            }
            //6小时一次次数产品
            elseif($item['type']==7){
                //日利率
                $total_interest = intval($money_usd * $item['rate']/ 100) * $item['day'] ;
                //返息期数
                $total_num = $item['day'];
            }//机器定投
            elseif($item['type']==8){
                //日利率
                // $total_interest = $money_usd * $item['rate'] * $item['day'] / 100;
                //返息期数
                $total_num = $item['day'];
            }elseif($item['type']==12){
                //日利率
                $total_interest = intval($item['min'] * $item['rate']/ 100) * $item['day'] ;
                //返息期数
                $total_num = $item['day'];
            }
            //添加投资记录
            $insert = array(
                "uid" =>$uid,
                "itemid" =>$item['id'],
                "orderNo" =>$orderNo,
                "money" =>$item['min'],
                "money2" =>$money_usd,
                "total_interest" =>$total_interest,
                "wait_interest" =>$total_interest,
                "total_num" =>$total_num,
                "wait_num" =>$total_num,
                "day" =>$item['day'],
                "rate" =>$item['rate'],
                "type" =>$item['type'],
                "is_draw" => 1,
                "source" => 2,
                "not_receive" =>$item['not_receive'],
                "is_distribution" =>$item['is_distribution'],
                "currency" =>$currency['symbol'],
                "time_zone" =>$time_zone,
                "time" =>$time,
                "time_actual" =>$time_actual,
                "time2" =>$time2,
                "time2_actual" =>$time2_actual,
            );
            
            Db::name('LcInvest')->insertGetId($insert);
        }
        
        $data = array(
            'draw' => $draw,
        );
        $this->success("success", $data);
    }
    /**
     * Describe:抽奖记录
     * DateTime: 2022/8/29 21:59
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function drawRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        // $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('lc_draw_record dr,lc_draw_prize dp')->field("date_format(dr.time, '%d %M %Y · %H:%i') as act_time,dp.title_en_us as title,dp.img,dp.type,dp.money")->where("dr.pid = dp.id")->where("uid = $uid")->order("dr.act_time desc")->page($page,$listRows)->select();
        $length = Db::name('lc_draw_record dr,lc_draw_prize dp')->where("dr.pid = dp.id")->where("uid = $uid")->order("dr.act_time desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    
    
    /**
     * @description：储蓄金详情
     * @date: 2023/2/4
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsDetail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        $savings = Db::name('LcSavings')->field("content_$language as content")->find(1);
        
        $income =  Db::name('LcUserFunding')->where("uid = '{$uid}' AND fund_type = 18")->sum('money');
        
        $data = array(
            'savings' => $savings,
            'income' => $income,
            // 'flexible'=>changeMoneyByLanguage($user['savings_flexible'],$language),
            'flexible_usd'=>$user['savings_flexible'],
            // 'fixed'=>changeMoneyByLanguage($user['savings_fixed'],$language),
            // 'fixed_usd'=>$user['savings_fixed']
        );
        $this->success("success", $data);
    }
    /**
     * Describe:申购详情
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsSubscribeDetail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        //判断参数
        if(empty($params['type'])) $this->error('utils.parameterError',"",218);
        if($params['type']!=1&&$params['type']!=2) $this->error('utils.parameterError',"",218);
        
        $savings = Db::name('LcSavings')->find(1);
        $data = array();
        
        //时区转换，按当前用户时区统计
        $time_zone = getTimezoneByLanguage($language);
        
        $date_now = date('Y-m-d H:i:s');
        $now = dateTimeChangeByZone($date_now, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
        
        //活期
        if($params['type']==1){
            $data = array(
                "rate" =>$savings['flexible_rate'],
                "max_rate" =>$savings['flexible_rate'],
                "inc_rate" =>0,
                "min" =>$savings['flexible_min'],
                "day" =>$savings['flexible_min_day'],
                "userBalance" =>$user['money'],
                "time1" =>date("Y-m-d H:i",strtotime(dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s'))),
                "time2" =>date("Y-m-d H:i",strtotime(dateTimeChangeByZone(date('Y-m-d 23:30:00'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s')."+1 day"))
            );
        }
        //定期
        else{
            $data = array(
                "rate" =>$savings['fixed_rate'],
                "max_rate" =>$savings['fixed_rate_max'],
                "inc_rate" =>$savings['fixed_inc_rate'],
                "min" =>$savings['fixed_min'],
                "day" =>$savings['fixed_min_day'],
                "userBalance" =>$user['money'],
                "time1" =>date("Y-m-d H:i",strtotime(dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s'))),
                "time2" =>date("Y-m-d H:i",strtotime(dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s')."+1 day"))
            );
        }
        
        $this->success("success", $data);
    }
    /**
     * Describe:发起申购
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsSubscribe()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        
        //判断认证状态
        if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //判断参数
        if(empty($params['type'])||empty($params['money'])) $this->error('utils.parameterError',"",218);
        if($params['type']!=1&&$params['type']!=2) $this->error('utils.parameterError',"",218);
        
        $savings = Db::name('LcSavings')->find(1);
        
        //金额转换
        $money_usd = $params['money'];
        
        //判断余额>最低金额
        $act_user_money = $user['money'];
        if($act_user_money<$params['money']) $this->error('utils.parameterError',"",218);
        
        
        $day = 0;
        $wait_day = 0;
        $rate = 0;
        $status = -1;
        
        //时区转换
        $time = date('Y-m-d H:i:s');
        $time_zone = getTimezoneByLanguage($language);
        $time_actual = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        $currency = getCurrencyByLanguage($language);
        
        $time2 = "0000-00-00 00:00:00";
        $time2_actual = "0000-00-00 00:00:00";
        
        //活期
        if($params['type']==1){
            //判断申购金额
            if ($money_usd - $savings['flexible_min'] < 0) $this->error('utils.parameterError',"",218);
            
            $rate = $savings['flexible_rate'];
        }
        //定期
        else{
            //判断申购金额
            if ($money_usd - $savings['fixed_min'] < 0) $this->error('utils.parameterError',"",218);
            //判断申购天数
            if(empty($params['days'])) $this->error('utils.parameterError',"",218);
            if ($params['days'] - $savings['fixed_min_day'] < 0) $this->error('utils.parameterError',"",218);
            
            $day = $params['days'];
            $status = 0;
            $rate = $savings['fixed_rate'];
            
            $rate = $rate + ($day - $savings['fixed_min_day'])*$savings['fixed_inc_rate'];
            
            if($rate<$savings['fixed_rate']) $rate = $savings['fixed_rate'];
            if($rate>$savings['fixed_rate_max']) $rate = $savings['fixed_rate_max'];
            
            $time2 = date('Y-m-d H:i:s', strtotime($time.'+' . $day . ' day'));
            $time2_actual = dateTimeChangeByZone($time2, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            $wait_day = $day;
        }
        
        $orderNo = 'SU' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        
        //添加申购记录
        $insert = array(
            "uid" =>$uid,
            "orderNo" =>$orderNo,
            "money" =>$money_usd,
            "money2" =>$params['money'],
            "day" =>$day,
            "wait_day" =>$wait_day,
            "rate" =>$rate,
            "status" =>$status,
            "type" =>$params['type'],
            "currency" =>$currency,
            "time_zone" =>$time_zone,
            "time" =>$time,
            "time_actual" =>$time_actual,
            "time2" =>$time2,
            "time2_actual" =>$time2_actual,
        );
        
        Db::startTrans();
        $iid = Db::name('LcSavingsSubscribe')->insertGetId($insert);
        if(!empty($iid)){
            //流水添加
            addFunding($uid,$money_usd,changeMoneyByLanguage($params['money'],$language),2,16,$language);
            //余额扣除
            setNumber('LcUser', 'money', $money_usd, 2, "id = $uid");
            //活期/定期余额添加
            if($params['type']==1){
                setNumber('LcUser', 'savings_flexible', $money_usd, 1, "id = $uid");
            }else{
                setNumber('LcUser', 'savings_fixed', $money_usd, 1, "id = $uid");
            }
            //冻结金额扣除
            if($user['frozen_money']>$money_usd){
                setNumber('LcUser', 'frozen_money', $money_usd, 2, "id = $uid");
            }else{
                setNumber('LcUser', 'frozen_money', $user['frozen_money'], 2, "id = $uid");
            }
            Db::commit();
            $this->success("success");
        }else{
            Db::rollback();
        }
        $this->error("error");
    }
    /**
     * Describe:申购记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsSubscribeRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('lc_savings_subscribe')->field("money,money2,currency,day,rate,type,status,time_actual as act_time")->where("uid = $uid")->order("time_actual desc")->page($page,$listRows)->select();
        $length = Db::name('lc_savings_subscribe')->field("money,money2,currency,day,rate,type,status,time_actual as act_time")->where("uid = $uid")->order("time_actual desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }
    
    /**
     * Describe:赎回详情
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsRedeemDetail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        
        $savings = Db::name('LcSavings')->find(1);
        $flexible_min_day = $savings['flexible_min_day'];
        
        //时区转换，按当前用户时区统计
        $time_zone = getTimezoneByLanguage($language);
        $now = dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
        
        $start = dateTimeChangeByZone(date('Y-m-d H:i:s',strtotime("-$flexible_min_day day")), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        
        $flexible_no_sum = Db::name('lc_savings_subscribe')->where("time_actual BETWEEN '$start' AND '$now' AND uid = $uid AND type = 1")->sum('money');
        
        //可赎回金额 = 用户活期余额-活期持有未到期金额
        $money_usd = $user['savings_flexible'] - $flexible_no_sum;
        
        $data = array(
            "balance" =>$money_usd,
            "days" =>$savings['flexible_min_day'],
            "time" =>date("Y-m-d H:i",strtotime(dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s'))),
            );
        
        $this->success("success", $data);
    }
    /**
     * Describe:发起赎回
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsRedeem()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        
        //判断认证状态
        if(getUserNeedAuth($uid)) $this->error('auth.authFirst',"",405);
        
        //判断参数
        if(empty($params['money'])) $this->error('utils.parameterError',"",218);
        
        $savings = Db::name('LcSavings')->find(1);
        $flexible_min_day = $savings['flexible_min_day'];
        
        //金额转换
        $money_usd = $params['money'];
        
        //判断可赎回金额>赎回金额
        //时区转换，按当前用户时区统计
        $time_zone = getTimezoneByLanguage($language);
        $now = dateTimeChangeByZone(date('Y-m-d H:i:s'), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
        $start = dateTimeChangeByZone(date('Y-m-d H:i:s',strtotime("-$flexible_min_day day")), 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');//当前用户时区 现在
        $flexible_no_sum = Db::name('lc_savings_subscribe')->where("time_actual BETWEEN '$start' AND '$now' AND uid = $uid AND type = 1")->sum('money');
        //可赎回金额 = 用户定期余额-活期持有未到期金额
        $flexible_usd = $user['savings_flexible'] - $flexible_no_sum;
        
        $used_money = $flexible_usd;
        if($used_money<$params['money']) $this->error('utils.parameterError',"",218);
        
        //时区转换
        $time = date('Y-m-d H:i:s');
        $time_zone = getTimezoneByLanguage($language);
        $time_actual = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
        $currency = getCurrencyByLanguage($language);
        
        $orderNo = 'RE' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        
        //添加赎回记录
        $insert = array(
            "uid" =>$uid,
            "orderNo" =>$orderNo,
            "money" =>$money_usd,
            "money2" =>$params['money'],
            "type" =>1,
            "currency" =>$currency,
            "time_zone" =>$time_zone,
            "time" =>$time,
            "time_actual" =>$time_actual,
        );
        
        Db::startTrans();
        $iid = Db::name('LcSavingsRedeem')->insertGetId($insert);
        if(!empty($iid)){
            //流水添加
            addFunding($uid,$money_usd,changeMoneyByLanguage($params['money'],$language),1,17,$language);
            //余额添加
            setNumber('LcUser', 'money', $money_usd, 1, "id = $uid");
            //活期余额扣除
            setNumber('LcUser', 'savings_flexible', $money_usd, 2, "id = $uid");
            Db::commit();
            $this->success("success");
        }else{
            Db::rollback();
        }
        $this->error("error");
    }
    /**
     * Describe:赎回记录
     * DateTime: 
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function savingsRedeemRecord()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        
        $page = $params["page"];
        $listRows = $params["listRows"];
        
        $list = Db::name('lc_savings_redeem')->field("money,money2,currency,type,time_actual as act_time")->where("uid = $uid")->order("time_actual desc")->page($page,$listRows)->select();
        $length = Db::name('lc_savings_redeem')->field("money,money2,currency,type,time_actual as act_time")->where("uid = $uid")->order("time_actual desc")->count();
        
        $data = array(
            'list' => $list,
            'length' => $length
        );
        $this->success("success", $data);
    }

    // 领取利息
    public function invest_settle()
    {
        $noInvest = true;
        $params = $this->request->param();
        $id = $params['invest_id'];
        $language = $params["language"];
        $this->checkToken($language);
        $uid = $this->userInfo['id'];
        $type = $params['type'];

        // 设置锁
        $cache_key = "invest_settle_{$uid}_$id";
        // Cache::store('redis')->rm($cache_key); 
        // if (Cache::store('redis')->get($cache_key)) {
            
        //     $this->success('success',['success']);
        // }
        $boolg =Cache::store('redis')->rawCommand('set',$cache_key, '1',"EX",10,"NX");
        if(!$boolg){
            $this->success('success',['success']);
        }
        // Cache::store('redis')->set($cache_key, time(),1800);
        // // 任务结束触发
        // register_shutdown_function(function () use ($cache_key) {
        //     Cache::store('redis')->rm($cache_key); 
        // });
        
        $now = date('Y-m-d H:i:s');
        $invest_list1 = [];
        $invest_list2 = [];
        $invest_list3 = [];
        $invest_list4 = [];
        $savings_list1 = [];
        $invest_list5 = [];
        $invest_list6 = [];
        switch ($type) {
            case 1:
                //每日付息到期还本
                $invest_list1 = Db::name("LcInvest")->where(['id' => $id,'type' => 1,'status' => 0])->select();
                break;
            case 2:
                //到期还本付息
                $invest_list2 = Db::name("LcInvest")->where([
                   'id' => $id,
                   'time2' => ['<=', $now],
                   'status' => 0
               ])->where(function($query) {
                   $query->where('type', 2)->whereOr('type', 3);
               })->select();
               
                break;
            case 3:
                //储蓄金定期
                $savings_list1 = Db::name("LcSavingsSubscribe")->where([
                   'id' => $id,
                   'type' => 2,
                   'status' => 0
               ])->select();
               
                break;
            case 4:
                //按日反息 到期不反本（日）
                $invest_list3 = Db::name("LcInvest")->where([
                   'id' => $id,
                   'type' => 4,
                   'status' => 0
               ])->select();
               
                break;
            case 5:
                //定投）
                $invest_list4 = Db::name("LcInvest")->where([
                   'id' => $id,
                   'type' => 5,
                   'status' => 0
               ])->select();
               
                break;    
            case 6:
                //按日反息 到期不反本（日）8小时领一次 type = 6    
                $invest_list5 = Db::name("LcInvest")->where(['id' => $id,'type' => 6,'status' => 0])->select();
                break; 
            case 7:
                //按日反息 到期不反本（日）8小时领一次
                $invest_list6 = Db::name("LcInvest")->where(['id' => $id,'type' => 7,'status' => 0])->select();
                break;     
            case 8:
                //机器定投）
                $invest_list4 = Db::name("LcInvest")->where(['id' => $id,'type' => 8,'status' => 0])->select();
                break; 
           case 9:
                //税务机器
                $invest_list3 = Db::name("LcInvest")->where(['id' => $id,'type' => 9,'status' => 0])->select();
                break;    
                
           case 12:
               //12小时领一次  ||  type=12  12小时领一次 
               $invest_list5 = Db::name("LcInvest")->where(['id' => $id,'type' => 12,'status' => 0])->select();
               break;      
        }
        if (empty($invest_list1)&&empty($invest_list2)&&empty($invest_list3)&&empty($savings_list1)&&empty($invest_list4)&&empty($invest_list5)&&empty($invest_list6))  $this->error('error',"", 218);
        
        //每日付息到期还本处理
        foreach ($invest_list1 as $k => $v) {
            // 判断是否隔天没有领取
            $wait_day = 0;
            $Date_1=date("Y-m-d");
            $Date_2=date("Y-m-d", strtotime($v['time_actual']));
            $d1=strtotime($Date_1);
            $d2=strtotime($Date_2);
            $day_diff=round(($d1-$d2)/3600/24);
            if (!empty($day_diff)) {
                $wait_day = $day_diff - ($v['total_num'] - $v['wait_num']);
            }
            
            //判断返还时间
            $return_num = $v['wait_num'] - 1;
            $return_time = date('Y-m-d', (strtotime($v['time2_actual'].'-' . $return_num . ' day') + (3600*24*($wait_day -1))));
            if($return_time > $now && empty(($wait_day))) continue;
            
            $time_zone = $v['time_zone'];
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            //每日利息=总利息/总期数
            $day_interest = $v['total_interest']/$v['total_num'];

            // 添加返利
            if ($v['is_distribution']) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->where("ur.uid = {$v['uid']}")->order('ur.level asc')->order('ur.level asc')->limit(3)->select();
                foreach($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if(empty($ProductNumber)){
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
                    $interest_rate = floor($day_interest*$level) / 100;
                    $interest_rate = intval($interest_rate);
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'],$interest_rate,0,1,19,$language);
                }
            }
            
            
            //最后一期
            if($v['wait_num']==1){
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1,'wait_num' => 0,'wait_interest' => 0]);
                if($v['is_draw'] == 0) {
                    //返还本金
                    addFunding($v['uid'],$v['money2'],0,1,15,$language);
                    setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");
                }
                
            }else{
                $time2 = date('Y-m-d H:i:s', strtotime($v['time2_actual'].'+' . ($wait_day -1) . ' day'));
                $time = date('Y-m-d H:i:s', strtotime($v['time_actual'].'+' . ($wait_day -1) . ' day'));
                Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num']-1,'wait_interest' => $v['wait_interest']-$day_interest, 'time_actual' => $time, 'time2' => $time2, 'time2_actual' => $time2]);
            }
            
            //利息
            addFunding($v['uid'],$day_interest,0,1,6,$language, 2);
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");
            
            //添加收益
            setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");
            
            $noInvest = false;
        }
        //到期还本付息处理
        foreach ($invest_list2 as $k => $v) {
            Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1,'wait_num' => 0,'wait_interest' => 0]);
            
            $time_zone = $v['time_zone'];
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            $total_interest = $v['total_interest'];

            // 添加返利
            if ($v['is_distribution']) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->where("ur.uid = {$v['uid']}")->order('ur.level asc')->limit(3)->select();
                foreach($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if(empty($ProductNumber)){
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
                    $interest_rate = floor($day_interest*$level) / 100;
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'],$interest_rate,0,1,19,$language);
                }
            }
            $interest_rate = intval($total_interest);
            //利息
            addFunding($v['uid'],$total_interest,0,1,6,$language, 2);
            setNumber('LcUser', 'withdrawable', $total_interest, 1, "id = {$v['uid']}");
            
            //本金
            if($v['is_draw'] != 1) {
                addFunding($v['uid'],$v['money2'],0,1,15,$language);
                setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");
            }
            
            
            //添加收益
            setNumber('LcUser', 'income', $total_interest, 1, "id = {$v['uid']}");
            
            $noInvest = false;
        }
        //按日反息 到期不反本（日）
        foreach ($invest_list3 as $k => $v) {
            // 判断是否隔天没有领取
            $wait_day = 0;
            $time = date('Y-m-d');
            $time_zone = $v['time_zone'];
            $Date_1 = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d');
            $Date_2=date("Y-m-d", strtotime($v['time_actual']));
            $d1=strtotime($Date_1);
            $d2=strtotime($Date_2);
            $day_diff=round(($d1-$d2)/3600/24);
            if (!empty($day_diff)) {
                $wait_day = $day_diff - ($v['total_num'] - $v['wait_num']);
            }
            //判断返还时间
            $return_num = $v['wait_num'] - 1;
            $return_time = date('Y-m-d', (strtotime($v['time2_actual'].'-' . $return_num . ' day') + (3600*24*($wait_day-1))));
            // if($return_time > $now) continue;
            if ($wait_day < 1) continue;

            
           //  $time_zone = $v['time_zone'];
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            //每日利息=总利息/总期数
            $day_interest = $v['total_interest']/$v['total_num'];
            $day_interest = intval($day_interest);
            // 添加返利
            if ($v['is_distribution']) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->where("ur.uid = {$v['uid']}")->order('ur.level asc')->limit(3)->select();
                foreach($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if(empty($ProductNumber)){
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
                    $interest_rate = floor($day_interest*$level) / 100;
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'],$interest_rate,0,1,19,$language);
                }
            }
            
            //最后一期
            if($v['wait_num']==1){
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1,'wait_num' => 0,'wait_interest' => 0]);
                // //返还本金
                // addFunding($v['uid'],$v['money2'],changeMoneyByLanguage($v['money2'],$language),1,15,$language);
                // setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");
                
            }else{
                $time2 = date('Y-m-d H:i:s', strtotime($v['time2_actual'].'+' . ($wait_day -1) . ' day'));
                $time = date('Y-m-d H:i:s', strtotime($v['time_actual'].'+' . ($wait_day -1 ) . ' day'));
                Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num']-1,'wait_interest' => $v['wait_interest']-$day_interest, 'time_actual' => $time, 'time2' => $time2, 'time2_actual' => $time2]);
            }
            
            //利息
            addFunding($v['uid'],$day_interest,0,1,6,$language, 2);
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");
            
            //添加收益
            setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");
            
            $noInvest = false;
        }
            //储蓄金定期收益处理
        foreach ($savings_list1 as $k => $v) {
            //判断返还时间
            $return_num = $v['wait_day'] - 1;
            $return_time = date('Y-m-d H:i:s', strtotime($v['time2'].'-' . $return_num . ' day'));
            if($return_time > $now) continue;
            
            $time_zone = $v['time_zone'];
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            //每日利息=申购金额*利率
            $day_interest = $v['money']*$v['rate']/100;
            
            //最后一期
            if($v['wait_day']==1){
                Db::name('LcSavingsSubscribe')->where('id', $v['id'])->update(['status' => 1,'wait_day' => 0]);
                //添加赎回记录
                $orderNo = 'RE' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
                $insert = array(
                    "uid" =>$v['uid'],
                    "orderNo" =>$orderNo,
                    "money" =>$money,
                    "money2" =>$v['money2'],
                    "type" =>2,
                    "currency" =>$v['currency'],
                    "time_zone" =>$v['time_zone'],
                    "time" =>$now,
                    "time_actual" =>$time_actual = dateTimeChangeByZone($now, 'Asia/Shanghai', $v['time_zone'], 'Y-m-d H:i:s'),
                );
                Db::name('LcSavingsRedeem')->insertGetId($insert);
                
                //自动赎回
                addFunding($v['uid'],$money,0,1,17,$language);
                setNumber('LcUser', 'savings_fixed', $money, 2, "id = {$v['uid']}");
                setNumber('LcUser', 'money', $money, 1, "id = {$v['uid']}");
                
            }else{
                Db::name('LcSavingsSubscribe')->where('id', $v['id'])->update(['wait_day' => $v['wait_day']-1]);
            }
            
            //利息流水
            addFunding($v['uid'],$day_interest,0,1,18,$language, 2);
            //利息
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");
            
            
            $noInvest = false;
        }
        
          //定投收益处理
        foreach ($invest_list4 as $k => $v) {
            // 判断是否隔天没有领取
            
            $time = date('Y-m-d');
            $time_zone = $v['time_zone'];
            $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d');
        
            $Date_2=date("Y-m-d", strtotime($v['time2_actual']));
            
            // $invest['is_receive'] = $act_time >= $Date_2 ? 1 : 0;
            if($act_time < $Date_2){
                 continue;
            }
            
            $inteam=Db::name("LcInvest")->where("id = $id")->find();
            if($inteam['wait_interest']==0){
                continue;
            }
            // $wait_day = 0;
            // $Date_1=date("Y-m-d");
            // $datt=date("Y-m-d HH:mm:ss");
            // $Date_2=date("Y-m-d", strtotime($v['time_actual']));
            // $d1=strtotime($Date_1);
            // $d2=strtotime($Date_2);
            // $day_diff=round(($d1-$d2)/3600/24);
            // if (!empty($day_diff)) {
            //     $wait_day = $day_diff - ($v['total_num'] - $v['wait_num']);
            // }
            // // //判断返还时间
            // // $return_num = $v['wait_num'] - 1;
            // // $return_time = date('Y-m-d', (strtotime($v['time2_actual'].'-' . $return_num . ' day') + (3600*24*($wait_day-1))));
            // // if($return_time > $now) continue;
            // // if ($wait_day < 1) continue;

            // if($datt < $v['time2']){
            //     continue;
            // }

            
            $time_zone = $v['time_zone'];
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            //每日利息
            $day_interest = $v['total_interest'];
           $day_interest = intval($day_interest);
            // 添加返利
            if ($v['is_distribution']) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->where("ur.uid = {$v['uid']}")->order('ur.level asc')->limit(3)->select();
                foreach($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if(empty($ProductNumber)){
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
                    $interest_rate = floor($day_interest*$level) / 100;
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'],$interest_rate,0,1,19,$language);
                }
            }
            
            //最后一期
            // if($v['wait_num']==1){
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1,'wait_num' => 0,'wait_interest' => 0]);
                // //返还本金
                // addFunding($v['uid'],$v['money2'],changeMoneyByLanguage($v['money2'],$language),1,15,$language);
                // setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");
                
            // }else{
            //     $time2 = date('Y-m-d H:i:s', strtotime($v['time2_actual'].'+' . ($wait_day -1) . ' day'));
            //     $time = date('Y-m-d H:i:s', strtotime($v['time_actual'].'+' . ($wait_day -1 ) . ' day'));
            //     Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num']-1,'wait_interest' => $v['wait_interest']-$day_interest, 'time_actual' => $time, 'time2' => $time2, 'time2_actual' => $time2]);
            // }
            
            //利息
            addFunding($v['uid'],$day_interest,0,1,6,$language, 2);
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");
            
            //添加收益
            setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");
            
            $noInvest = false;
        }
        
        
        //次数产品 8小时  和  12小时
        foreach ($invest_list5 as $k => $v) {
            // 判断是否隔天 没有领取
            $wait_day = 0;
            $time_zone = $v['time_zone'];
            $act_time1=date("Y-m-d");
            $Date_1 = dateTimeChangeByZone($act_time1, 'Asia/Shanghai', $time_zone, 'Y-m-d');
            
            $Date_2=date("Y-m-d", strtotime($v['time_actual']));
            $d1=strtotime($Date_1);
            $d2=strtotime($Date_2);
            $day_diff=round(($d1-$d2)/3600/24);
            if (!empty($day_diff) && $day_diff >0) {
               
            }else{
                continue;
            }
            //判断返还时间
            // $return_num = $v['wait_num'] - 1;
            // $return_time = date('Y-m-d', (strtotime($v['time2_actual'].'-' . $return_num . ' day') + (3600*24*($wait_day-1))));
            // if($return_time > $now) continue;
            //不是购买同一天  并且 时差超过8小时就可以领取
            $datetime = date('Y-m-d H:i:s');
            //当前用户时区时间
            $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            //用户最后领取时间
            $datetime2=date("Y-m-d H:i:s", strtotime($v['time2_actual']));
            
            $d11=strtotime($act_date_time);
            $d21=strtotime($datetime2);   
            $num = 480;
            if($type == 12){ //如果是12小时机器
               $num = 720;
            }elseif($type == 6){//8小时领一次
               $num = 480;
            }
            
            //判断时差是否有8小时
            $day_diff2=round(($d11-$d21)/60);
            if(!empty($day_diff2) && ($day_diff2 >=$num || $day_diff2 <-$num)){
                
            }else{
                continue;
            }
            if ($v['wait_num'] < 1) continue;


            
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            //每日利息=总利息/总期数
            $day_interest = $v['total_interest']/$v['total_num'];
            $day_interest = intval($day_interest);
            // 添加返利
            if ($v['is_distribution']) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->where("ur.uid = {$v['uid']}")->order('ur.level asc')->limit(3)->select();
                foreach($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if(empty($ProductNumber)){
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
                    $interest_rate = floor($day_interest*$level) / 100;
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'],$interest_rate,0,1,19,$language);
                }
            }
            
            //最后一期
            if($v['wait_num']==1){
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1,'wait_num' => 0,'wait_interest' => 0]);
                // //返还本金
                // addFunding($v['uid'],$v['money2'],changeMoneyByLanguage($v['money2'],$language),1,15,$language);
                // setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");
                
            }else{
   
   
                Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num']-1,'wait_interest' => $v['wait_interest']-$day_interest,   'time2_actual' => $act_date_time]);
            }
            
            //利息
            addFunding($v['uid'],$day_interest,0,1,6,$language, 2);
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");
            
            //添加收益
            setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");
            
            $noInvest = false;
        }

          //6小时领一次的次数产品
        foreach ($invest_list6 as $k => $v) {
            // 判断是否隔天 没有领取
            $wait_day = 0;
            $time_zone = $v['time_zone'];
            $act_time1=date("Y-m-d");
            $Date_1 = dateTimeChangeByZone($act_time1, 'Asia/Shanghai', $time_zone, 'Y-m-d');
            
            $Date_2=date("Y-m-d", strtotime($v['time_actual']));
            $d1=strtotime($Date_1);
            $d2=strtotime($Date_2);
            $day_diff=round(($d1-$d2)/3600/24);
            if (!empty($day_diff) && $day_diff >0) {
               
            }else{
                continue;
            }
            //判断返还时间
            // $return_num = $v['wait_num'] - 1;
            // $return_time = date('Y-m-d', (strtotime($v['time2_actual'].'-' . $return_num . ' day') + (3600*24*($wait_day-1))));
            // if($return_time > $now) continue;
            //不是购买同一天  并且 时差超过8小时就可以领取
            $datetime = date('Y-m-d H:i:s');
            //当前用户时区时间
            $act_date_time = dateTimeChangeByZone($datetime, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
            //用户最后领取时间
            $datetime2=date("Y-m-d H:i:s", strtotime($v['time2_actual']));
            
            $d11=strtotime($act_date_time);
            $d21=strtotime($datetime2);   
            
            
            
            //判断时差是否有12小时
            $day_diff2=round(($d11-$d21)/60);
            if(!empty($day_diff2) && ($day_diff2 >=720 || $day_diff2 <-720)){
                
            }else{
                continue;
            }
            if ($v['wait_num'] < 1) continue;

            
            
           //  $language = getLanguageByTimezone($time_zone);
            
            $money = $v['money'];
            //每日利息=总利息/总期数
            $day_interest = $v['total_interest']/$v['total_num'];
           $day_interest = intval($day_interest);
            // 添加返利
            if ($v['is_distribution']) {
                $fusers = Db::name("LcUserRelation")->alias('ur')->join('lc_user u', 'ur.parentid=u.id')->join('lc_user_member um', 'um.id=u.mid')->where("ur.uid = {$v['uid']}")->order('ur.level asc')->limit(3)->select();
                foreach($fusers as $key => $val) {
                    //如果上级没有购买相同的产品类型 则不返利跳过
                    $ProductNumber = Db::name("LcInvest")->where("uid={$val['parentid']} and wait_num >0 and itemid={$v['itemid']}")->select();
                    if(empty($ProductNumber)){
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
                    $interest_rate = floor($day_interest*$level) / 100;
                    // 添加收益
                    setNumber('LcUser', 'withdrawable', $interest_rate, 1, "id = {$val['parentid']}");
                    // 添加总收益
                    setNumber('LcUser', 'income', $interest_rate, 1, "id = {$val['parentid']}");
                    //流水添加
                    addFunding($val['parentid'],$interest_rate,0,1,19,$language);
                }
            }
            
            //最后一期
            if($v['wait_num']==1){
                Db::name('LcInvest')->where('id', $v['id'])->update(['status' => 1,'wait_num' => 0,'wait_interest' => 0]);
                // //返还本金
                // addFunding($v['uid'],$v['money2'],changeMoneyByLanguage($v['money2'],$language),1,15,$language);
                // setNumber('LcUser', 'money', $v['money2'], 1, "id = {$v['uid']}");
                
            }else{
   
   
                Db::name('LcInvest')->where('id', $v['id'])->update(['wait_num' => $v['wait_num']-1,'wait_interest' => $v['wait_interest']-$day_interest,   'time2_actual' => $act_date_time]);
            }
            
            //利息
            addFunding($v['uid'],$day_interest,0,1,6,$language, 2);
            setNumber('LcUser', 'withdrawable', $day_interest, 1, "id = {$v['uid']}");
            
            //添加收益
            setNumber('LcUser', 'income', $day_interest, 1, "id = {$v['uid']}");
            
            $noInvest = false;
        }
        
        if($noInvest){
            $this->error('error');
        }
        $this->success('success',['income' => $day_interest ?? 0]);
    }
  
    //兑换红包金额
   public function red_envelope_redemption () {
       $params = $this->request->param();
       $language = $params["language"];
       $code = $params["code"];
       $this->checkToken($language);
       $uid = $this->userInfo['id'];
       $user = Db::name('LcUser')->find($uid);
       
       // 设置锁
       $cache_key = "red_envelope_{$uid}";
       // Cache::store('redis')->rm($cache_key); 
       // if (Cache::store('redis')->get($cache_key)) {
           
       // }
       // $boolg =Cache::store('redis')->set($cache_key, time(),1000);
       if(Cache::store('redis')->get($cache_key)){
           $this->error('Queuing please try again later.',"", 218);
       }else{
           $boolg =Cache::store('redis')->rawCommand('set',$cache_key, '2',"EX",5,"NX");
           if(!$boolg){
               $this->error('Queuing please try again later.',"", 218);
           }
       }
       // $minScorePacket = Cache::store('redis')->zrangebyscore($code, '-inf', '+inf', ['limit' => [0, 1]]);
       // if (empty($minScorePacket)) {
       //     $this->error('Bonusnya hilang',"",218);
       // }
       // 任务结束触发
       // register_shutdown_function(function () use ($cache_key) {
       //     Cache::store('redis')->rm($cache_key); 
       // });
       
       //时区转换
       $time = date('Y-m-d H:i:s');
       $currency = Cache::store('redis')->hget("withdrawal_method","currency");
       if(empty($currency)){

           $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
           Cache::store('redis')->hset("withdrawal_method","currency",json_encode($currency));
       }else{
           $currency =  json_decode($currency,true);
       }
       $time_zone = $currency['time_zone'];
       $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');

       $red_envelope = Db::table('lc_red_envelope')->where(['code' => $code])->where("f_user_id",$user['system_user_id'])->find();
       if(empty($red_envelope)){
        $red_envelope = Db::table('lc_red_envelope')->where(['code' => $code])->where("f_user_id",10000)->find();
       }
     
       if (empty($red_envelope)){
        $this->error('Jika kode hadiah gagal diambil, silakan hubungi layanan pelanggan.',"",218);
        };
       $red_envelope_record = Db::table('lc_red_envelope_record')
        ->where("pid",$red_envelope['id'])->where('uid',$user['id'])->find();
       
       $f_id = 0;
       if (empty($red_envelope) or $red_envelope_record ) $this->error('You have already received',"",218);
       //管理员账户发送红包 所有人都可以领
        // dump($red_envelope);exit;
       if($red_envelope['f_user_id'] != 10000){
           $f_id = $red_envelope['f_user_id'];
           //群红包
           if ($red_envelope['type'] == 1) {
               //判断是否是归属在代理下面发的
               if($user['system_user_id']!=$red_envelope['f_user_id']){
                   $this->error('Jika kode hadiah gagal diambil, silakan hubungi layanan pelanggan.',"",218);
               }
               
           }else{//单发红包
               //判断是否是归属在代理下面发的
               if($user['system_user_id']!=$red_envelope['f_user_id']){
                   $this->error('Jika kode hadiah gagal diambil, silakan hubungi layanan pelanggan.',"",218);
               }
           }
       }else{
           $f_id = '10000';  //管理员发的全部可以领
       }
       
       //生成存在redis的红包码
    //    $rediscode = $code.'_'.$f_id;
       $rediscode = $code;
    //    dump($rediscode);exit;
       $minScorePacket = Cache::store('redis')->lpop($rediscode);
    //    dump($minScorePacket);exit;
       // print_r($minScorePacket);
       if (!empty($minScorePacket)) {
           $money = $minScorePacket;
          
           // 删除该元素
           // Cache::store('redis')->zrem($code, $minScorePacket[0]);
       } else {
           $this->error('Bonusnya hilang',"",218);
       }
       
       
       
       // if ($red_envelope['num'] <= $red_envelope['residue_num']) {
       //     $this->error('The red envelope has been received',"",218);
       // }
       //设置剩余红包数
       $length = Cache::store('redis')->llen($rediscode);
       $n = $red_envelope['num']-$length;
       Db::table('lc_red_envelope')->where("code='{$code}' and f_user_id={$f_id}")->update(['residue_num' => $n]);
       if($n == 0){
           Cache::store('redis')->del($rediscode);
       }
       
    //    dump($money);exit;
       Db::table('lc_user')->where("id={$user['id']}")->setInc('withdrawable', $money);
       $record_data = [
           'uid' => $user['id'],
           'pid' => $red_envelope['id'],
           'money' => $money,
           'time_zone' => $time_zone,
           'act_time' => $act_time,
           'time' => $time
       ];
       Db::table('lc_red_envelope_record')->insert($record_data);
       addFunding($user['id'],$money,0,1,20,$language, 2);
       $this->success('success',['money' => $money]);
       
       

   }
   // 随机红包处理
   public function getBonus($money, $num, $min, $max)  //$num是剩余红包数量
   {
       $num-=1;
       if ($num * $min >= $money) {
           throw new \Exception('Minimum amount out of range');
       }
       if ($num * $max <= $money) {
           throw new \Exception('The maximum amount is too small');
       }
       $kmix = max($min, $money - $num * $max); //最小金额
       $kmax = min($max, $money - $num * $min); //最大金额

       $kAvg = $money / ($num + 1);
       //获取最大值和最小值的距离之间的最小值
       $kDis = min($kAvg - $kmix, $kmax - $kAvg);
       //获取0到1之间的随机数与距离最小值相乘得出浮动区间，这使得浮动区间不会超出范围
       $r = ((float)(rand(1, 10000) / 10000) - 0.5) * $kDis * 2;
       $k = round($kAvg + $r, 2);
       return $k;
   }

   // 添加收货地址
   public function add_address() {
       $params = $this->request->param();
       $language = $params["language"];
       $address = $params["address"];
       $name = $params["name"];
       $phone = $params["phone"];
       $this->checkToken($language);
       $uid = $this->userInfo['id'];
       Db::name('LcUser')->where("id=$uid")->update(['address' => $address, 'address_name' => $name, 'address_phone' => $phone]);
       $this->success('success');
   }

   // 修改密码
   public function edit_password() {
       $params = $this->request->param();
       $language = $params["language"];
       $password = $params["new_password"];
       $old_password = $params["old_password"];
       $md5_old_password = md5($old_password);
       $this->checkToken($language);
       $uid = $this->userInfo['id'];
       if (!Db::name('LcUser')->where("id=$uid and password='$md5_old_password'")->find()) {
           $this->error('Old password error',"",218);
       }
       Db::name('LcUser')->where("id=$uid")->update(['password' => md5($password)]);
       $this->error('login.loginFirst','',403);
//       $this->success('success');
   }

   //获取邀请函
   public function invitation(){
       $params = $this->request->param();
       $language = $params["language"];
       $this->checkToken($language);
       $uid = $this->userInfo['id'];
       // $user = Db::name('LcUser')->find($uid);
       
       // $page = $params["page"];
       // $listRows = $params["listRows"];
       // alias('i')->field('i.*,d.title_en_us as title,u.username, d.type as dtype');
       // $query->join('lc_article d','i.article_id=d.id')->join('lc_user u','i.uid=u.id')->like('u.username#u_username')->dateBetween('i.created_at#i_time')->order('i.id desc')->page();
       $list = Db::name('lc_invitation_appoint')->alias('i')->field("article_id as id,d.title_en_us as title")->join('lc_article d','i.article_id=d.id')->where("i.uid = $uid")->order("created_at desc")->select();
       // $length = Db::name('lc_invitation_appoint')->field("article_id")->where("uid = $uid")->order("created_at desc")->count();
       
       $data = array(
           'list' => $list
           // ,
           // 'length' => $length
       );
       $this->success("success", $data);
       
   }

}
