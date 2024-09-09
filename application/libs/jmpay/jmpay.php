<?php
namespace app\libs\jmpay;

class jmpay {

  //XXX替换对应的国家对应的地址
  const HOST_URL = 'https://api.jmpays.com'; //网关地址切换正式环境不需要替换

		
  //以下3个参数需要开启正式商户号后替换.
  public $shanghu = 'JM88048';
  public $shoukey = '5f7eddaf-7d303ade3b-d7fc391bb7-c725c43385bd-c25cb685cb78-1aafdb7de03518-62622e43-27063f9f-cf05cd05'; //收
  public $fukey = '0399371def8d-0f22213d19916a-e37814542425-879f5489919030-50521e74c7c03d-b5592e9c92ec-806e2e4e-7364c5d529bd-58673467e0581587-660647e05b8bc8';  //付
//  public $shanghu = 'JM88030';
//   public $shoukey = '808a6b54-5f0bfbd255-1f96b4ebff-743b3c656018-4fb5bef78fc0-7d44f20eb547f7-77dcccb7-b720ebec-6e488f05'; //收
//   public $fukey = '168eb6703803-d6b7d048ae654d-5be27ac58738-c69c72c7452e0f-7367e65510c61c-cf3c1e84d090-d3fc60c1-5b41dc4b1ac1-7da51fb79175b67e-aa8257b7765df5';  //付
  //
  //创建收银台订单
  static public $oderReceive = self::HOST_URL . '/api/v1/payment/unifiedOrder';
  //创建代付订单
  static public $oderOut = self::HOST_URL . '/api/v1/settle/transfer';



    /**
     * 代收
     */
     
     public function send_pay($params) {
         
         $data = [
            'mch_id' => $this->shanghu,
            'trace_no' => $params['order_no'],  //订单号
            'total_fee' => $params['amount']*100, //金额
            'currency' => 'bdt',
            'trade_type' => 'bdt_wallet_one',
            'notify_url' => getInfo('domain_api')."/index/index/jm_pay_callback",
            'return_url' => getInfo('domain').'/#/recharge/record',
            'trade_name' => 'test',
            // 'extra' => [
            //     'account'=>''
            // ]     
            // 'payCode' => self::PAY_CODE,
            // 'submit_time' => date('Y-m-d H:i:s'),
            // 'bank_code' => $params['pay_code'],
            
        ];
        $sgin = $this->calculateMd5Sign($data,$this->shoukey);

        
        $data['sign'] = $sgin;
        return $this->curlPost(self::$oderReceive, $data);
    }



    /**
     * 代付
     */

    public function sends_pay_out($params) {
        // if($params['bankname'] == 0){
        //     $data['payeeSecondInfo'] = 'bkash';
        // }else{
        //     $data['payeeSecondInfo'] = 'nagad';
        // }
        $data = [
            'mch_id' => $this->shanghu,
            'total_fee' => intval($params['money']*100),
            'notify_url' => getInfo('domain_api')."/index/index/jm_payout_callback",
            'currency' => 'bdt',
            'trade_type' => 'bdt_wallet_one',
            'trace_no' => $params['order_no'],
            'extra' => [
                'arrive_code'=>$params['bankname'],
                'receive_account'=>$params['cardnumber'],
            ]   
            // 'payeeType' => $params['bankname'], // 银行名称
            // 'payeeName' => str_replace(" ","",$params['accountname']), // 收款人姓名
            // 'payeeFirstInfo' => $params['cardnumber'], // 银行卡号
        ];
        $sgin = $this->calculateMd5Sign($data,$this->fukey);
        $data['sign'] = $sgin;
        $curr_date = date('Y-m-d H:i:s');
        $res = $data['trace_no'].' + '.$params['cardnumber'].' + '.$params['cardnumber'].' + '.$params['accountname'];
        file_put_contents('../pay_out_list.log', "【".$curr_date."】:".($res).PHP_EOL,FILE_APPEND);
        // $res = json_decode($str, true);
        // $res = json_decode($str, true);

        return $this->curlPost(self::$oderOut, $data);
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
            // 'Authorization: '. $this->authorizationKey,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//请求数据
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        return $result;
    }




  
    function calculateMd5Sign($params, $key) {
        ksort($params); // 对参数键进行排序


        $stringParams = '';
        foreach ($params as $k => $item) {
            if ($item !== null && $item !== 0) {
                if (is_array($item)) {
                    ksort($item); // 对数组键进行排序
                    $item = json_encode($item); // 转换为 JSON 字符串
                }
                $stringParams .= $k . '=' . $item . '&'; // 构建参数字符串
            }
        }


        $encodedString = $stringParams . "secret=" . $key; // 构建最终的编码字符串



        // 计算签名
        $md5Hash = md5($encodedString); // 计算 MD5 哈希
        $doubleMd5Hash = strtoupper(md5($md5Hash)); // 再次计算 MD5 哈希并转为大写
        return $doubleMd5Hash; // 返回双重 MD5 后转为大写的签名字符串
    }



}