<?php
namespace InteractivePlus\PDK2021\Implementions\Sender\DXTonSMSServiceProvider;

use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SMSServiceProvider;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use libphonenumber\PhoneNumber;

class DXTonServiceProvider implements SMSServiceProvider{
    private string $_encoding;
    public string $account;
    public string $apiSecret;
    public bool $allowIntlPhoneNum;

    public function __construct(string $account, string $apiSecret, string $encoding = 'utf8', bool $allowIntlPhoneNum = false){
        $this->account = $account;
        $this->apiSecret = $apiSecret;
        $this->allowIntlPhoneNum = $allowIntlPhoneNum;
        $this->_encoding = $encoding;
    }

    public function getEncoding() : string{
        return $this->_encoding;
    }
    public function setEncoding(string $encoding) : void{
        $encoding = strtolower($encoding);
        if($encoding === 'gbk' || $encoding === 'gb2312' || $encoding === 'gb-2312'){
            $this->_encoding = 'gbk';
        }else{
            $this->_encoding = 'utf8';
        }
    }
    public static function Post($curlPost,$url) : ?string{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
		$return_str = curl_exec($curl);
        curl_close($curl);
        if($return_str === false){
            return null;
        }
		return $return_str;
    }

    public function sendSMS(PhoneNumber $numberToReceive, string $content, bool $enableSMSSplit) : bool{
        $nationalNum = $numberToReceive->getNationalNumber();
        $isIntlNumber = true;
        if($numberToReceive->getCountryCode() === 86 || ($numberToReceive->getCountryCode() === null && !empty($nationalNum) && str_starts_with($nationalNum,'1'))){
            $isIntlNumber = false;
        }
        if($isIntlNumber && !$this->allowIntlPhoneNum){
            return false;
        }
        $target = '';
        $post_data = '';
        if(!$isIntlNumber){
            $target = 'http://sms.106jiekou.com/' . $this->_encoding . '/sms.aspx';
            $post_data = 'account=' . $this->account . '&password=' . $this->apiSecret . '&mobile=' . $nationalNum . '&content='. rawurlencode($content);
        }else{
            $intlNumber = UserPhoneUtil::outputPhoneNumberE164($numberToReceive);
            if(str_starts_with($intlNumber,'+')){
                $intlNumber = substr($intlNumber,1,strlen($intlNumber) - 1);
            }
            $target = 'http://sms.106jiekou.com/' . $this->_encoding . '/worldapi.aspx';
            $post_data = 'account=' . $this->account . '&password=' . $this->apiSecret . '&mobile=' . $intlNumber . '&content=' . rawurlencode($content);
        }
        $postResult = self::Post($post_data,$target);
        if($postResult === null){
            return false;
        }
        /*
        状态码		说明
        100			发送成功
        101			验证失败
        102			手机号码格式不正确
        103			会员级别不够
        104			内容未审核
        105			内容过多
        106			账户余额不足
        107			Ip受限
        108			手机号码发送太频繁，请换号或隔天再发
        109			帐号被锁定
        110			手机号发送频率持续过高，黑名单屏蔽数日
        120			系统升级
        */
        if($postResult === '100'){
            return true;
        }else{
            return false;
        }
    }

}