<?php
namespace app\libs\ffpay;

class Ff {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'https://ffpay8.com'; //网关地址切换正式环境不需要替换

		
	//以下3个参数需要开启正式商户号后替换.
    public $password = 'YwIMtFOIecvQ'; //商户秘钥
	public $authorizationKey = '91592463';  //商户号
    //
    //创建收银台订单
    static public $oderReceive = self::HOST_URL . '/pay/index';
    //创建代付订单
    static public $oderOut = self::HOST_URL . '/dfpay/add';
    //代付订单查询
    static public $oderQuery = self::HOST_URL . '/dfpay/query';

    /**
     * 发起订单
     */
    public function send_pay($params) {
        $data = [
            'merchant_id' => $this->authorizationKey,
            'order_no' => $params['order_no'],
            'submit_time' => date('Y-m-d H:i:s'),
            'bank_code' => $params['pay_code'],
            'notify_url' => getInfo('domain_api')."/index/index/ff_pay_callback",
            'return_url' => getInfo('domain').'/#/recharge/record',
            'amount' => $params['amount'],
        ];
        $data['sign'] = $this->getSign($data);
        return $this->curlPost(self::$oderReceive, $data);
    }

    /**
     * 发起代付订单
     */
    public function sends_pay_out($params) {
        $data = [
            'mchid' => $this->authorizationKey,
            'out_trade_no' => $params['order_no'],
            'bankname' => $params['bankname'], // 银行名称
            'accountname' => str_replace(" ","",$params['accountname']), // 收款人姓名
            'cardnumber' => $params['cardnumber'], // 银行卡号
            'money' => $params['money'],
        ];
        $data['pay_md5sign'] = $this->getSign($data);
        
        // $data['logs']=$ress;
        
        $data['notifyurl'] = getInfo('domain_api')."/index/index/ff_out_pay_callback";
        
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        $curr_date = date('Y-m-d H:i:s');
        $res = $data['mchid'].' + '.$data['out_trade_no'].' + '.$data['bankname'].' + '.$data['accountname'].' + '.$data['cardnumber'].' + '.$data['money'].' + '.$data['pay_md5sign'];
        file_put_contents('/www/wwwroot/web/hd/application/libs/ffpay/pay_out_list.log', "【".$curr_date."】:".($res).PHP_EOL,FILE_APPEND);
        // $res = json_decode($str, true);
        // $res = json_decode($str, true);

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
        $newstr = substr($strA,0,strlen($strA)-1); 
        $str = $this->password.$newstr.$this->password;
        return md5(md5($str));
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