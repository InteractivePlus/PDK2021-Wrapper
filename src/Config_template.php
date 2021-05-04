<?php
namespace InteractivePlus\PDK2021;

use InteractivePlus\PDK2021Core\APP\APPSystemFormatSetting;
use InteractivePlus\PDK2021Core\APP\APPSystemFormatSettingImpl;
use InteractivePlus\PDK2021Core\EXT_Ticket\OAuthTicketFormatSettingImpl;
use InteractivePlus\PDK2021Core\User\Setting\SettingBoolean;
use InteractivePlus\PDK2021Core\User\Setting\UserSetting;
use InteractivePlus\PDK2021Core\User\UserSystemFormatSettingImpl;

class Config{
    public string $MYSQL_HOST = '';
    public int $MYSQL_PORT = 3306;
    public string $MYSQL_USERNAME = '';
    public string $MYSQL_PASSWORD = '';
    public string $MYSQL_DATABASE = '';
    public string $MYSQL_CHARSET = 'utf8';
    
    public string $ALIYUN_ACCESS_KEY_ID = '';
    public string $ALIYUN_ACCESS_KEY_SECRET = '';
    public string $ALIYUN_FROM_ADDR = '';
    public string $ALIYUN_FROM_NAME = '';

    public string $DXTON_USERNAME = '';
    public string $DXTON_APISecret = '';
    
    public UserSystemFormatSettingImpl $USER_SYSTEM_CONSTRAINTS;
    public UserSetting $USER_SYSTEM_DEFAULT_SETTINGS;

    public APPSystemFormatSetting $APP_SYSTEM_FORMAT_CONSTRAINTS;

    public bool $SLIM_CHECK_PROXY = false;
    public ?array $SLIM_PROXY_IPS = null;

    public int $OAUTH_AUTHCODE_AVAILABLE_DURATION = 60 * 10;
    public int $OAUTH_TOKEN_AVAILABLE_DURATION = 3600 * 24 * 15;
    public int $OAUTH_REFRESH_TOKEN_AVAILABLE_DURATION = 3600 * 24 * 30;
    public int $VERICODE_AVAILABLE_DURATION = 1200;
    public int $TOKEN_AVAILABLE_DURATION = 3600 * 24 * 15;
    public int $REFRESH_TOKEN_AVAILABLE_DURATION = 3600 * 24 * 30;
    
    public int $CAPTCHA_AVAILABLE_DURATION = 60 * 5;
    public int $CAPTCHA_PHRASE_LEN = 5;

    public bool $DEVELOPMENT_MODE = true;
    public array $FRONTEND_ROOT_URL_FOR_CORS = array(
        'http://pdk.xsyds.cn/',
        'https://user.xsyds.cn/',
        'http://pdk.interactiveplus.org/',
        'https://user.interactiveplus.org/'
    );

    public int $OAUTH_NOTIFICATION_TITLE_MAX_LEN = 20;
    public int $OAUTH_NOTIFICATION_CONTENT_MAX_LEN = 200;
    public int $OAUTH_SALE_TITLE_MAX_LEN = 20;
    public int $OAUTH_SALE_CONTENT_MAX_LEN = 500;

    public OAuthTicketFormatSettingImpl $OAUTH_TICKET_SYSTEM_FORMAT_CONSTRAINTS;
    public function __construct()
    {
        $this->USER_SYSTEM_CONSTRAINTS = new UserSystemFormatSettingImpl(
            0,
            20,
            0,
            25,
            0,
            40,
            0,
            30,
            0,
            60,
            null
        );
        $this->USER_SYSTEM_DEFAULT_SETTINGS = new UserSetting(
            SettingBoolean::SET_YES,
            SettingBoolean::SET_YES,
            SettingBoolean::SET_YES,
            SettingBoolean::SET_NO,
            SettingBoolean::SET_YES,
            SettingBoolean::SET_NO
        );
        $this->APP_SYSTEM_FORMAT_CONSTRAINTS = new APPSystemFormatSettingImpl(
            0,
            25
        );

        $this->OAUTH_TICKET_SYSTEM_FORMAT_CONSTRAINTS = new OAuthTicketFormatSettingImpl(
            0,
            20,
            0,
            1000,
            0,
            25
        );
    }
}