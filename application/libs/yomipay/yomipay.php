<?php
namespace app\libs\yomipay;

class yomipay {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'https://api.youmipay.top'; //网关地址切换正式环境不需要替换

		
	//以下3个参数需要开启正式商户号后替换.
    // public $password = 'YwIMtFOIecvQ'; //商户秘钥
	// public $authorizationKey = '91592463';  //商户号

    public $pay_memberid = '88012'; //商户号

    public $mchPrivateKey = 'MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBANgZNcniuTgdkh9O
    OiUCSwIlQnWL2Hu/VpLwf574rs9ISV5NLuHwzN5mD9un6S0HcrqXDdEVPxe37xQK
    Q/D/98dpQg2OI28wccnN3TzJUuJ+zFy+44JHdwmwrrJWBY6bMteJwtCUe2wIzMZS
    jtO7oL41I9Vv3J0t/FTohrOQMW0DAgMBAAECgYEAgIEdV1yXwCL1jeA6+18Ns8zs
    ZHIw3gW+OcsCWUqQyXq3BnjndDx515bhv0Fui/Rt6T+CW99CkZwzc7tXA61zbe1g
    E51xVzXzg8ypRGANuQqswpU0Xzqumj6SaNo+J+TIQuaf1aMRyXvZs43tE7t65RwL
    z/ZihZQ75b39PVD/y+ECQQD8WsVkIsPJNvYrnfOendG+MZprke4lNZgiMvElludV
    SF3SUqbPy9Wii+hBxjvwYiUj30uDuUI3g3FCidpJSNG9AkEA2zhcYtRq7amXfQ+J
    bW7FmjsVIdlkh3t0C4XtTRQscXP53k69QjTnlEsDe+dZs6iJNEKlmiSXKuhw6wu5
    oHlFvwJAbngJ6uDxFhdSQhu99tSdaYXrVGWoe29vrqDgQZVCpWmtcJGv5k0TszlN
    reVqfbtpCMAKHZquqwCGRxtzjBstfQJBALtb9DnTaoAtTffo63/ICMLEdE81yaGx
    dYDTufkCoOlmQcwqZ77KEJLBefzPwe62wG0V+QtA8qINf1Sj9MaeX2kCQBzdDuys
    m7/i+Y+O3Dz5xi8st3FRN8KWkpxP5IsaA+fama6uTiTxvhm3kgI0+eRKXUhYrNIr
    58EjTmnAVMGTRgE='; //商户私钥

    //平台公钥
    public $platPublicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC19cIDiIFchrj7Pnmyj0hVfLEF
    cVeHcYbzK3mawdgSMPeRW/lkhcBFD+tVpE1/+rEq2A5yShYIBbOBs2NLUJRlzhHw
    J3sQmL06LGWFwAaSu3MF0UgBxTHt5aXgoT1RUNILz5NIPUEMI1ScqjTksiI2vNLS
    Ds6OY7v0aENn3allyQIDAQAB';
    //创建收银台订单
    static public $oderReceive = self::HOST_URL . '/Pay_api.html';
    // //创建代付订单
    static public $oderOut = self::HOST_URL . '/Payment_Api';
    // //代付订单查询
    // static public $oderQuery = self::HOST_URL . '/dfpay/query';

    /**
     * 发起订单
     */
    public function send_pay($params) {
        $data = [
            // 'merchant_id' => $this->authorizationKey,
            // 'order_no' => $params['order_no'],
            // 'submit_time' => date('Y-m-d H:i:s'),
            // 'bank_code' => $params['pay_code'],
            // 'notify_url' => getInfo('domain_api')."/index/index/ff_pay_callback",
            // 'return_url' => getInfo('domain').'/#/recharge/record',
            // 'amount' => $params['amount'],
            "pay_memberid" =>$this->pay_memberid,
            "pay_orderid" => $params['order_no'],
            "pay_amount" => $params['amount'],
            "pay_applydate" => date('Y-m-d H:i:s'),
            "pay_bankcode" => $params['pay_code'],
            "pay_notifyurl" => getInfo('domain_api')."/index/index/yomi_pay_callback",
            "pay_callbackurl" => getInfo('domain').'/#/recharge/record',
            "pay_type" => 'default',
            "name" => '',
            'email' => '',
        ];

        $array = [];
        if (isset($_POST['os_type'])) $array['os_type'] = 'IOS';//手机端系统 设备标识ANDROID/IOS
        if ($array) {
            $data['pay_attach'] = json_encode($array);
        }


        //排序，值为空不拼接，拼接完的值做加密 生成签名
        ksort($data);
        $str = "";
        foreach ($data as $key => $val) {
            if (empty($val)) {
                unset($data[$key]);
            } else {
                $str = $str . $val;
            }
        }
        $sign = $this->pivate_key_encrypt($str, $this->mchPrivateKey);
        $data["pay_sign"] = $sign;


        // $data['sign'] = $this->getSign($data);
        return $this->curlPost(self::$oderReceive, $data);
    }

    /**
     * 发起代付订单
     */
    public function sends_pay_out($params) {
        $data = [
            'mch_id' => $this->pay_memberid,
            'out_trade_no' => $params['order_no'],
            'bank_code' => $params['bankname'], // 银行名称
            'account_name' => str_replace(" ","",$params['accountname']), // 收款人姓名
            'card_number' => $params['cardnumber'], // 银行卡号
            'money' => $params['money'],
            
        ];
        // $sign = $this->pivate_key_encrypt($str, $this->mchPrivateKey);
        // $data["pay_sign"] = $sign;
        
        // $data['logs']=$ress;
        
        $data['notify_url'] = getInfo('domain_api')."/index/index/yomi_out_pay_callback";
        //排序，值为空不拼接，拼接完的值做加密 生成签名
        ksort($data);
        $str = "";
        foreach ($data as $key => $val) {
            if (empty($val)) {
                unset($data[$key]);
            } else {
                $str = $str . $val;
            }
        }
        $sign = $this->pivate_key_encrypt($str, $this->mchPrivateKey);
        $data["pay_sign"] = $sign;
        
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        $curr_date = date('Y-m-d H:i:s');
        $res = $data['mch_id'].' + '.$data['out_trade_no'].' + '.$data['bank_code'].' + '.$data['account_name'].' + '.$data['card_number'].' + '.$data['money'].' + '.$data['pay_sign'];
        file_put_contents('/www/wwwroot/web/hd/application/libs/ffpay/pay_out_list.log', "【".$curr_date."】:".($res).PHP_EOL,FILE_APPEND);
        // $res = json_decode($str, true);
        // $res = json_decode($str, true);

        return $this->curlPost(self::$oderOut, $data);
    }

    /**
     * 获取签名
     */
    public function getSign($data, $pivate_key) {
        $pivate_key = '-----BEGIN PRIVATE KEY-----' . "\n" . $pivate_key . "\n" . '-----END PRIVATE KEY-----';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }

    function pivate_key_encrypt($data, $pivate_key)
    {
        $pivate_key = '-----BEGIN PRIVATE KEY-----' . "\n" . $pivate_key . "\n" . '-----END PRIVATE KEY-----';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }
    
        return base64_encode($crypto);
    }

    function public_key_decrypt($data, $public_key)
    {
        $public_key = '-----BEGIN PUBLIC KEY-----' . "\n" . $public_key . "\n" . '-----END PUBLIC KEY-----';
        $data = base64_decode($data);
        $pu_key = openssl_pkey_get_public($public_key);
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pu_key);
            $crypto .= $decryptData;
        }

        return $crypto;
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
            'Content-type: application/json;charset=UTF-8'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//请求数据
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        return $result;
    }
}