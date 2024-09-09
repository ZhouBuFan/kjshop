<?php
require_once "Tool.php";

###########推送入款单
$data['orderNo'] = '2022011116544301676';
$data['payCode'] = Tool::PAY_CODE;
$data['amount'] = 1000; //金额是到分,平台金额是元需要除100
$data['notifyUrl'] = "http://www.alpaca.vip/test.php";
$data['returnUrl'] = 'http://www.baidu.com';
//以下参数自行修改
$data['payerName'] = '张三';

$tool = new Tool();
//$res = $tool->postRes(Tool::$oderReceive, $data);
//var_dump($res);die;

###########推送出款单
$out['orderNo'] = '2021011116544302027';
$out['payCode'] = Tool::PAY_CODE;
$out['amount'] = 1000; //金额是到分,平台金额是元需要除100
$out['notifyUrl'] = 'http://www.alpaca.vip/test.php';
//以下参数自行修改
$out['payeeType'] = '0';
$out['payeeName'] = '张三';
$out['payeeFirstInfo'] = '4566287863221';

//$res = $tool->postRes(Tool::$oderOut, $out);
//var_dump($res);die;

###########订单查询
$queryData['orderType'] = 1;
$queryData['orderNo'] = '2022011116544301950';
$queryData['nonce'] = $tool->GetRandStr();

//$res = $tool->postRes(Tool::$oderQuery, $queryData);
//var_dump($res);die;

###########商户余额
$balanceData['nonce'] = $tool->GetRandStr();
//$res = $tool->postRes(Tool::$balanceQuery, $queryData);
//var_dump($res);die;


###########自助回调
$dataBack['orderType'] = '1';
$dataBack['orderNo'] = 'SKD2022051115295203806';    /*系统订单号，非商户订单号*/
$dataBack['status'] = '2';
//$res = $tool->curlGet(Tool::$orderNotify, $dataBack);
//var_dump($res);die;


###########异步通知（接口解析）
//接收数据示例:{"data":"50C7CC8B58CEFFD1A824ADE524F4F55DB0DAEE6029ADBB597C1F99D28E1D0779C33B562526F05C6821932DE20B6893ADD6834D3397B7A8E08CC03995A5CDEA7E6B4DF0485466D4C25AEB223DD456DBC0321921FDCA18F9596A1C14B54C5A018CC7C0B922E3DE371626887DA78E539DA81E64EC41938BC3EC5BEBC26A948803E8","merchantNo":"2022011116544301686"}
$str = file_get_contents("php://input");   //获取post数据
$res = $tool->parseData($str);  //解析数据结果为数组。
