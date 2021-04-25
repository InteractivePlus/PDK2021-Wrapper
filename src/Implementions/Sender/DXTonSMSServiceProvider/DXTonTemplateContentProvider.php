<?php
namespace InteractivePlus\PDK2021\Implementions\Sender\DXTonSMSServiceProvider;

use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;
use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;
use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Interfaces\VeriCodeSMSAndCallContentGenerator;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;
use InteractivePlus\LibI18N\Locale as LibI18NLocale;

class DXTonTemplateContentProvider implements VeriCodeSMSAndCallContentGenerator{
    public function getContentForPhoneVerification(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForChangePassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForForgetPassword(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForChangeEmail(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newEmail, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForChangePhone(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, string $newPhone, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForAdminAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity); 
    }
    public function getContentForThirdAPPImportantAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForThirdAPPDeleteAction(VeriCodeEntity $veriCodeEntity, UserEntity $relatedUser, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN) : string{
        return self::getDXTonCommonTemplateContent($veriCodeEntity);
    }
    public function getContentForThirdAPPNotification(UserEntity $relatedUser, MaskIDEntity $relatedMaskID, APPEntity $relatedAPP, APPTokenEntity $relatedAPPToken, string $notificationTitle, string $notificationContent, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN): string
    {
        //65/Msg, Use 65*2 Char
        $APPDisplayName = strlen($relatedAPP->getDisplayName()) > 7 ? substr($relatedAPP->getDisplayName(),0,7) : $relatedAPP->getDisplayName();
        $servicePhoneNumber = '无，请登录官网修改' . $APPDisplayName . '设置';
        $itemName = '第三方APP提醒';
        $ticketNumber = substr($relatedAPPToken->getAccessToken(),0,5);
        $state = strlen($notificationContent) > 70 ? substr($notificationContent,0,70) : $notificationContent;
        return '您的' . $itemName . '，单号：'. $ticketNumber .'，状态：' . $state . '。客服电话' . $servicePhoneNumber . '，如需帮助请联系！';
    }
    public function getContentForThirdAPPSaleMsg(UserEntity $relatedUser, MaskIDEntity $relatedMaskID, APPEntity $relatedAPP, APPTokenEntity $relatedAPPToken, string $notificationTitle, string $notificationContent, ?string $locale = LibI18NLocale::LOCALE_zh_Hans_CN): string
    {
        //65/Msg, Use 65*2 Char
        $APPDisplayName = strlen($relatedAPP->getDisplayName()) > 7 ? substr($relatedAPP->getDisplayName(),0,7) : $relatedAPP->getDisplayName();
        $servicePhoneNumber = '无，请登录官网修改' . $APPDisplayName . '设置';
        $itemName = '第三方APP营销提醒';
        $ticketNumber = substr($relatedAPPToken->getAccessToken(),0,5);
        $state = strlen($notificationContent) > 70 ? substr($notificationContent,0,70) : $notificationContent;
        return '您的' . $itemName . '，单号：'. $ticketNumber .'，状态：' . $state . '。客服电话' . $servicePhoneNumber . '，如需帮助请联系！';
    }
    public static function getDXTonCommonTemplateContent(VeriCodeEntity $veriCodeEntity) : string{
        $veriCodeStr = $veriCodeEntity->getVeriCodePartialPhoneCode();
        return "您的验证码是：{$veriCodeStr}。请不要把验证码泄露给其他人。如非本人操作，可不用理会！";
    }
}