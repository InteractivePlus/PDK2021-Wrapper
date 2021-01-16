<?php
namespace InteractivePlus\PDK2021\Implementions\MySQL;

use MysqliDb;

class MySQLErrorParams{
    public static function paramsFromMySQLiDBObject(MysqliDb $dbObj) : array{
        return array(
            'dbErrNo' => $dbObj->getLastErrno(),
            'dbErrInfo' => $dbObj->getLastError()
        );
    }
}