<?php
namespace InteractivePlus\PDK2021;

use InteractivePlus\PDK2021\Implementions\Sender\LocalFileSMSServiceProvider;
use InteractivePlus\PDK2021\Implementions\Sender\SendGridEmailServiceProvider;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\LoggerStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\TokenEntityStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\UserEntityStorageMySQLImpl;
use InteractivePlus\PDK2021\Implementions\Storage\MySQL\VeriCodeStorageMySQLImpl;
use InteractivePlus\PDK2021Core\Communication\VeriSender\Implementions\VeriCodeEmailSenderImplWithProvider;
use InteractivePlus\PDK2021Core\Communication\VeriSender\Implementions\VeriCodeSMSSenderImplWithService;
use InteractivePlus\PDK2021Core\PDKCore;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;
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
            new SendGridEmailServiceProvider($config->SENDGRID_APIKEY,$config->SENDGRID_FROM_ADDR,$config->SENDGRID_FROM_NAME),
            new EmailContentProvider()
        );
        $SMSSender = new VeriCodeSMSSenderImplWithService(
            new LocalFileSMSServiceProvider(__DIR__ . '/../LocalFileServiceProvider/SMS.json'),
            new SMSContentProvider,
            ' 【形随意动用户系统团队】'
        );
        self::$pdkCore = new PDKCore(
            $LoggerStorage,
            $VeriCodeStorage,
            $EmailSender,
            $SMSSender,
            null,
            $UserEntityStorage,
            $TokenEntityStorage
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
        $LoggerStorage->createTables();
        $VeriCodeStorage->createTables();
        $UserEntityStorage->createTables();
        $TokenEntityStorage->createTables();
        $mySQLConn->disconnect();
    }
}