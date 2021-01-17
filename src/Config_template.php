<?php
namespace InteractivePlus\PDK2021;
use InteractivePlus\PDK2021Core\User\UserSystemFormatSetting;
use InteractivePlus\PDK2021Core\User\UserSystemFormatSettingImpl;

class Config{
    const MYSQL_HOST = '';
    const MYSQL_PORT = 3306;
    const MYSQL_USERNAME = '';
    const MYSQL_PASSWORD = '';
    const MYSQL_DATABASE = '';
    const MYSQL_CHARSET = 'utf8';
    const SENDGRID_APIKEY = '';
    const USER_SYSTEM_CONSTRAINTS = new UserSystemFormatSettingImpl(
        0,
        10,
        0,
        10,
        0,
        20,
        0,
        20,
        0,
        40,
        null
    );
}