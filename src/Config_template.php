<?php
namespace InteractivePlus\PDK2021;

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
    
    public UserSystemFormatSettingImpl $USER_SYSTEM_CONSTRAINTS;
    public bool $SLIM_CHECK_PROXY = false;
    public ?array $SLIM_PROXY_IPS = null;

    public int $VERICODE_AVAILABLE_DURATION = 1200;
    public int $TOKEN_AVAILABLE_DURATION = 3600 * 24 * 1;
    public int $REFRESH_TOKEN_AVAILABLE_DURATION = 3600 * 24 * 10;

    public bool $DEVELOPMENT_MODE = true;
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
    }
}