<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\APP\AuthCode\AuthCodeChallengeType;
use InteractivePlus\PDK2021Core\APP\AuthCode\AuthCodeEntity;
use InteractivePlus\PDK2021Core\APP\AuthCode\AuthCodeStorage;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use MysqliDb;

class AuthCodeStorageMySQLImpl extends AuthCodeStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    
    public function __construct(MysqliDb $db){
        $this->db = $db;
    }

    public function createTables() : void{
        $authCodeLen = APPFormat::getAuthCodeStringLength();
        $maskIDLen = MaskIDFormat::getMaskIDStringLength();
        $challengeLen = APPFormat::getChallengeS256StringLength();

        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `oauth_codes` (
                `auth_code` CHAR({$authCodeLen}) NOT NULL,
                `appuid` INT UNSIGNED NOT NULL,
                `mask_id` CHAR({$maskIDLen}) NOT NULL,
                `issue_time` INT UNSIGNED NOT NULL,
                `expire_time` INT UNSIGNED NOT NULL,
                `scopes` TINYTEXT,
                `code_challenge` VARCHAR({$challengeLen}),
                `challenge_type` INT NOT NULL,
                `used` TINYINT(1) NOT NULL,
                PRIMARY KEY ( `auth_code` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `oauth_codes`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `oauth_codes`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    protected function scopeArrayToScopeData(array $scope) : ?string{
        return empty($scope) ? null : implode(' ',$scope);
    }

    protected function scopeDataToScopeArray(?string $data) : array{
        return empty($data) ? array() : explode(' ',$data);
    }

    protected function __addAuthCodeEntity(AuthCodeEntity $entity) : void{
        $dataToInsert = array(
            'auth_code' => $entity->getAuthCodeStr(),
            'appuid' => $entity->appUID,
            'mask_id' => $entity->getMaskID(),
            'issue_time' => $entity->issueTime,
            'expire_time' => $entity->expireTime,
            'scopes' => $this->scopeArrayToScopeData($entity->scopes),
            'code_challenge' => $entity->codeChallenge,
            'challenge_type' => $entity->getChallengeType(),
            'used' => $entity->used ? 1 : 0
        );
        $insertResult = $this->db->insert('oauth_codes',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function updateAuthCodeEntity(AuthCodeEntity $entity) : void{
        $dataToUpdate = array(
            'appuid' => $entity->appUID,
            'mask_id' => $entity->getMaskID(),
            'issue_time' => $entity->issueTime,
            'expire_time' => $entity->expireTime,
            'scopes' => $this->scopeArrayToScopeData($entity->scopes),
            'code_challenge' => $entity->codeChallenge,
            'challenge_type' => $entity->getChallengeType(),
            'used' => $entity->used ? 1 : 0
        );
        $this->db->where('auth_code',$entity->getAuthCodeStr());
        $result = $this->db->update('oauth_codes',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return;
    }

    protected function getAuthCodeEntityByDataBaseRow(array $dataRow) : AuthCodeEntity{
        $newEntity = new AuthCodeEntity(
            $dataRow['auth_code'],
            $dataRow['appuid'],
            $dataRow['mask_id'],
            $dataRow['issue_time'],
            $dataRow['expire_time'],
            $this->scopeDataToScopeArray($dataRow['scopes']),
            $dataRow['code_challenge'],
            $dataRow['challenge_type'],
            $dataRow['used'] === 1
        );
        return $newEntity;
    }

    public function checkAuthCodeExist(string $authCode) : bool{
        $this->db->where('auth_code',$authCode);
        $result = $this->db->getValue('oauth_codes','count(*)');
        if(!$result){
            return -1;
        }else{
            return $result >= 1;
        }
    }
    public function getAuthCodeEntity(string $authCode) : ?AuthCodeEntity{
        $this->db->where('auth_code',$authCode);
        $result = $this->db->getOne('oauth_codes');
        if(!$result){
            return null;
        }
        return $this->getAuthCodeEntityByDataBaseRow($result);
    }
    public function useAuthCode(string $authCode) : void{
        $dataToUpdate = array(
            'used' => 1
        );
        $this->db->where('auth_code',$authCode);
        $result = $this->db->update('oauth_codes',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return;
    }
    public function searchAuthCodeEntity(?string $authCode = null, int $createTimeStart = -1, int $createTimeEnd = -1, int $expireTimeStart = -1, int $expireTimeEnd = -1, ?string $relatedMaskID = null, int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID, int $dataOffset = 0, int $dataCountLimit = -1) : MultipleResult{
        if(!empty($authCode)){
            $this->db->where('auth_code','%' . $authCode . '%', 'LIKE');
        }
        if($createTimeStart >= 0){
            $this->db->where('create_time',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('create_time',$createTimeEnd,'<=');
        }
        if($expireTimeStart >= 0){
            $this->db->where('expire_time',$expireTimeStart,'>=');
        }
        if($expireTimeEnd >= 0){
            $this->db->where('expire_time',$expireTimeEnd,'<=');
        }
        if(!empty($relatedMaskID)){
            $this->db->where('mask_id',$relatedMaskID);
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        $dataLimit = null;
        if($dataCountLimit != -1){
            $dataLimit = array($dataOffset, $dataCountLimit);            
        }
        $result = $this->db->withTotalCount()->get('oauth_codes',$dataLimit);
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $resultObjArr[] = $this->getAuthCodeEntityByDataBaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }
    public function getAPPEntityCount(?string $authCode = null, int $createTimeStart = -1, int $createTimeEnd = -1, int $expireTimeStart = -1, int $expireTimeEnd = -1, ?string $relatedMaskID = null, int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID) : int{
        if(!empty($authCode)){
            $this->db->where('auth_code','%' . $authCode . '%', 'LIKE');
        }
        if($createTimeStart >= 0){
            $this->db->where('create_time',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('create_time',$createTimeEnd,'<=');
        }
        if($expireTimeStart >= 0){
            $this->db->where('expire_time',$expireTimeStart,'>=');
        }
        if($expireTimeEnd >= 0){
            $this->db->where('expire_time',$expireTimeEnd,'<=');
        }
        if(!empty($relatedMaskID)){
            $this->db->where('mask_id',$relatedMaskID);
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        $count = $this->db->getValue('oauth_codes','count(*)');
        if(!$count){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count;
    }
}