<?php
namespace app\libs\onePay;

class Tool
{
	//XXX替换对应的国家对应的地址
    const HOST_URL = 'https://api-pkr.onepay.news'; //网关地址切换正式环境不需要替换
    const PAY_CODE = 'BDT904';    //订单中的支付编码

	public $method = 'AES-128-CBC'; //AES加密定义不要更改
		
	//以下3个参数需要开启正式商户号后替换.
    public $password = '3E12i2YJXff0K4o1'; //AES密钥q
	public $authorizationKey = '50846K879D';  //请求头中的商户Key
    //
    //推送入款单
    static public $oderReceive = self::HOST_URL . '/api/v1/order/receive';
    //推送出款单
    static public $oderOut = self::HOST_URL . '/api/v1/order/out';
    //订单查询
    static public $oderQuery = self::HOST_URL . '/api/v1/order/query';
    //商户余额查询
    static public $balanceQuery = self::HOST_URL . '/api/v1/merchant/balance';
    //自助回调
    static public $orderNotify = self::HOST_URL .'/api/v1/test/orderNotify';




    /**
     * 代收
     */
     
    public function send_pay($params) {
        $data = [
            // 'merchant_id' => $this->authorizationKey,
            'orderNo' => $params['order_no'],  //订单号
            'payCode' => self::PAY_CODE,
            'amount' => $params['amount']*100, //金额
            // 'submit_time' => date('Y-m-d H:i:s'),
            // 'bank_code' => $params['pay_code'],
            'notifyUrl' => getInfo('domain_api')."/index/index/one_pay_callback",
            'returnUrl' => getInfo('domain').'/#/recharge/record',
            
        ];
        // dump($data);exit;
        // $data['sign'] = $this->getSign($data);
        return $this->postRes(self::$oderReceive, $data);
    }

    /**
     * 代付
     */

    public function sends_pay_out($params) {
        $data = [
            'orderNo' => $params['order_no'],
            'payCode' => self::PAY_CODE,
            'amount' => $params['money']*100,
            'notifyUrl' => getInfo('domain_api')."/index/index/one_pay_callback",
            'payeeType' => $params['bankname'], // 银行名称
            'payeeName' => str_replace(" ","",$params['accountname']), // 收款人姓名
            'payeeFirstInfo' => $params['cardnumber'], // 银行卡号
            
            
        ];
        if($params['bankname'] == 0){
            $data['payeeSecondInfo'] = 'bkash';
        }else{
            $data['payeeSecondInfo'] = 'nagad';
        }
        
        $curr_date = date('Y-m-d H:i:s');
        $res = $data['orderNo'].' + '.$data['payeeType'].' + '.$data['payeeName'].' + '.$data['payeeFirstInfo'].' + '.$data['amount'];
        file_put_contents('../pay_out_list.log', "【".$curr_date."】:".($res).PHP_EOL,FILE_APPEND);
        // $res = json_decode($str, true);
        // $res = json_decode($str, true);

        return $this->postRes(self::$oderOut, $data);
    }



    /**加密
     * @param array $data
     * @return string
     */
    public function encryptionAes(array $data)
    {
//        $jsonData = json_encode($data,true);
        //修改
        $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE );
        $aesSecret = bin2hex(openssl_encrypt($jsonData, $this->method,$this->password,  OPENSSL_RAW_DATA, $this->password));
        return $aesSecret;
    }

    /**解密
     * @param $aesSecret
     * @return false|string
     */
    public function decryptAes($aesSecret)
    {
        $str="";
        for($i=0;$i<strlen($aesSecret)-1;$i+=2){
            $str.=chr(hexdec($aesSecret[$i].$aesSecret[$i+1]));
        }
        $jsonData =  openssl_decrypt($str,$this->method,$this->password, OPENSSL_RAW_DATA,$this->password);
        $data = json_decode($jsonData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return $data;
    }


    /**获取随机数
     * @param int $length 随机数长度
     * @return string 返回随机数
     */
    public function GetRandStr($length = 8)
    {
        $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len=strlen($str)-1;
        $randStr='';
        for($i=0;$i<$length;$i++){
            $num=mt_rand(0,$len);
            $randStr .= $str[$num];
        }
        return $randStr;
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
            'Authorization: '. $this->authorizationKey,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//请求数据
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        return $result;
    }

    /**get请求
     * @param string $url
     * @param array $data
     * @return false|string
     */
    public function curlGet($url = '', $data = array())
    {
        if(!empty($data)) $url = $url .'?'. http_build_query($data);
        $ch = curl_init();//初始化
        curl_setopt($ch, CURLOPT_URL, $url);//访问的URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//只获取页面内容，但不输出
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//https请求 不验证证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//https请求 不验证HOST
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        return $result;
    }

    /**post加密数据并提交请求
     * @param string $url 请求地址
     * @param array $data   请求数据
     * @return false|string     返回数据（json字符串）
     */
    public function postRes($url, array $data)
    {
        $info['data'] = $this->encryptionAes($data);
        // dump($info);exit;
        $res = $this->curlPost($url, $info);
        return $res;
    }

    /**解密并解析数据为数组；
     * @param string $data 示例:{"data":"50C7CC8B58CEFFD1A824ADE524F4F55DB0DAEE6029ADBB597C1F99D28E1D0779C33B562526F05C6821932DE20B6893ADD6834D3397B7A8E08CC03995A5CDEA7E6B4DF0485466D4C25AEB223DD456DBC0321921FDCA18F9596A1C14B54C5A018CC7C0B922E3DE371626887DA78E539DA81E64EC41938BC3EC5BEBC26A948803E8","merchantNo":"2022011116544301686"}
     * @return false|array
     */
    public function parseData( $data )
    {
        $info = json_decode($data,true );
        $val = $this->decryptAes($info['data']);
        return $val;
    }



}