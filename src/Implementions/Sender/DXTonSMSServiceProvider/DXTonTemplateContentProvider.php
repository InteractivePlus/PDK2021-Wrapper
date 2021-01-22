<?php
namespace InteractivePlus\PDK2021\Implementions\Sender\DXTonSMSServiceProvider;

use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Interfaces\VeriCodeSMSAndCallContentGenerator;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class DXTonTemplateContentProvider implements VeriCodeSMSAndCallContentGenerator{
    public function getContentForPhoneVerification(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForChangePassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForForgetPassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForChangeEmail(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newEmail) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForChangePhone(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newPhone) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForAdminAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity); 
    }
    public function getContentForThirdAPPImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForThirdAPPDeleteAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public static function getDXTonCommonTemplateContent(VeriCodeEntity $veriCodeEntity) : string{
        $veriCodeStr = $veriCodeEntity->getVeriCodePartialPhoneCode();
        return "您的验证码是：{$veriCodeStr}。请不要把验证码泄露给其他人。如非本人操作，可不用理会！";
    }
}