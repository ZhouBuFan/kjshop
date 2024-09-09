<?php
namespace app\libs\tingtingpay;

class tingtingpay {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'https://api.tingpay.net'; //网关地址切换正式环境不需要替换
    

    public $orderUserName = "BitDigital"; // orderUserName

    public $ip = "178.128.50.239"; //本机IP


     //创建收银台订单
     static public $oderReceive = self::HOST_URL . '/api/v1/public/send-money';
     // 订单查询
     static public $orderReceiveQuery = self::HOST_URL . "api/pay/query";
     //创建代付订单
     static public $oderOut = self::HOST_URL . '/api/v1/public/withdraw-money';
     //代付订单查询
     static public $oderQuery = self::HOST_URL . '/api/transfer/query';
     // 关闭订单
     static public $orderClose = self::HOST_URL . '/api/pay/close';





  /**
     * 发起订单
     */
    public function send_pay($params) {
        
        $json = json_decode($params['account'],true);
        // print_r($json['secretKeyId']);
        $data = [
            "secretKeyId" =>$json['secretKeyId'],
            "ipAddress"=>$this->ip,
            "orderUserName"=>$this->orderUserName,
            "order"=>$params['order_no'],
            "cliNA"=>"PC",
            "notifyUrl"=> getInfo('domain_api')."/index/index/tingting_in_pay_callback",
            "totalMoney"=>$params['amount'],
            "bankId"=> $json['bankId'],
            "cardCode"=> ".",
            "serial"=> "."
        ];
        $md = [
            "secretKeyId"=> $json['secretKeyId'],
            "ipAddress"=> $this->ip,
            "orderUserName"=>  $this->orderUserName,
            "order"=> $params['order_no'],
            "cliNA"=> "PC",
            "totalMoney"=> $params['amount'],
            "secretKey"=> $json['secretKey']
        ];
        $data['md5'] = $this->getSign($md);
        // print_r($data);
        // print(self::$oderReceive);
        return $this->curlPost(self::$oderReceive, $data);
    }


      /**
     * 发起代付订单
     */
    public function send_pay_out($params) {
        $json = json_decode($params['code'],true);
        $data = [
            // 'mchNo' => $this->authorizationKey,
            // 'appId' => $this->appId,
            // 'mchOrderNo' => $params['order_no'],
            // 'ifCode' => "tingtingpay",
            // 'entryType' => "BANK_CARD",
            // 'currency' => "vnd",
            // 'version' => "1.0",
            // 'signType' => "MD5",
            // 'transferDesc' => "Rút tiền",
            // 'notifyUrl' => getInfo('domain_api')."/index/index/tt_out_pay_callback",
            // 'reqTime' => time().rand(100,999),
            // 'bankName' => $params['bankname'], // 银行名称
            // 'extParam' => $params['code'], // code
            // 'accountName' => str_replace(" ","",$params['accountname']), // 收款人姓名
            // 'accountNo' => $params['cardnumber'], // 银行卡号
            // 'amount' => $params['money'],


            "secretKeyId"=> $json['secretKeyId'],
            "ipAddress"=>$this->ip,
            "orderUserName"=>$this->orderUserName,
            "order"=> $params['order_no'],
            "cliNA"=>"PC",
            "notifyUrl" => getInfo('domain_api')."/index/index/tingting_out_pay_callback",
            "totalMoney"=> $params['money'],
            "bankName" => $params['bankname'],
            "bankAccount" => $params['cardnumber'],
            "bankUsername"=> str_replace(" ","",$params['accountname']),
            "bankId"=> $json['bankId']
            
        ];
        $md = [
            "secretKeyId"=> $json['secretKeyId'],
            "ipAddress"=> $this->ip,
            "orderUserName"=>  $this->orderUserName,
            "order"=> $params['order_no'],
            "cliNA"=> "PC",
            "totalMoney"=> $params['money'],
            "secretKey"=> $json['secretKey'],
            "bankName" => $params['bankname'],
            "bankAccount" => $params['cardnumber'],
            "bankUsername" => str_replace(" ","",$params['accountname']),
        ];
        $data['md5'] = $this->getSign($md);
        return $this->curlPost(self::$oderOut, $data);
    }




    /**
     * 获取签名
     */
    public function getSign($data) {
        // ksort($data);
        $strA = '';
        foreach ($data as $key => $val) {
            $strA .= "$key:$val";
        }
        return md5($strA);
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
            'Content-type: application/json'
            // 'X-SN: '. $this->authorizationKey,
            // 'X-SECRET: '. $this->password
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//请求数据
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        return $result;
    }


}
