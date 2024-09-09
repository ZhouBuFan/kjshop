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

use library\File;
use think\Db;
use think\facade\Request;
use think\facade\Session;

if (!function_exists('isLogin')) {
    /**
     * @description：判断是否登录
     * @date: 2020/5/13 0013
     * @return bool
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    function isLogin()
    {
        $uid = Session::get('uid');
        if (!$uid) return false;
        $user = Db::name('LcUser')->find($uid);
        if (!$user || !$user['clock']) return false;
        $data = ['logintime' => date('Y-m-d H:i:s'), 'id' => $uid];
        Db::name('LcUser')->update($data);
        return true;
    }
}
/**
 * @description：根据id生成邀请码
 * @date: 2022/6/17
 * @param $userId
 * @return string
 */
function createCode($userId)
{
    $sourceString = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';
    $code = '';
    while ($userId > 0) {
        $mod = $userId % 35;
        $userId = ($userId - $mod) / 35;
        $code = $sourceString[$mod] . $code;
    }
    if (strlen($code) < 6) $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    return $code;
}
/**
 * @description：插入用户关系（闭包表方式）
 * @date: 2022/6/17
 * @param $userId
 * @param $parentid
 * @param $invite_level  分销级别
 * @return bool
 */
function insertUserRelation($userId,$parentid,$invite_level)
{
    //查询上级关系网
    $topRelation = Db::name('LcUserRelation')->where(['uid' => $parentid])->order('id asc')->select();
    
    Db::name('LcUserRelation')->insert(['uid' => $userId,'parentid' => $parentid,'level' => 1]);
    //插入上级关系网
    if(!empty($topRelation)){
        foreach ($topRelation as $key => $top) {
            //当达到分销级别时，跳出循环
            if($key>=$invite_level-1){
                break;
            }
            Db::name('LcUserRelation')->insert([
                'uid' => $userId,
                'parentid' => $top['parentid'],
                'level' => $top['level']+1
                ]);
        }
    }
    return true;
}
/**
 * @description：发送邮件
 * @date: 2022/6/17
 * @param $to
 * @param $title
 * @param $content
 * @return bool
 */
function sendMail($to,$title, $content)
{
    require_once env("root_path") . "/vendor/phpmailer/src/Mailer.php";
    // 实例化 QQMailer
    $mailer = new \Mailer(true); 
    $email = Db::name('LcEmail')->find(1);
    $host  = $email['host'];
    $post  = $email['port'];
    $smtp  = $email['smtp'];
    $charset  = $email['charset'];
    $username  = $email['username'];
    $password  = $email['password'];
    $nickname  = $email['nickname'];
    
    return $mailer->send($to, $title, $content,$host,$post,$smtp,$charset,$username,$password,$nickname);
}

/**
 * @param string $dateTime 时间，如：2020-04-22 10:10:10
 * @param string $fromZone 时间属于哪个时区
 * @param string $toZone   时间转换为哪个时区的时间
 * @param string $format   时间格式，如：Y-m-d H:i:s
 * 时区选择参考：https://www.php.net/manual/zh/timezones.php 常见的如：UTC,Asia/Shanghai
 * 时间格式参考：https://www.php.net/manual/zh/datetime.formats.php
 *
 * @return string
 */
function dateTimeChangeByZone($dateTime, $fromZone, $toZone, $format = 'Y-m-d H:i:s')
{
    $dateTimeZoneFrom = new DateTimeZone($fromZone);
    $dateTimeZoneTo   = new DateTimeZone($toZone);
    $dateTimeObj      = DateTime::createFromFormat($format, $dateTime, $dateTimeZoneFrom);
    $dateTimeObj->setTimezone($dateTimeZoneTo);
 
    return $dateTimeObj->format($format);
}
/**
 * @description：根据语言获取时区
 * @date: 2022/6/17
 * @param $language
 *
 * @return string
 */
function getTimezoneByLanguage($language)
{
    $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
 
    return $currency['time_zone'];
}
/**
 * @description：根据时区获取语言
 * @date: 2022/6/17
 * @param $timeZone
 *
 * @return string
 */
function getLanguageByTimezone($timeZone)
{
    $currency = Db::name('LcCurrency')->where(['time_zone' => $timeZone])->find();
 
    return $currency['country'];
}
/**
 * @description：根据语言获取货币
 * @date: 2022/6/17
 * @param $language
 *
 * @return string
 */
function getCurrencyByLanguage($language)
{
    $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
 
    return $currency['symbol'];
}
/**
 * @description：根据语言换算金额
 * @date: 2022/6/17
 * @param $timeZone
 *
 * @return string
 */
function changeMoneyByLanguage($money,$language)
{
    $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
    
    return number_format($money*$currency['price'],4,".","");
}
/**
 * @description：根据语言换算美元
 * @date: 2022/6/17
 * @param $timeZone
 *
 * @return string
 */
function changeMoneyToUsdByLanguage($money,$language)
{
    $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
    
    return number_format($money/$currency['price'],4,".","");
}
/**
 * @description：根据IP获取语言
 * @date: 2022/6/17
 * @param $timeZone
 *
 * @return string
 */
function getLanguageByIp($ip)
{
    $result = requestPost('http://ip-api.com/json/'.$ip.'?lang=zh-CN');
    $json= json_decode($result, true);
    $country = "en_us";
    $city = isset($json['country']) ? $json['country'] : '';
    
    if (strpos($city, '中国') !== false) {
        $country = "zh_cn";
    }else if (strpos($city, '香港') !== false) {
        $country = "zh_hk";
    }else if (strpos($city, '美国') !== false) {
        $country = "en_us";
    }else if (strpos($city, '泰国') !== false) {
        $country = "th_th";
    }else if (strpos($city, '越南') !== false) {
        $country = "vi_vn";
    }else if (strpos($city, '日本') !== false) {
        $country = "ja_jp";
    }else if (strpos($city, '韩国') !== false) {
        $country = "ko_kr";
    }else if (strpos($city, '马来西亚') !== false) {
        $country = "ms_my";
    }else if (strpos($city, '葡萄牙') !== false) {
        $country = "pt_pt";
    }else if (strpos($city, '西班牙') !== false) {
        $country = "es_es";
    }else if (strpos($city, '土耳其') !== false) {
        $country = "tr_tr";
    }else if (strpos($city, '印度尼西亚') !== false) {
        $country = "id_id";
    }else if (strpos($city, '德国') !== false) {
        $country = "de_de";
    }else if (strpos($city, '法国') !== false) {
        $country = "fr_fr";
    }
    
    return $country;
}
/**
 * 模拟post进行url请求
 */
function requestPost($path) {
    $header = array();
    $header[] = 'Content-Type:application/x-www-form-urlencoded';
    $ch = curl_init();//初始化curl
    curl_setopt($ch, CURLOPT_URL,$path);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    $data = curl_exec($ch);//运行curl
    curl_close($ch);
     
    return $data;
}
/**
 * Describe:添加流水
 * DateTime: 2022/6/17
 * @param $uid
 * @param $money_usd
 * @param $money_act
 * @param $type
 * @param $reason
 * @return bool
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function addFunding($uid,$money_usd,$money_act,$type,$fund_type,$language = "zh_cn", $price_type=1)
{
    $user = Db::name('LcUser')->find($uid);
    if (!$user) return false;
    if ($user['money'] < 0) return false;
    //货币转换
    $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
    //时区转换
    $time = date('Y-m-d H:i:s');
    
    $time_zone = $currency['time_zone'];
    $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
    
    $data = array(
        'uid' => $uid,
        'money' => $money_usd,
        'money2' => $money_act,
        'type' => $type,
        'fund_type' => $fund_type,
        'price_type' => $price_type,
        'before' => $user['money'],
        'currency' => $currency['symbol'],
        'time' => date('Y-m-d H:i:s'),
        'act_time' => $act_time,
        'time_zone' => $time_zone
    );
    Db::startTrans();
    $re = Db::name('LcUserFunding')->insert($data);
    if ($re) {
        Db::commit();
        return true;
    } else {
        Db::rollback();
        return false;
    }
}

/**
 * Describe:添加积分流水
 * DateTime: 2022/6/17
 * @param $uid
 * @param $money_usd
 * @param $type
 * @param $reason
 * @return bool
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function addIntegral($uid,$money_usd,$type,$fund_type,$language = "zh_cn")
{
    $user = Db::name('LcUser')->find($uid);
    if (!$user) return false;
    if ($user['integral'] < 0) return false;
    //货币转换
    $currency = Db::name('LcCurrency')->where(['country' => $language])->find();
    //时区转换
    $time = date('Y-m-d H:i:s');
    
    $time_zone = $currency['time_zone'];
    $act_time = dateTimeChangeByZone($time, 'Asia/Shanghai', $time_zone, 'Y-m-d H:i:s');
    
    $data = array(
        'uid' => $uid,
        'money' => $money_usd,
        'type' => $type,
        'fund_type' => $fund_type,
        'before' => $user['integral'],
        'time' => date('Y-m-d H:i:s'),
        'act_time' => $act_time,
        'time_zone' => $time_zone
    );
    Db::startTrans();
    $re = Db::name('LcUserIntegral')->insert($data);
    if ($re) {
        Db::commit();
        return true;
    } else {
        Db::rollback();
        return false;
    }
}
/**
 * Describe:模拟请求
 * DateTime: 2022/7/17
 */
function http_post_yd($url, $data_string)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-AjaxPro-Method:ShowList',
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($data_string))
    );
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    $data = curl_exec($ch);
    curl_close($ch);

    //var_dump(curl_error($ch));die;

    return $data;
}

/**
 * -----------------------------优盾start------------------------
 * /
/**
 * Describe:获取商户支持的币种信息
 * DateTime: 2022/7/17
 */
function supportCoins($showBalance = true)
{
    $merchant = Db::name('LcMerchant')->find(1);
    $body = array(
        'merchantId' => $merchant['mer_num'],
        'showBalance' => $showBalance
    );

    $body = json_encode($body);
    $timestamp = time();
    $nonce = rand(100000, 999999);

    $url = $merchant['gate_add'].'/mch/support-coins';
    $key = $merchant['api_key'];

    $sign = md5($body.$key.$nonce.$timestamp);
    
    $data = array(
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'sign' => $sign,
        'body' => $body
    );

    $data_string = json_encode($data);

    return http_post_yd($url, $data_string);
}
/**
 * Describe:创建地址
 * DateTime: 2022/7/17
 */
function createAddress()
{
    $merchant = Db::name('LcMerchant')->find(1);
    $body = array(
        'merchantId' => $merchant['mer_num'],
        'coinType' => intval($merchant['main_coin_type']),
        'callUrl' => $merchant['call_url'],
    );

    $body = '['.json_encode($body).']';
    $timestamp = time();
    $nonce = rand(100000, 999999);

    $url = $merchant['gate_add'].'/mch/address/create';
    $key = $merchant['api_key'];

    $sign = md5($body.$key.$nonce.$timestamp);
    
    $data = array(
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'sign' => $sign,
        'body' => $body
    );

    $data_string = json_encode($data);

    return http_post_yd($url, $data_string);
}
/**
 * Describe:校验地址合法性
 * DateTime: 2022/7/17
 */
function checkAddress($address)
{
    $merchant = Db::name('LcMerchant')->find(1);
    $body = array(
        'merchantId' => $merchant['mer_num'],
        'mainCoinType' => intval($merchant['main_coin_type']),
        'address' => $address,
    );

    $body = '['.json_encode($body).']';
    $timestamp = time();
    $nonce = rand(100000, 999999);

    $url = $merchant['gate_add'].'/mch/check/address';
    $key = $merchant['api_key'];

    $sign = md5($body.$key.$nonce.$timestamp);
    
    $data = array(
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'sign' => $sign,
        'body' => $body
    );

    $data_string = json_encode($data);
    return http_post_yd($url, $data_string);
}
/**
 * Describe:代付
 * DateTime: 2022/7/17
 */
function proxypay($address, $amount, $businessId)
{
    $merchant = Db::name('LcMerchant')->find(1);
    $body = array(
        'merchantId' => $merchant['mer_num'],
        'mainCoinType' => $merchant['main_coin_type'],
        'address' => $address,
        'amount' => $amount,
        'coinType' => $merchant['coin_type'],
        'callUrl' => $merchant['call_url'],
        'businessId' => $businessId
    );

    $body = '['.json_encode($body).']';
    $timestamp = time();
    $nonce = rand(100000, 999999);

    $url = $merchant['gate_add'].'/mch/withdraw/proxypay';
    $key = $merchant['api_key'];

    $sign = md5($body.$key.$nonce.$timestamp);
    
    $data = array(
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'sign' => $sign,
        'body' => $body
    );

    $data_string = json_encode($data);

    return http_post_yd($url, $data_string);
}
/**
 * -----------------------------优盾end------------------------
 * /

/**
 * @description：
 * @date: 2020/5/14 0014
 * @param $str
 * @param $type
 * @return bool
 */
function judge($str, $type)
{
    $char = '';
    if ($type == 'int') {
        $char = '/^\\d*$/';
    } else if ($type == 'email') {
        $char = '/([\\w\\-]+\\@[\\w\\-]+\\.[\\w\\-]+)/';
    } else if ($type == 'idcard') {
        $char = '/[0-9]{17}([0-9]|X)/';
    } else if ($type == 'name') {
        $char = '/^[\\x{4e00}-\\x{9fa5}]+[·•]?[\\x{4e00}-\\x{9fa5}]+$/u';
    } else if ($type == 'phone') {
        $char = '/^1[3456789]{1}\\d{9}$/';
    } else if ($type == 'tel') {
        $char = '/(^(\\d{3,4}-)?\\d{7,8})$/';
    } else if ($type == 'date') {
        $char = '/^\\d{4}[\\-](0?[1-9]|1[012])[\\-](0?[1-9]|[12][0-9]|3[01])?$/';
    } else if ($type == 'time') {
        $char = '/^\\d{4}[\\-](0?[1-9]|1[012])[\\-](0?[1-9]|[12][0-9]|3[01])(\\s+(0?[0-9]|1[0-9]|2[0-3])\\:(0?[0-9]|[1-5][0-9])\\:(0?[0-9]|[1-5][0-9]))?$/';
    } else if ($type == 'username') {
        $char = '/^[0-9a-zA-Z_]{1,}$/';
    } else if ($type == 'digit') {
        $char = '/^[0-9]*$/';
    } else if ($type == 'mobile_phone') {
        $char = '/^\\d{6,16}$/';
    } else if ($type == 'exist') {
    } else {
        return false;
    }
    if (preg_match($char, $str)) {
        return true;
    }
    return false;
}

/**
 * @description：设置
 * @date: 2020/5/13 0013
 * @param $database
 * @param $field
 * @param $value
 * @param int $type
 * @param string $where
 * @return int|true
 * @throws \think\Exception
 */
function setNumber($database, $field, $value, $type = 1, $where = '')
{
    if ($type != 1) {
        $re = Db::name($database)->where($where)->setDec($field, $value);
    } else {
        $re = Db::name($database)->where($where)->setInc($field, $value);
    }
    return $re;
}

/**
 * @description：脱敏
 * @date: 2020/5/14 0014
 * @param $string
 * @param int $start
 * @param int $length
 * @param string $re
 * @return bool|string
 */
function dataDesensitization($string, $start = 0, $length = 0, $re = '*')
{
    if (empty($string)) {
        return false;
    }
    $strarr = array();
    $mb_strlen = mb_strlen($string);
    while ($mb_strlen) {
        $strarr[] = mb_substr($string, 0, 1, 'utf8');
        $string = mb_substr($string, 1, $mb_strlen, 'utf8');
        $mb_strlen = mb_strlen($string);
    }
    $strlen = count($strarr);
    $begin = $start >= 0 ? $start : ($strlen - abs($start));
    $end = $last = $strlen - 1;
    if ($length > 0) {
        $end = $begin + $length - 1;
    } elseif ($length < 0) {
        $end -= abs($length);
    }
    for ($i = $begin; $i <= $end; $i++) {
        $strarr[$i] = $re;
    }
    if ($begin >= $end || $begin >= $last || $end > $last) return false;
    return implode('', $strarr);
}

/**
 * Describe:设置会员等级
 * DateTime: 2020/5/13 23:49
 * @param $member
 * @return mixed|string
 */
function setUserMember($uid, $value)
{
    $member = Db::name('LcUserMember')->where("value <= '{$value}'")->order('value desc')->find();
    $mid = 0;
    if (!empty($member)) {
        $mid = $member['id'];
    }
    $user = Db::name("LcUser")->find($uid);
    if($user['mid']!=$mid){
        //添加抽奖次数
        $draw = Db::name('LcDraw')->find(1);
        if($draw['invest']>0){
            setNumber('LcUser', 'draw_num', $draw['invest'], 1, "id = $uid");
        }
    }
    
    Db::name('LcUser')->where("id = {$uid}")->update(array('mid' => $mid));
    return $mid;
}
/**
 * Describe:会员等级
 * DateTime: 2020/5/13 23:49
 * @param $member
 * @return mixed|string
 */
function getUserMember($mid)
{
    $member = Db::name('LcUserMember')->where("id = {$mid}")->value('name');
    return $member ? $member : 'VIP1';
}


/**
 * @description：获取网站配置
 * @date: 2020/5/14 0014
 * @param $value
 * @return mixed
 */
function getInfo($value)
{
    return Db::name('LcInfo')->where('id', 1)->value($value);
}

/**
 * @description：获取配置参数表
 * @date: 2020/5/14 0014
 * @param $value
 * @return mixed
 */
function getSysConfig($value)
{
    return Db::name('SystemConfig')->where('name', $value)->value('value');
}

/**
 * @description：获取奖励配置
 * @date: 2020/5/14 0014
 * @param $value
 * @return mixed
 */
function getReward($value)
{
    return Db::name('LcReward')->where('id', 1)->value($value);
}


function diffBetweenTwoDays($day1, $day2)
{
    $second1 = strtotime($day1);
    $second2 = strtotime($day2);
    if ($second1 < $second2) {
        $tmp = $second2;
        $second2 = $second1;
        $second1 = $tmp;
    }
    return ($second1 - $second2) / 86400;
}
/**
 * @description：获取流水记录
 * @date: 2022/8/5
 * @param $value
 * @return mixed
 */
function getFundingByTime($start,$end,$uid="",$fund_type="")
{
    return Db::name('LcUserFunding')->where("time >= '$start' AND time <= '$end' AND uid = '{$uid}' AND fund_type = '{$fund_type}'")->select();
}
/**
 * @description：获取用户认证状态
 * @date: 2022/8/15
 * @param $value
 * @return mixed
 */
function getUserNeedAuth($uid)
{
    $user = Db::name("LcUser")->find($uid);
    $info = Db::name('LcInfo')->find(1);
    
    switch ($info['funding_need_auth'])
    {
        //手机号
    case 0:
        if(!$user['auth_phone']) return true;
        break;
        //邮箱
    case 1:
        if(!$user['auth_email']) return true;
        break;
        //google
    case 2:
        if(!$user['auth_google']) return true;
        break;
        //都要
    case 3:
        if(!$user['auth_phone']||!$user['auth_email']||!$user['auth_google']) return true;
        break;
    }
    return false;
}
/**
 * @description：设置团队充值奖励
 * @date: 2022/8/15
 * @param $value
 * @return mixed
 */
function setTemRechargeReward($uid,$money)
{
    $relations = Db::name("LcUserRelation")->where(['uid'=>$uid])->select();
    foreach ($relations as &$relation) {
        $user = Db::name("LcUser")->find($relation['parentid']);
        $vip = Db::name("LcUserMember")->find($user['mid']);
        if(!empty($vip)){
            $direct = $vip['rewards_direct'];
            $undirect = $vip['rewards_undirect'];
            $language = getLanguageByTimezone($user['time_zone']);
            $uid = $user['id'];
            //奖励
            if($money>0.01 && ($direct>0||$undirect>0)){
                $reward_money = 0;
                $fund_type = 12;
                //直接
                if($relation['level']==1){
                    $reward_money = $money*$direct/100;
                }
                //间接
                else{
                    $reward_money = $money*$undirect/100;
                    $fund_type = 13;
                }
                if($reward_money>0){
                    //流水添加
                    addFunding($uid,$reward_money,changeMoneyByLanguage($reward_money,$language),1,$fund_type,$language);
                    //添加余额
                    setNumber('LcUser', 'money', $reward_money, 1, "id = $uid");
                    //添加冻结金额
                    if(getInfo('reward_need_flow')){
                        setNumber('LcUser', 'frozen_money', $reward_money, 1, "id = $uid");
                    }
                }
            }
        }
    }
    
    return false;
}





/**
 * @description：判断密码的简易程度
 * @date: 2020/9/3 0003
 * @param $pass
 * @return bool
 */
function payPassIsContinuity($pass)
{
    //是纯数字  则判断是否连续
    if (is_numeric($pass)) {
        if (strlen($pass) != 6) return true;
        static $num = 1;
        for ($i = 0; $i < strlen($pass); $i++) {
            if (substr($pass, $i, 1) + 1 == substr($pass, $i + 1, 1)) {
                $num++;
            }
        }
        if ($num == strlen($pass)) {
            return true;
        } else {
            return false;
        }
    } else {
        return true;
    }
}

/**
 * Describe:获取本月日期
 * DateTime: 2020/9/5 16:00
 * @return array
 */
function getAllMonthDays()
{
    $monthDays = [];
    $firstDay = date('Y-m-01', time());
    $i = 0;
    $lastDay = date('Y-m-d', strtotime("$firstDay +1 month -1 day"));
    while (date('Y-m-d', strtotime("$firstDay +$i days")) <= $lastDay) {
        $monthDays[] = date('Y-m-d', strtotime("$firstDay +$i days"));
        $i++;
    }
    return $monthDays;
}

/**
 * Describe:检查是否WAP
 * DateTime: 2020/9/5 20:26
 * @return bool
 */
function check_wap(){
    if(preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])){
        return true;
    }
    else{
        return false;
    }
}
/**
 * Describe:计算概率
 * DateTime: 2022/8/29
 * @return float|int
 */
function get_rand($proArr) {   
    $result = '';   
    //概率数组的总概率精度  
    $proSum = array_sum($proArr);   
    //概率数组循环    
    foreach ($proArr as $key => $proCur) {   
        $randNum = mt_rand(1, $proSum);               
        if ($randNum <= $proCur) {   
            $result = $key;                         
            break;   
        } else {   
            $proSum -= $proCur;                       
        }   
    }   
    unset ($proArr);   
    return $result;   
}

/**
 * @description：获取提示语
 * @date: 2022/6/17
 * @param $userId
 * @return string
 */
function getTipsByLanguage($code,$language)
{
    $tips = array();
    switch ($language)
    {
    case 'zh_cn':
        $tips = array(
            "auth_eamil" => '验证邮箱验证码',
            "eamil_tips1" => '您的验证码是',
            "eamil_tips2" => '，如非本人操作，请忽略',
        );
        break;
    case 'zh_hk':
        $tips = array(
            "auth_eamil" => '驗證郵箱驗證碼',
            "eamil_tips1" => '您的驗證碼是',
            "eamil_tips2" => '，如非本人操作，請忽略',
        );
        break;
    case 'en_us':
        $tips = array(
            "auth_eamil" => 'Email Verification',
            "eamil_tips1" => 'Your verification code is "',
            "eamil_tips2" => '", please ignore if it is not done by me',
        );
        break;
    case 'th_th':
        $tips = array(
            "auth_eamil" => 'การยืนยันอีเมล',
            "eamil_tips1" => 'รหัสตรวจสอบของคุณคือ "',
            "eamil_tips2" => '"',
        );
        break;
    case 'vi_vn':
        $tips = array(
            "auth_eamil" => 'Email xác thực',
            "eamil_tips1" => 'Mã xác minh của bạn là "',
            "eamil_tips2" => '"',
        );
        break;
    case 'ja_jp':
        $tips = array(
            "auth_eamil" => 'メールの確認',
            "eamil_tips1" => 'あなたの確認コードは「',
            "eamil_tips2" => '」です',
        );
        break;
    case 'ko_kr':
        $tips = array(
            "auth_eamil" => '이메일 확인',
            "eamil_tips1" => '귀하의 인증 코드는 "',
            "eamil_tips2" => '"입니다',
        );
        break;
    case 'ms_my':
        $tips = array(
            "auth_eamil" => 'pengesahan email',
            "eamil_tips1" => 'Kod pengesahan anda ialah "',
            "eamil_tips2" => '"',
        );
        break;
    case 'pt_pt':
        $tips = array(
            "auth_eamil" => 'Verificar código de verificação de e-mail',
            "eamil_tips1" => 'Seu código de verificação é "',
            "eamil_tips2" => '"',
        );
        break;
    case 'es_es':
        $tips = array(
            "auth_eamil" => 'Verificar código de verificación de correo electrónico',
            "eamil_tips1" => 'Su código de verificación es "',
            "eamil_tips2" => '"',
        );
        break;
    case 'tr_tr':
        $tips = array(
            "auth_eamil" => 'E-posta doğrulama kodunu doğrulayın',
            "eamil_tips1" => 'Doğrulama kodunuz "',
            "eamil_tips2" => '"',
        );
        break;
    case 'id_id':
        $tips = array(
            "auth_eamil" => 'Verifikasi kode verifikasi email',
            "eamil_tips1" => 'Kode verifikasi Anda adalah "',
            "eamil_tips2" => '"',
        );
        break;
    case 'de_de':
        $tips = array(
            "auth_eamil" => 'Bestätigen Sie den E-Mail-Bestätigungscode',
            "eamil_tips1" => 'Ihr Verifizierungscode lautet „',
            "eamil_tips2" => '“',
        );
        break;
    case 'fr_fr':
        $tips = array(
            "auth_eamil" => "Vérifier le code de vérification de l'e-mail",
            "eamil_tips1" => 'Votre code de vérification est "',
            "eamil_tips2" => '"',
        );
        break;
    }
     
    return $tips[$code];
}

function countUserRelation($userId) {
    return Db::name("LcUserRelation")
        ->alias('ur')
        ->join('lc_user u', 'ur.uid=u.id')
        ->where("u.mid >= {$userId} and ur.level in (1,2,3) and ur.parentid = {$userId}")
        ->group('ur.parentid')
        ->count();
}

function countMemberRelation($userId) {
    return Db::name("LcUserRelation")
        ->alias('ur')
        ->join('lc_user u', 'ur.uid=u.id')
        ->where("u.mid >= 8006 and ur.parentid = {$userId}")
        ->group('ur.parentid')
        ->count();
}

function updateMemberLevel($user, $num, $levelInc, $member_num = null, $member_threshold = null) {
    if ($num > $levelInc && $member_num > $member_threshold) {
        Db::name('LcUser')->where("id = {$user['id']}")->update(['mid' => ($user['mid'] + 1)]);
    }
}
