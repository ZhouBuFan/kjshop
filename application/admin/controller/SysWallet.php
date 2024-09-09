<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller;

use library\Controller;
use think\Db;
use Endroid\QrCode\QrCode;

/**
 * 系统钱包管理
 * Class Item
 * @package app\admin\controller
 */
class SysWallet extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcSysWallet';

    /**
     * 系统钱包管理
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = '系统钱包管理';
        $query = $this->_query($this->table)->like('name');
        $query->order('id asc')->page();
    }

    /**
     * 添加系统钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加系统钱包';
        $json= json_decode(createAddress(), true);
        if($json['code']!=200){
            $this->error($json['message']);
        }
        
        $qrCode = new QrCode();
        $qrCode->setText($json['data']['address']);
        $qrCode->setSize(300);
        $walletQrCode = $qrCode->getDataUri();
        
        $merchant = Db::name('LcMerchant')->find(1);
        
        
        $data = array('mid' => 1 ,'address' => $json['data']['address'] ,'qrcode' => $walletQrCode ,'coin_type' => $json['data']['coinType'] ,'call_url' => $merchant['call_url']);
        
        Db::name('LcSysWallet')->insert($data);
        
        sysoplog('系统管理', '创建系统钱包');
        
        $this->success('创建成功');
    }

    /**
     * 编辑系统钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    // public function edit()
    // {
    //     $this->title = '编辑系统钱包';
    //     $this->_form($this->table, 'form');
    // }

    /**
     * 删除系统钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        
        sysoplog('系统管理', '删除系统钱包');
        
        $this->_delete($this->table);
    }

}
