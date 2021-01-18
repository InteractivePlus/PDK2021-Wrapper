<?php
namespace InteractivePlus\PDK2021;

use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Interfaces\VeriCodeSMSAndCallContentGenerator;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class SMSContentProvider implements VeriCodeSMSAndCallContentGenerator{
    public function getContentForPhoneVerification(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在验证手机, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在进行重要操作, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForChangePassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在更改密码, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForForgetPassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在进行密码重设(忘记密码), 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForChangeEmail(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newEmail) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在更改密保邮箱为{$newEmail}, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForChangePhone(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newPhone) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在更改密保手机为{$newPhone}, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForAdminAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在进行管理员权限操作, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForThirdAPPImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在进行第三方APP操作, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
    public function getContentForThirdAPPDeleteAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $phoneVeriCode = $veriCodeEntity->getVeriCodePartialPhoneCode();
        $message = "亲爱的{$userNick}, 您正在删除第三方APP, 验证码为：{$phoneVeriCode}";
        return $message; 
    }
}