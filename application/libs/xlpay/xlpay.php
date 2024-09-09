<?php
namespace app\libs\xlpay;

class xlpay {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'https://pay.xlpay888.com'; //网关地址切换正式环境不需要替换

		
	//以下3个参数需要开启正式商户号后替换.
    // public $password = 'YwIMtFOIecvQ'; //商户秘钥

	public $authorizationKey = 'XL842';  //商户号

    public $key = 'fJyOZQNOUd3edOa6xDun'; //商户号

    
    //创建收银台订单
    static public $oderReceive = self::HOST_URL . '/payment/collection';
    // //创建代付订单
    static public $oderOut = self::HOST_URL . '/payment/payout';
    // //代付订单查询
    // static public $oderQuery = self::HOST_URL . '/dfpay/query';

      /**
     * 代收
     */
     
    public function send_pay($params) {
        $data = [
            'merchantLogin' => $this->authorizationKey,
            'orderCode' => $params['order_no'],  //订单号
            'currencyCode' =>'TK',
            'account'=>'10073974372',
            'amount' => $params['amount'], //金额
            'notifyUrl' => getInfo('domain_api')."/index/index/xl_pay_callback",
            'name' => 'Jone Connor',
            'email' => 'aaa@aaa.com',
            'phone' => '911234567890',
            'remark' => 'remark'
        ];
        $sgin = $this->getSign($params['order_no']);
        $data['sign'] = $sgin;
        return $this->curlPost(self::$oderReceive, $data);
    }



    /**
     * 代付
     */

    public function sends_pay_out($params) {
       
        $data = [
            'merchantLogin' => $this->authorizationKey,
            'orderCode' => $params['order_no'],
            'currencyCode' => 'TK',
            'amount' => intval($params['money']),
            'notifyUrl' => getInfo('domain_api')."/index/index/xl_payout_callback",
            'name' => 'Jone Connor',
            'account' => $params['cardnumber'],
            'bankCode' => $params['bankname']
            
        ];
        $sgin = $this->getSign($params['order_no']);
        $data['sign'] = $sgin;
        $curr_date = date('Y-m-d H:i:s');
        $res = $params['order_no'].'  + '.$params['cardnumber'].' + '.$params['money'].' + '.$params['accountname'];
        file_put_contents('../pay_out_list.log', "【".$curr_date."】:".($res).PHP_EOL,FILE_APPEND);
        // $res = json_decode($str, true);
        // $res = json_decode($str, true);

        return $this->curlPost(self::$oderOut, $data);
    }

    /**
     * 获取签名
     */
    public function getSign($orderno) {
       
        
        // print_r($str);
        return md5($orderno.$this->key);
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