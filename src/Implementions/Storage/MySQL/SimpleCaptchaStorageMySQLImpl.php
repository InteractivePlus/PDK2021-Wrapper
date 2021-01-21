<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Captcha\Format\CaptchaFormat;
use InteractivePlus\PDK2021Core\Captcha\Implemention\PDKSimpleCaptchaSystemStorage;
use MysqliDb;

class SimpleCaptchaStorageMySQLImpl implements PDKSimpleCaptchaSystemStorage, MySQLStorageImpl{
    private MysqliDb $db;
    private int $phraseLen;
    public function __construct(MysqliDb $db, int $phraseLen)
    {
        $this->db = $db;
        $this->phraseLen = $phraseLen;
    }

    public function createTables() : void{
        $mysqli = $this->db->mysqli();
        
        $captchaStrLen = CaptchaFormat::getCaptchaIDStringLength();
        $phraseLen = $this->phraseLen;

        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `captcha_simple_infos` (
                `captcha_id` CHAR({$captchaStrLen}) NOT NULL,
                `phrase` CHAR({$phraseLen}) NOT NULL,
                `issue_time` INT UNSIGNED NOT NULL,
                `expire_time` INT UNSIGNED NOT NULL,
                `width` INT UNSIGNED NOT NULL,
                `height` INT UNSIGNED NOT NULL,
                `verified` TINYINT(1) NOT NULL,
                `used` TINYINT(1) NOT NULL,
                PRIMARY KEY ( `captcha_id` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `captcha_simple_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `captcha_simple_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    public function saveCaptchaToDatabase(string $captchaID, string $phrase, int $issued_time, int $expire_time, int $width, int $height, bool $phrasePassed = false, bool $used = false){
        $insertData = [
            'captcha_id' => $captchaID,
            'phrase' => $phrase,
            'issue_time' => $issued_time,
            'expire_time' => $expire_time,
            'width' => $width,
            'height' => $height,
            'verified' => $phrasePassed ? 1 : 0,
            'used' => $used ? 1 : 0
        ];
        $insertRst = $this->db->insert('captcha_simple_infos',$insertData);
        if(!$insertRst){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function checkCaptchaAvailable(string $captchaID, int $currentTime) : bool{
        $this->db->where('captcha_id',$captchaID);
        $this->db->where('expire_time',$currentTime,'>');
        $row = $this->db->getOne('captcha_simple_infos',array('verified','used'));
        if($row === null){
            return false;
        }
        if($row['verified'] == 1 && $row['used'] == 0){
            return true;
        }
    }
    public function useCpatcha(string $captchaID) : void{
        $updateArr = [
            'used' => 1
        ];
        $this->db->where('captcha_id',$captchaID);
        $updateRst = $this->db->update('captcha_simple_infos', $updateArr);
        if(!$updateRst){
            throw new PDKStorageEngineError('failed to update data in database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function checkCaptchaIDExist(string $captchaID) : bool{
        $this->db->where('captcha_id',$captchaID);
        $value = $this->db->getValue('captcha_simple_infos','count(*)');
        if($value >= 1){
            return true;
        }else if($value === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            return false;
        }
    }
    public function trySubmitCaptchaPhrase(string $captchaID, string $phrase) : bool{
        $updateData = [
            'verified' => 1
        ];
        $this->db->where('captcha_id',$captchaID);
        $this->db->where('phrase',$phrase);
        $updateRst = $this->db->update('captcha_simple_infos',$updateData);
        if(!$updateRst){
            throw new PDKStorageEngineError('failed to update data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            if($this->db->count >= 1){
                return true;
            }else{
                return false;
            }
        }
    }
    public function getPhraseLen() : int{
        return $this->phraseLen;
    }
}