<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntityStorage;
use InteractivePlus\PDK2021Core\APP\APPSystemFormatSetting;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\User\Formats\UserFormat;
use MysqliDb;

class APPEntityStorageMySQLImpl extends APPEntityStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    

    public function __construct(MysqliDb $db, APPSystemFormatSetting $formatSetting){
        parent::__construct($formatSetting);
        $this->db = $db;
    }

    public function createTables() : void{
        $displayNameMax = $this->getFormatSetting()->getAPPDisplayNameMaxLen();
        $clientIDLen = APPFormat::getAPPIDStringLength();
        $clientSecretLen = APPFormat::getAPPSecertStringLength();

        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `app_infos` (
                `appuid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `display_name` VARCHAR({$displayNameMax}) NOT NULL,
                `client_id` CHAR({$clientIDLen}) NOT NULL,
                `client_secret` CHAR({$clientSecretLen}) NOT NULL,
                `client_type` INT NOT NULL,
                `redirect_uri` TINYTEXT NOT NULL,
                `create_time` INT UNSIGNED NOT NULL,
                `owner_uid` INT UNSIGNED NOT NULL,
                PRIMARY KEY ( `appuid` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `app_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `app_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    protected function __addAPPEntity(APPEntity $entity) : int{
        $dataToInsert = array(
            'display_name' => $entity->getDisplayName(),
            'client_id' => $entity->getClientID(),
            'client_secret' => $entity->getClientSecret(),
            'client_type' => $entity->getClientType(),
            'redirect_uri' => strlen($entity->redirectURI) > 255 ? substr($entity->redirectURI,0,255) : $entity->redirectURI,
            'create_time' => $entity->createTime,
            'owner_uid' => $entity->ownerUID
        );
        $insertResult = $this->db->insert('app_infos',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            return $insertResult;
        }
    }
    protected function __updateAPPEntity(APPEntity $entity) : void{
        if($entity->getAPPUID() < 0){
            return;
        }
        $dataToUpdate = array(
            'display_name' => $entity->getDisplayName(),
            'client_id' => $entity->getClientID(),
            'client_secret' => $entity->getClientSecret(),
            'client_type' => $entity->getClientType(),
            'redirect_uri' => strlen($entity->redirectURI) > 255 ? substr($entity->redirectURI,0,255) : $entity->redirectURI,
            'create_time' => $entity->createTime,
            'owner_uid' => $entity->ownerUID
        );
        $this->db->where('appuid',$entity->getAPPUID());
        $result = $this->db->update('app_infos',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    protected function getAPPEntityByDatabaseRow(array $dataRow) : APPEntity{
        $newEntity = APPEntity::fromDatabase(
            $dataRow['appuid'],
            $dataRow['display_name'],
            $dataRow['client_id'],
            $dataRow['client_secret'],
            $dataRow['client_type'],
            $dataRow['redirect_uri'],
            $dataRow['create_time'],
            $dataRow['owner_uid'],
            $this->getFormatSetting()
        );
        return $newEntity;
    }


    public function checkAPPUIDExist(int $appuid) : bool{
        $this->db->where('appuid',$appuid);
        $result = $this->db->getValue('app_infos','count(*)');
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result >= 1;
    }
    public function checkClientIDExist(string $clientID) : int{
        $this->db->where('client_id',$clientID);
        $result = $this->db->getOne('app_infos');
        if($result === null){
            return -1;
        }else{
            return $result['appuid'];
        }
    }
    public function checkDisplayNameExist(string $displayName) : int{
        $this->db->where('display_name',$displayName);
        $result = $this->db->getOne('app_infos');
        if($result === null){
            return -1;
        }else{
            return $result['appuid'];
        }
    }
    public function getAPPEntityByAPPUID(int $appuid) : ?APPEntity{
        $this->db->where('appuid',$appuid);
        $result = $this->db->getOne('app_infos');
        if(!$result){
            return null;
        }
        return $this->getAPPEntityByDatabaseRow($result);
    }
    public function getAPPEntityByClientID(string $clientID) : ?APPEntity{
        $this->db->where('client_id',$clientID);
        $result = $this->db->getOne('app_infos');
        if(!$result){
            return null;
        }
        return $this->getAPPEntityByDatabaseRow($result);
    }
    public function getAPPEntityByDisplayName(string $displayName) : ?APPEntity{
        $this->db->where('display_name',$displayName);
        $result = $this->db->getOne('app_infos');
        if(!$result){
            return null;
        }
        return $this->getAPPEntityByDatabaseRow($result);
    }
    public function deleteAPPEntity(int $appuid) : void{
        $this->db->where('appuid',$appuid);
        $result = $this->db->delete('app_infos');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function searchAPPEntity(?string $displayName = null, int $createTimeStart = -1, int $createTimeEnd = -1, int $ownerUID = UserSystemConstants::NO_USER_RELATED_UID, int $dataOffset = 0, int $dataCountLimit = -1) : MultipleResult{
        if(!empty($displayName)){
            $this->db->where('display_name','%' . $displayName . '%', 'LIKE');
        }
        if($createTimeStart >= 0){
            $this->db->where('create_time',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('create_time',$createTimeEnd,'<=');
        }
        if($ownerUID !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('owner_uid',$ownerUID);
        }
        $dataLimit = null;
        if($dataCountLimit != -1){
            $dataLimit = array($dataOffset, $dataCountLimit);            
        }
        $result = $this->db->withTotalCount()->get('app_infos',$dataLimit);
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $resultObjArr[] = $this->getAPPEntityByDatabaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }
    public function getAPPEntityCount(?string $displayName = null, int $createTimeStart = -1, int $createTimeEnd = -1, int $ownerUID = UserSystemConstants::NO_USER_RELATED_UID) : int{
        if(!empty($displayName)){
            $this->db->where('display_name','%' . $displayName . '%', 'LIKE');
        }
        if($createTimeStart >= 0){
            $this->db->where('create_time',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('create_time',$createTimeEnd,'<=');
        }
        if($ownerUID !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('owner_uid',$ownerUID);
        }
        $count = $this->db->getValue('app_infos','count(*)');
        if($count === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count;
    }
    
}