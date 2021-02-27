<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Formats\IPFormat;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeID;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeIDs;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeStorage;
use MysqliDb;

class VeriCodeStorageMySQLImpl extends VeriCodeStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    public function __construct(MysqliDb $db)
    {
        $this->db = $db;
    }
    public function createTables() : void{
        $ipMaxLen = IPFormat::IPV6_STR_MAX_LEN;
        $veriCodeStrLen = VeriCodeFormat::getVeriCodeStringLength();
        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `verification_codes` (
                `vericode_id` INT UNSIGNED NOT NULL,
                `trigger_client_addr` VARCHAR({$ipMaxLen}),
                `vericode_params` TEXT,
                `vericode_str` CHAR({$veriCodeStrLen}) NOT NULL,
                `issue_time` INT UNSIGNED NOT NULL,
                `expire_time` INT UNSIGNED NOT NULL,
                `sent_method` TINYINT UNSIGNED NOT NULL,
                `used` TINYINT(1) NOT NULL,
                `related_uid` INT UNSIGNED,
                `related_appuid` INT UNSIGNED,
                PRIMARY KEY ( `vericode_str` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `verification_codes`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `verification_codes`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    protected function __addVeriCodeEntity(VeriCodeEntity $veriCode) : void{
        $dataToInsert = array(
            'vericode_id' => $veriCode->getVeriCodeID()->getVeriCodeID(),
            'trigger_client_addr' => $veriCode->getTriggerClientIP(),
            'vericode_params' => empty($veriCode->getVeriCodeParams()) ? null : json_encode($veriCode->getVeriCodeParams()),
            'vericode_str' => $veriCode->getVeriCodeString(),
            'issue_time' => $veriCode->getIssueUTCTime(),
            'expire_time' => $veriCode->getExpireUTCTime(),
            'sent_method' => $veriCode->getSentMethod(),
            'used' => $veriCode->isVeriCodeUsed() ? 1 : 0,
            'related_uid' => $veriCode->getUID() === UserSystemConstants::NO_USER_RELATED_UID ? null : $veriCode->getUID(),
            'related_appuid' => $veriCode->getAPPUID() === APPSystemConstants::NO_APP_RELATED_APPUID ? null : $veriCode->getAPPUID()
        );
        $insertResult = $this->db->insert('verification_codes',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('Failed to insert data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function checkVeriCodeExist(string $veriCodeString) : bool{
        $this->db->where('vericode_str',VeriCodeFormat::formatVerificationCode($veriCodeString));
        $count = $this->db->getValue('verification_codes','count(*)');
        if($count >= 1){
            return true;
        }else{
            return false;
        }
    }
    protected function VeriCodeEntityFromDataRow(array $dataRow) : VeriCodeEntity{
        $newEntity = new VeriCodeEntity(
            VeriCodeIDs::findVeriCodeID($dataRow['vericode_id']),
            $dataRow['issue_time'],
            $dataRow['expire_time'],
            !isset($dataRow['related_uid']) ? UserSystemConstants::NO_USER_RELATED_UID : $dataRow['related_uid'],
            !isset($dataRow['related_appuid']) ? APPSystemConstants::NO_APP_RELATED_APPUID : $dataRow['related_appuid'],
            empty($dataRow['vericode_params']) ? null : json_decode($dataRow['vericode_params'],true),
            $dataRow['trigger_client_ip'],
            $dataRow['vericode_str'],
            $dataRow['sent_method'],
            $dataRow['used'] === 1 ? true : false
        );
        return $newEntity;
    }
    public function getVeriCodeEntity(string $veriCodeString) : ?VeriCodeEntity{
        $this->db->where('vericode_str',VeriCodeFormat::formatVerificationCode($veriCodeString));
        $rowData = $this->db->getOne('verification_codes');
        if(!$rowData){
            return null;
        }
        return $this->VeriCodeEntityFromDataRow($rowData);
    }
    public function updateVeriCodeEntity(VeriCodeEntity $veriCode) : bool{
        $newDataRow = array(
            'vericode_id' => $veriCode->getVeriCodeID()->getVeriCodeID(),
            'trigger_client_addr' => $veriCode->getTriggerClientIP(),
            'vericode_params' => empty($veriCode->getVeriCodeParams()) ? null : json_encode($veriCode->getVeriCodeParams()),
            'issue_time' => $veriCode->getIssueUTCTime(),
            'expire_time' => $veriCode->getExpireUTCTime(),
            'sent_method' => $veriCode->getSentMethod(),
            'used' => $veriCode->isVeriCodeUsed() ? 1 : 0,
            'related_uid' => $veriCode->getUID() === UserSystemConstants::NO_USER_RELATED_UID ? null : $veriCode->getUID(),
            'related_appuid' => $veriCode->getAPPUID() === APPSystemConstants::NO_APP_RELATED_APPUID ? null : $veriCode->getAPPUID()
        );
        $this->db->where('vericode_str',$veriCode->getVeriCodeString());
        $updateState = $this->db->update('verification_codes',$newDataRow,1);
        if(!$updateState){
            throw new PDKStorageEngineError('failed to update table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $this->db->count === 1;
    }
    
    public function useVeriCodeEntity(string $veriCodeString) : void{
        $newDataRow = array(
            'used' => 1
        );
        $this->db->where('vericode_str',VeriCodeFormat::formatVerificationCode($veriCodeString));
        $updateState = $this->db->update('verification_codes',$newDataRow,1);
        if(!$updateState){
            throw new PDKStorageEngineError('failed to update table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return;
    }

    public function searchVeriCode(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $uid = UserSystemConstants::NO_USER_RELATED_UID, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID, int $veriCodeID = 0) : MultipleResult{
        if($issueTimeMin > 0){
            $this->db->where('issue_time',$issueTimeMin,'>=');
        }
        if($issueTimeMax > 0){
            $this->db->where('issue_time',$issueTimeMax,'<=');
        }
        if($expireTimeMin > 0){
            $this->db->where('expire_time',$expireTimeMin, '>=');
        }
        if($expireTimeMax > 0){
            $this->db->where('expire_time',$expireTimeMax,'<=');
        }
        if($uid !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('related_uid',$uid);
        }
        if($appuid !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('related_appuid',$appuid);
        }
        $this->db->where('sent_method',SentMethod::SMS_MESSAGE,'>=');
        $this->db->where('sent_method',SentMethod::PHONE_CALL,'<=');
        if($veriCodeID != 0){
            $this->db->where('vericode_id',$veriCodeID);
        }
        $result = $this->db->withTotalCount()->get('verification_codes');
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRowData){
            $resultObjArr[] = $this->VeriCodeEntityFromDataRow($singleRowData);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }

    public function searchPhoneVeriCode(int $expireTimeMin = 0, int $expireTimeMax = 0, int $uid = UserSystemConstants::NO_USER_RELATED_UID, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID, string $partialVericodeStr = null, int $veriCodeID = 0) : MultipleResult{
        if($expireTimeMin > 0){
            $this->db->where('expire_time',$expireTimeMin, '>=');
        }
        if($expireTimeMax > 0){
            $this->db->where('expire_time',$expireTimeMax,'<=');
        }
        if($uid !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('related_uid',$uid);
        }
        if($uid !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('related_uid',$uid);
        }
        if($appuid !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('related_appuid',$appuid);
        }
        if($veriCodeID != 0){
            $this->db->where('vericode_id',$veriCodeID);
        }
        if(!empty($partialVericodeStr)){
            $this->db->where('vericode_str', $partialVericodeStr . '%','LIKE');
        }
        $result = $this->db->withTotalCount()->get('verification_codes');
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRowData){
            $resultObjArr[] = $this->VeriCodeEntityFromDataRow($singleRowData);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }

    public function clearVeriCode(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $uid = UserSystemConstants::NO_USER_RELATED_UID, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID, int $veriCodeID = 0) : void{
        if($issueTimeMin > 0){
            $this->db->where('issue_time',$issueTimeMin,'>=');
        }
        if($issueTimeMax > 0){
            $this->db->where('issue_time',$issueTimeMax,'<=');
        }
        if($expireTimeMin > 0){
            $this->db->where('expire_time',$expireTimeMin, '>=');
        }
        if($expireTimeMax > 0){
            $this->db->where('expire_time',$expireTimeMax,'<=');
        }
        if($uid !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('related_uid',$uid);
        }
        if($appuid !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('related_appuid',$appuid);
        }
        if($veriCodeID != 0){
            $this->db->where('vericode_id',$veriCodeID);
        }
        $this->db->delete('verification_codes');
    }
    
    public function getVeriCodeCount() : int{
        return $this->db->getValue('verification_codes','count(*)');
    }

}