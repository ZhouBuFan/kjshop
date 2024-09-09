<?php
namespace app\libs\wowpay;

class wowPay {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'https://dev.wowpayidr.com/rest'; //网关地址切换正式环境不需要替换

		
	//以下3个参数需要开启正式商户号后替换.
    public $password = ''; //商户秘钥
	public $authorizationKey = '';  //商户号
    //
    //创建收银台订单
    static public $oderReceive = self::HOST_URL . '/cash-in/payment-checkout';


    /**
     * 发起订单
     */
    public function send_pay($params) {
        $data = [
            // 'merchant_id' => $this->authorizationKey,
            'referenceId' => $params['order_no'],  //订单号
            'amount' => $params['amount'], //金额
            // 'submit_time' => date('Y-m-d H:i:s'),
            // 'bank_code' => $params['pay_code'],
            'notifyUrl' => getInfo('domain_api')."/index/index/wow_pay_callback",
            'redirectUrl' => getInfo('domain').'/#/recharge/record',
            
        ];
        // print_r($data);
        // $data['sign'] = $this->getSign($data);
        return $this->curlPost(self::$oderReceive, $data);
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