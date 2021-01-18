<?php
namespace InteractivePlus\PDK2021;

use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Interfaces\EmailContent;
use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Interfaces\VeriCodeEmailContentGenerator;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class EmailContentProvider implements VeriCodeEmailContentGenerator{
    public function getContentForEmailVerification(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">感谢您选择我们的服务,您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '验证您的邮箱地址';
        return new EmailContent($title,$content);
    }
    public function getContentForImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在进行重要操作,您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '重要操作验证';
        return new EmailContent($title,$content);
    }
    public function getContentForChangePassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在更改您的密码,您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '修改密码验证';
        return new EmailContent($title,$content);
    }
    public function getContentForForgetPassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在进行密码重设(忘记密码),您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '密码重设验证';
        return new EmailContent($title,$content);
    }
    public function getContentForChangeEmail(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newEmail) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在修改您的密保邮箱,修改后邮箱变更为{$newEmail},您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '修改邮箱验证';
        return new EmailContent($title,$content);
    }
    public function getContentForChangePhone(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newPhone) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在修改密保手机,修改后手机变更为{$newPhone},您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '修改手机验证';
        return new EmailContent($title,$content);
    }
    public function getContentForAdminAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在进行管理员权限操作,您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '管理员权限操作验证';
        return new EmailContent($title,$content);
    }
    public function getContentForThirdAPPImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在对账户内的第三方APP进行重要操作,您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '第三方APP重要操作验证';
        return new EmailContent($title,$content);
    }
    public function getContentForThirdAPPDeleteAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : EmailContent{
        $userNick = empty($relatedUser->getNickName()) ? $relatedUser->getUsername() : $relatedUser->getNickName();
        $content = "<!DOCTYPE html>
        <html>
        <body>
            <p style=\"text-align:left\">亲爱的{$userNick},</p>
            <p style=\"text-align:center\">您正在对账户内的第三方APP进行删除操作,您的验证码为:</p>
            <p style=\"text-align:center;font-size:2.5em\">{$veriCodeEntity->getVeriCodeString()}</p>
            <p style=\"text-align:right\">此致, </p>
            <p style=\"text-align:right\">形随意动用户系统团队</p>
        </body>
        </html>
        ";
        $title = '第三方APP删除验证';
        return new EmailContent($title,$content);
    }
}