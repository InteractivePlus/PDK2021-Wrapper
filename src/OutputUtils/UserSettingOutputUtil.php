<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021Core\User\Setting\UserSetting;

class UserSettingOutputUtil{
    public static function getUserSettingAsAssocArray(UserSetting $userSetting) : array{
        return array(
            'allowEmailNotifications' => $userSetting->allowNotificationEmails(),
            'allowSaleEmail' => $userSetting->allowSaleEmails(),
            'allowSMSNotifications' => $userSetting->allowNotificationSMS(),
            'allowSaleSMS' => $userSetting->allowSaleSMS(),
            'allowCallNotifications' => $userSetting->allowNotificationCall(),
            'allowSaleCall' => $userSetting->allowSaleCall()
        );
    }
}