<?php
namespace InteractivePlus\PDK2021\InputUtils;

use InteractivePlus\PDK2021Core\User\Setting\SettingBoolean;
use InteractivePlus\PDK2021Core\User\Setting\UserSetting;

class UserSettingInputUtil{
    public static function parseSettingArray(array $settingArray) : UserSetting{
        /*
        return array(
            'allowEmailNotifications' => $userSetting->allowNotificationEmails(),
            'allowSaleEmail' => $userSetting->allowSaleEmails(),
            'allowSMSNotifications' => $userSetting->allowNotificationSMS(),
            'allowSaleSMS' => $userSetting->allowSaleSMS(),
            'allowCallNotifications' => $userSetting->allowNotificationCall(),
            'allowSaleCall' => $userSetting->allowSaleCall()
        );
        */
        $returnValue = new UserSetting(
            SettingBoolean::INHERIT,
            SettingBoolean::INHERIT,
            SettingBoolean::INHERIT,
            SettingBoolean::INHERIT,
            SettingBoolean::INHERIT,
            SettingBoolean::INHERIT
        );
        foreach($settingArray as $sKey => $sVal){
            $setVal = SettingBoolean::fixSetting($sVal);
            switch($sKey){
                case 'allowEmailNotifications':
                    $returnValue->setAllowNotificationEmails($setVal);
                    break;
                case 'allowSaleEmail':
                    $returnValue->setAllowSaleEmails($setVal);
                    break;
                case 'allowSMSNotifications':
                    $returnValue->setAllowNotificationSMS($setVal);
                    break;
                case 'allowSaleSMS':
                    $returnValue->setAllowSaleSMS($setVal);
                    break;
                case 'allowCallNotifications':
                    $returnValue->setAllowNotificationCall($setVal);
                    break;
                case 'allowSaleCall':
                    $returnValue->setAllowSaleCall($setVal);
                    break;
            }
        }
        return $returnValue;
    }
}