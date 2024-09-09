<?php
namespace app\libs\baoxue;

class baoxuepay {

    //XXX替换对应的国家对应的地址
    const HOST_URL = 'http://api.blizzardpay.pw'; //网关地址切换正式环境不需要替换

		
	//以下3个参数需要开启正式商户号后替换.
    public $password = ''; //商户秘钥
	public $authorizationKey = '';  //商户号
    //
    //创建收银台订单
    static public $oderReceive = self::HOST_URL . '/order/v2/create';
    //创建代付订单
    static public $oderOut = self::HOST_URL . '/dfpay/add';
    //代付订单查询
    static public $oderQuery = self::HOST_URL . '/dfpay/query';

    /**
     * 发起订单
     */
    public function send_pay($params) {
        $data = [
            'appId' => $this->authorizationKey,
            'outTradeNo' => $params['order_no'],
            // 'submit_time' => date('Y-m-d H:i:s'),
            'amount' => $params['amount'],
            'channelId' => $params['pay_code'],
            'bankCardNo'=>'123',
            'userName'=>'123',
            'userPhone'=>'123',
            'callbackUrl' => getInfo('domain_api')."/index/index/baoxue_pay_callback",
            'successUrl' => getInfo('domain').'/#/recharge/record',
            
        ];
        $data['sign'] = $this->getSign($data);
        // $data['callbackUrl'] = getInfo('domain_api')."/index/index/ff_pay_callback";
        // $data['successUrl'] = getInfo('domain').'/#/recharge/record';
        // print_r($data);
        return $this->curlPost(self::$oderReceive, $data);
    }

    /**
     * 发起代付订单
     */
    public function sends_pay_out($params) {
        $data = [
            'appId' => $this->authorizationKey,
            'outOrderNo' => $params['order_no'],
            'amount' => $params['money'],
            'bankname' => $params['bankname'], // 银行名称
            'bankUserName' => str_replace(" ","",$params['accountname']), // 收款人姓名
            'bankCard' => $params['cardnumber'], // 银行卡号
            'currency' => 'IDR', // 货币类型
            
        ];
        $data['sign'] = $this->getSign($data);
        
        // $data['logs']=$ress;
        
        $data['callbackUrl'] = getInfo('domain_api')."/index/index/ff_out_pay_callback";
        
        // $res = $tool->parseData($str);  //解析数据结果为数组.
        $curr_date = date('Y-m-d H:i:s');
        $res = $data['appId'].' + '.$data['outOrderNo'].' + '.$data['bankname'].' + '.$data['bankUserName'].' + '.$data['bankCard'].' + '.$data['amount'].' + '.$data['sign'];
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
        $str = $newstr."&key=".$this->password;
        // print_r($str);
        return md5($str);
    }

    /**post请求
     * @param string $url
     * @param array $data
     * @return false|string
     */
    public function curlPost($url = '', $data=null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        // echo json_encode($data);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}