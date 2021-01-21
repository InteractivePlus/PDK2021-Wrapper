<?php
namespace InteractivePlus\PDK2021;


use InteractivePlus\PDK2021\Implementions\Sender\AliyunServiceProvider;
use InteractivePlus\PDK2021\Implementions\Sender\LocalFileSMSServiceProvider;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\LoggerStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\SimpleCaptchaStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\TokenEntityStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\UserEntityStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\VeriCodeStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\TemplateProvider\WrapperEmailContent as TemplateProviderWrapperEmailContent;
use InteractivePlus\PDK2021Core\Captcha\Implemention\PDKSimpleCaptchaSystemImpl;
use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Implementions\EmailTemplateContent\TemplateEmailContentGenerator;
use InteractivePlus\PDK2021Core\Communication\VeriSender\Implementions\VeriCodeEmailSenderImplWithProvider;
use InteractivePlus\PDK2021Core\Communication\VeriSender\Implementions\VeriCodeSMSSenderImplWithService;
use InteractivePlus\PDK2021Core\PDKCore;

use MysqliDb;

class PDK2021Wrapper{
    public static ?PDKCore $pdkCore = null;
    public static ?Config $config = null;
    public static function initiatePDKCore() : void{
        $config = new Config();
        self::$config = $config;
        $mySQLConn = new MysqliDb($config->MYSQL_HOST,$config->MYSQL_USERNAME,$config->MYSQL_PASSWORD,$config->MYSQL_DATABASE,$config->MYSQL_PORT,$config->MYSQL_CHARSET);
        $mySQLConn->autoReconnect = true;
        $LoggerStorage = new LoggerStorageMySQLImpl($mySQLConn);
        $VeriCodeStorage = new VeriCodeStorageMySQLImpl($mySQLConn);
        $UserEntityStorage = new UserEntityStorageMySQLImpl($mySQLConn,$config->USER_SYSTEM_CONSTRAINTS);
        $TokenEntityStorage = new TokenEntityStorageMySQLImpl($mySQLConn);
        $EmailSender = new VeriCodeEmailSenderImplWithProvider(
            new AliyunServiceProvider($config->ALIYUN_ACCESS_KEY_ID,$config->ALIYUN_ACCESS_KEY_SECRET),
            new TemplateEmailContentGenerator(new TemplateProviderWrapperEmailContent(),new LinkProvider(),'InteractivePDK'),
            $config->ALIYUN_FROM_NAME,
            $config->ALIYUN_FROM_ADDR
        );
        $SMSSender = new VeriCodeSMSSenderImplWithService(
            new LocalFileSMSServiceProvider(__DIR__ . '/../LocalFileServiceProvider/SMS.json'),
            new SMSContentProvider,
            ' 【形随意动用户系统团队】'
        );

        $captchaSystem = new PDKSimpleCaptchaSystemImpl($config->CAPTCHA_AVAILABLE_DURATION,new SimpleCaptchaStorageMySQLImpl($mySQLConn,$config->CAPTCHA_PHRASE_LEN));

        self::$pdkCore = new PDKCore(
            $LoggerStorage,
            $VeriCodeStorage,
            $EmailSender,
            $SMSSender,
            null,
            $UserEntityStorage,
            $TokenEntityStorage,
            $captchaSystem
        );
    }
    public static function installDB() : void{
        $config = new Config();
        self::$config = $config;
        $mySQLConn = new MysqliDb($config->MYSQL_HOST,$config->MYSQL_USERNAME,$config->MYSQL_PASSWORD,$config->MYSQL_DATABASE,$config->MYSQL_PORT,$config->MYSQL_CHARSET);
        $mySQLConn->autoReconnect = true;
        $LoggerStorage = new LoggerStorageMySQLImpl($mySQLConn);
        $VeriCodeStorage = new VeriCodeStorageMySQLImpl($mySQLConn);
        $UserEntityStorage = new UserEntityStorageMySQLImpl($mySQLConn,$config->USER_SYSTEM_CONSTRAINTS);
        $TokenEntityStorage = new TokenEntityStorageMySQLImpl($mySQLConn);
        $SimpleCaptchaStorage = new SimpleCaptchaStorageMySQLImpl($mySQLConn,$config->CAPTCHA_PHRASE_LEN);
        $LoggerStorage->createTables();
        $VeriCodeStorage->createTables();
        $UserEntityStorage->createTables();
        $TokenEntityStorage->createTables();
        $SimpleCaptchaStorage->createTables();
        $mySQLConn->disconnect();
    }
}