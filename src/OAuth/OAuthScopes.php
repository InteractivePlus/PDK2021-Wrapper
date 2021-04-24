<?php
namespace InteractivePlus\PDK2021\OAuth;

use InteractivePlus\LibI18N\MultiLangValueProvider;

class OAuthScopes{
    private static ?OAuthScope $SCOPE_BASIC_INFO = null;
    public static function SCOPE_BASIC_INFO() : OAuthScope{
        if(self::$SCOPE_BASIC_INFO === null){
            self::$SCOPE_BASIC_INFO = new OAuthScope(
                'info',
                new MultiLangValueProvider('User Info',array('zh'=>'用户信息','en'=>'User Info')),
                new MultiLangValueProvider('Granting this scope allows the APP to read your Mask\'s display name and preferences',array('zh'=>'授予此项权限会让APP有权获取您面具的展示名和偏好设置','en'=>'Granting this scope allows the APP to read your Mask\'s display name and preferences'))
            );
        }
        return self::$SCOPE_BASIC_INFO;
    }
    private static ?OAuthScope $SCOPE_SEND_NOTIFICATIONS = null;
    public static function SCOPE_SEND_NOTIFICATIONS() : OAuthScope{
        if(self::$SCOPE_SEND_NOTIFICATIONS === null){
            self::$SCOPE_SEND_NOTIFICATIONS = new OAuthScope(
                'notifications',
                new MultiLangValueProvider('Send Notifications',array('zh'=>'发送提醒','en'=>'Send Notifications')),
                new MultiLangValueProvider('Granting this scope allows the APP to send you notifications by Email / SMS / Phone Calls through our channel',array('zh'=>'授予此项权限会让APP有权给您通过邮件, 手机短信和电话发送提醒消息','en'=>'Granting this scope allows the APP to send you notifications by Email / SMS / Phone Calls through our channel'))
            );
        }
        return self::$SCOPE_SEND_NOTIFICATIONS;
    }
    private static ?OAuthScope $SCOPE_SEND_SALES = null;
    public static function SCOPE_SEND_SALES() : OAuthScope{
        if(self::$SCOPE_SEND_SALES === null){
            self::$SCOPE_SEND_SALES = new OAuthScope(
                'contact_sales',
                new MultiLangValueProvider('Send Sale Messages',array('zh'=>'发送促销提醒','en'=>'Send Sale Messages')),
                new MultiLangValueProvider('Granting this scope allows the APP to send you sale and event informations by Email / SMS / Phone Calls through our channel',array('zh'=>'授予此项权限会让APP有权给您通过邮件, 手机短信和电话发送销售和活动信息','en'=>'Granting this scope allows the APP to send you sale and event informations by Email / SMS / Phone Calls through our channel'))
            );
        }
        return self::$SCOPE_SEND_SALES;
    }
    
    public static function isValidScope(string $scopeValue) : bool{
        switch(strtolower($scopeValue)){
            case self::SCOPE_BASIC_INFO()->getScopeName():
            case self::SCOPE_SEND_NOTIFICATIONS()->getScopeName():
            case self::SCOPE_SEND_SALES()->getScopeName():
                return true;
            default:
                return false;
        }
    }
    public static function getScopeObject(string $scopeValue) : ?OAuthScope{
        switch(strtolower($scopeValue)){
            case self::SCOPE_BASIC_INFO()->getScopeName():
                return self::SCOPE_BASIC_INFO();
            case self::SCOPE_SEND_NOTIFICATIONS()->getScopeName():
                return self::SCOPE_SEND_NOTIFICATIONS();
            case self::SCOPE_SEND_SALES()->getScopeName():
                return self::SCOPE_SEND_SALES();
            default:
                return null;
        }
    }
}