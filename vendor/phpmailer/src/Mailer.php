<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once 'PHPMailer.php';
require_once 'SMTP.php';

class Mailer
{
    /**
     * Mailer constructor.
     * @param bool $debug [调试模式]
     */
    public function __construct($debug = false)
    {
        $this->mailer = new PHPMailer();
        $this->mailer->SMTPDebug = 0;
        $this->mailer->isSMTP(); // 使用 SMTP 方式发送邮件
    }

    /**
     * @return PHPMailer
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    private function loadConfig($host,$post,$smtp,$charset,$username,$password,$nickname)
    {
        /* Server Settings  */
        $this->mailer->SMTPAuth = true; // 开启 SMTP 认证
        $this->mailer->Host = $host; // SMTP 服务器地址
        $this->mailer->Port = $post; // 远程服务器端口号
        $this->mailer->SMTPSecure = $smtp; // 登录认证方式
        /* Account Settings */
        $this->mailer->Username = $username; // SMTP 登录账号
        $this->mailer->Password = $password; // SMTP 登录密码
        $this->mailer->From = $username; // 发件人邮箱地址
        $this->mailer->FromName = $nickname; // 发件人昵称（任意内容）
        /* Content Setting  */
        $this->mailer->isHTML(true); // 邮件正文是否为 HTML
        $this->mailer->CharSet = $charset; // 发送的邮件的编码
    }

    /**
     * Add attachment
     * @param $path [附件路径]
     */
    public function addFile($path)
    {
        $this->mailer->addAttachment($path);
    }


    /**
     * Send Email
     * @param $email [收件人]
     * @param $title [主题]
     * @param $content [正文]
     * @return bool [发送状态]
     */
    public function send($email, $title, $content,$host,$post,$smtp,$charset,$username,$password,$nickname)
    {
        $this->loadConfig($host,$post,$smtp,$charset,$username,$password,$nickname);
        $this->mailer->addAddress($email); // 收件人邮箱
        $this->mailer->Subject = $title; // 邮件主题
        $this->mailer->Body = $content; // 邮件信息
        return (bool)$this->mailer->send(); // 发送邮件
    }
}