<?php
namespace app\libs\ttpay;

class Tingting {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'https://pay-api.bot7572.com'; //网关地址切换正式环境不需要替换

		
	//以下3个参数需要开启正式商户号后替换.
    //AppSecret
    public $password = 'pMUE6URZfOfgc7ADedFFc7VIpZenfonJIXoJYxhVJUMuDbl6VfIpSmdYtRcZ11Mna1RzmuNErpN1MvxgC1dIooOtDSOEiUmoHROhsoDIkdfFXnD5KlVzPF8bmYpwtcVu'; 
	public $authorizationKey = 'M1692784344';  //商户号
    public $appId = "64e5d6d8e4b09bf4e84c401c"; // appid
    public $wayCode = "TINGTING_B2B";
    //
    //创建收银台订单
    static public $oderReceive = self::HOST_URL . '/api/pay/unifiedOrder';
    // 订单查询
    static public $orderReceiveQuery = self::HOST_URL . "api/pay/query";
    //创建代付订单
    static public $oderOut = self::HOST_URL . '/api/transferOrder';
    //代付订单查询
    static public $oderQuery = self::HOST_URL . '/api/transfer/query';
    // 关闭订单
    static public $orderClose = self::HOST_URL . '/api/pay/close';

    /**
     * 发起订单
     */
    public function send_pay($params) {
        $data = [
            'mchNo' => $this->authorizationKey,
            'appId' => $this->appId,
            'wayCode' => $this->wayCode,
            'mchOrderNo' => $params['order_no'],
            'version' => "1.0",
            'signType' => "MD5",
            'currency' => "vnd",
            'subject' => "Nạp tiền",
            'body' => "Nạp tiền",
            'reqTime' => time().rand(100,999),
            'extParam' => $params['account'],
            'notifyUrl' => getInfo('domain_api')."/index/index/tt_pay_callback",
            'returnUrl' => getInfo('domain').'/#/recharge/record',
            'amount' => $params['amount'],
        ];
        $data['sign'] = $this->getSign($data);
        return $this->curlPost(self::$oderReceive, $data);
    }

    /**
     * 发起代付订单
     */
    public function send_pay_out($params) {
        $data = [
            'mchNo' => $this->authorizationKey,
            'appId' => $this->appId,
            'mchOrderNo' => $params['order_no'],
            'ifCode' => "tingtingpay",
            'entryType' => "BANK_CARD",
            'currency' => "vnd",
            'version' => "1.0",
            'signType' => "MD5",
            'transferDesc' => "Rút tiền",
            'notifyUrl' => getInfo('domain_api')."/index/index/tt_out_pay_callback",
            'reqTime' => time().rand(100,999),
            'bankName' => $params['bankname'], // 银行名称
            'extParam' => $params['code'], // code
            'accountName' => str_replace(" ","",$params['accountname']), // 收款人姓名
            'accountNo' => $params['cardnumber'], // 银行卡号
            'amount' => $params['money'],
        ];
        $data['sign'] = $this->getSign($data);
        return $this->curlPost(self::$oderOut, $data);
    }

    /**
     * 获取签名
     */
    public function getSign($data) {
        ksort($data);
        $strA = '';
        foreach ($data as $key => $val) {
            $strA .= "$key=$val&";
        }
        $newstr = $strA."key=".$this->password; 
        return strtoupper(md5($newstr));
    }

    /**post请求
     * @param string $url
     * @param array $data
     * @return false|string
     */
    public function curlPost($url = '', $data=null)
    {
        $ch = curl_init();//初始化
        curl_setopt($ch, CURLOPT_URL, $url);//访问的URL
        curl_setopt($ch, CURLOPT_POST, true);//请求方式为post请求
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//只获取页面内容，但不输出
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//https请求 不验证证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//https请求 不验证HOST
        $header = [
            'Content-type: application/json;charset=UTF-8',
            'X-SN: '. $this->authorizationKey,
            'X-SECRET: '. $this->password
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//请求数据
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        return $result;
    }
}