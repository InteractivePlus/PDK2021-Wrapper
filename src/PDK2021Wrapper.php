<?php
namespace InteractivePlus\PDK2021;

use InteractivePlus\LibI18N\MultiLangValueProvider;
use InteractivePlus\PDK2021\Implementions\Sender\AliyunServiceProvider;
use InteractivePlus\PDK2021\Implementions\Sender\DXTonSMSServiceProvider\DXTonServiceProvider;
use InteractivePlus\PDK2021\Implementions\Sender\DXTonSMSServiceProvider\DXTonTemplateContentProvider;
use InteractivePlus\PDK2021\Implementions\Sender\LocalFileSMSServiceProvider;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\APPEntityStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\APPTokenStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\AuthCodeStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\EXTOAuthStorageRecordStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\EXTOAuthTicketRecordStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\LoggerStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\MaskIDStorageMySQLImpl;
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
        $UserEntityStorage = new UserEntityStorageMySQLImpl($mySQLConn,$config->USER_SYSTEM_CONSTRAINTS,$config->USER_SYSTEM_DEFAULT_SETTINGS);
        $TokenEntityStorage = new TokenEntityStorageMySQLImpl($mySQLConn);
        $APPEntityStorage = new APPEntityStorageMySQLImpl($mySQLConn,$config->APP_SYSTEM_FORMAT_CONSTRAINTS);
        $AuthCodeStorage = new AuthCodeStorageMySQLImpl($mySQLConn);
        $APPTokenStorage = new APPTokenStorageMySQLImpl($mySQLConn);
        $MaskIDStorage = new MaskIDStorageMySQLImpl($mySQLConn);
        $captchaSystem = new PDKSimpleCaptchaSystemImpl($config->CAPTCHA_AVAILABLE_DURATION,new SimpleCaptchaStorageMySQLImpl($mySQLConn,$config->CAPTCHA_PHRASE_LEN));
        $EXTOAuthStorageRecordStorage = new EXTOAuthStorageRecordStorageMySQLImpl($mySQLConn);
        $EXTTicketRecordStorage = new EXTOAuthTicketRecordStorageMySQLImpl($mySQLConn,$config->OAUTH_TICKET_SYSTEM_FORMAT_CONSTRAINTS);

        $EmailSender = new VeriCodeEmailSenderImplWithProvider(
            new AliyunServiceProvider($config->ALIYUN_ACCESS_KEY_ID,$config->ALIYUN_ACCESS_KEY_SECRET),
            new TemplateEmailContentGenerator(new TemplateProviderWrapperEmailContent(),new LinkProvider(),'InteractivePDK'),
            new MultiLangValueProvider($config->ALIYUN_FROM_NAME,array()),
            $config->ALIYUN_FROM_ADDR,
            true
        );
        $SMSSender = new VeriCodeSMSSenderImplWithService(
            new DXTonServiceProvider($config->DXTON_USERNAME,$config->DXTON_APISecret,'utf8',false),
            new DXTonTemplateContentProvider,
            null,
            false
        );

        self::$pdkCore = new PDKCore(
            $LoggerStorage,
            $VeriCodeStorage,
            $EmailSender,
            $SMSSender,
            null,
            $UserEntityStorage,
            $TokenEntityStorage,
            $captchaSystem,
            $APPEntityStorage,
            $APPTokenStorage,
            $AuthCodeStorage,
            $MaskIDStorage,
            $EXTOAuthStorageRecordStorage,
            $EXTTicketRecordStorage
        );
    }
    public static function installDB() : void{
        $config = new Config();
        self::$config = $config;
        $mySQLConn = new MysqliDb($config->MYSQL_HOST,$config->MYSQL_USERNAME,$config->MYSQL_PASSWORD,$config->MYSQL_DATABASE,$config->MYSQL_PORT,$config->MYSQL_CHARSET);
        $mySQLConn->autoReconnect = true;
        
        $LoggerStorage = new LoggerStorageMySQLImpl($mySQLConn);
        $VeriCodeStorage = new VeriCodeStorageMySQLImpl($mySQLConn);
        $UserEntityStorage = new UserEntityStorageMySQLImpl($mySQLConn,$config->USER_SYSTEM_CONSTRAINTS,$config->USER_SYSTEM_DEFAULT_SETTINGS);
        $TokenEntityStorage = new TokenEntityStorageMySQLImpl($mySQLConn);
        $SimpleCaptchaStorage = new SimpleCaptchaStorageMySQLImpl($mySQLConn,$config->CAPTCHA_PHRASE_LEN);
        $APPEntityStorage = new APPEntityStorageMySQLImpl($mySQLConn,$config->APP_SYSTEM_FORMAT_CONSTRAINTS);
        $AuthCodeStorage = new AuthCodeStorageMySQLImpl($mySQLConn);
        $APPTokenStorage = new APPTokenStorageMySQLImpl($mySQLConn);
        $MaskIDStorage = new MaskIDStorageMySQLImpl($mySQLConn);
        $EXTOAuthStorageRecordStorage = new EXTOAuthStorageRecordStorageMySQLImpl($mySQLConn);
        $EXTTicketRecordStorage = new EXTOAuthTicketRecordStorageMySQLImpl($mySQLConn,$config->OAUTH_TICKET_SYSTEM_FORMAT_CONSTRAINTS);

        $LoggerStorage->createTables();
        $VeriCodeStorage->createTables();
        $UserEntityStorage->createTables();
        $TokenEntityStorage->createTables();
        $SimpleCaptchaStorage->createTables();
        $APPEntityStorage->createTables();
        $AuthCodeStorage->createTables();
        $APPTokenStorage->createTables();
        $MaskIDStorage->createTables();
        $EXTOAuthStorageRecordStorage->createTables();
        $EXTTicketRecordStorage->createTables();
        

        $mySQLConn->disconnect();
    }
}