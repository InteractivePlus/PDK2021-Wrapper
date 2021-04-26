<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\EXT_Storage\StorageRecord\OAuthStorageRecordEntity;
use InteractivePlus\PDK2021Core\EXT_Storage\StorageRecord\OAuthStorageRecordStorage;
use MysqliDb;

class EXTOAuthStorageRecordStorageMySQLImpl extends OAuthStorageRecordStorage{
    private MysqliDb $db;
    public function __construct(MysqliDb $db)
    {
        $this->db = $db;
    }
    public function createTables() : void{
        $clientIDLen = APPFormat::getAPPIDStringLength();
        $maskIDLen = MaskIDFormat::getMaskIDStringLength();

        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `oauth_ext_storage_records` (
                `client_id` CHAR({$clientIDLen}) NOT NULL,
                `mask_id` CHAR({$maskIDLen}) NOT NULL,
                `appuid` INT UNSIGNED NOT NULL,
                `data` BLOB,
                `created` INT UNSIGNED NOT NULL,
                `last_updated` INT UNSIGNED NOT NULL
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `oauth_ext_storage_records`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `oauth_ext_storage_records`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function addOAuthStorageRecord(OAuthStorageRecordEntity $entity) : void{
        $dataToInsert = array(
            'client_id' => $entity->getClientID(),
            'mask_id' => $entity->getMaskID(),
            'appuid' => $entity->appuid,
            'data' => $entity->getCompressedData(),
            'created' => $entity->created,
            'last_updated' => $entity->lastUpdated
        );
        $insertResult = $this->db->insert('oauth_ext_storage_records',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            return;
        }
    }

    protected function getStorageRecordEntityByDatabaseRow(array $dataRow) : OAuthStorageRecordEntity{
        $newEntity = OAuthStorageRecordEntity::fromDatabase(
            $dataRow['mask_id'],
            $dataRow['client_id'],
            $dataRow['appuid'],
            empty($dataRow['data']) ? null : $dataRow['data'],
            $dataRow['created'],
            $dataRow['last_updated']
        );
        return $newEntity;
    }

    public function checkOAuthStorageRecordExist(string $mask_id, string $client_id) : bool{
        $this->db->where('mask_id',$mask_id);
        $this->db->where('client_id',$client_id);
        $result = $this->db->getValue('oauth_ext_storage_records','count(*)');
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result >= 1;
    }
    public function checkOAuthStorageRecordExistByAPPUID(string $mask_id, int $appuid) : bool{
        $this->db->where('mask_id',$mask_id);
        $this->db->where('appuid',$appuid);
        $result = $this->db->getValue('oauth_ext_storage_records','count(*)');
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result >= 1;
    }
    public function getOAuthStorageRecord(string $mask_id, string $client_id) : ?OAuthStorageRecordEntity{
        $this->db->where('mask_id',$mask_id);
        $this->db->where('client_id',$client_id);
        $result = $this->db->getOne('oauth_ext_storage_records');
        if(!$result){
            return null;
        }
        return $this->getStorageRecordEntityByDatabaseRow($result);
    }
    public function getOAuthStorageRecordByAPPUID(string $mask_id, int $appuid) : ?OAuthStorageRecordEntity{
        $this->db->where('mask_id',$mask_id);
        $this->db->where('appuid',$appuid);
        $result = $this->db->getOne('oauth_ext_storage_records');
        if(!$result){
            return null;
        }
        return $this->getStorageRecordEntityByDatabaseRow($result);
    }
    public function updateOAuthStorageRecord(OAuthStorageRecordEntity $entity) : void{
        $dataToUpdate = array(
            'appuid' => $entity->appuid,
            'data' => $entity->getCompressedData(),
            'created' => $entity->created,
            'last_updated' => $entity->lastUpdated
        );
        $this->db->where('client_id',$entity->getClientID());
        $this->db->where('mask_id',$entity->getMaskID());

        $result = $this->db->update('oauth_ext_storage_records',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function searchOAuthStorageRecordEntity(int $createTimeStart = -1, int $createTimeEnd = -1, int $lastUpdateStart = -1, int $lastUpdateEnd = -1, ?string $relatedMaskID = null, ?string $relatedClientID = null, int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID, int $dataOffset = 0, int $dataCountLimit = -1) : MultipleResult{
        if(!empty($relatedMaskID)){
            $this->db->where('mask_id','%' . $relatedMaskID . '%', 'LIKE');
        }
        if(!empty($relatedClientID)){
            $this->db->where('client_id','%' . $relatedClientID . '%', 'LIKE');
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        if($createTimeStart >= 0){
            $this->db->where('created',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('created',$createTimeEnd,'<=');
        }
        if($lastUpdateStart >= 0){
            $this->db->where('last_updated',$lastUpdateStart,'>=');
        }
        if($lastUpdateEnd >= 0){
            $this->db->where('last_updated',$lastUpdateEnd,'<=');
        }
        $dataLimit = null;
        if($dataCountLimit != -1){
            $dataLimit = array($dataOffset, $dataCountLimit);            
        }
        $result = $this->db->withTotalCount()->get('oauth_ext_storage_records',$dataLimit);
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $resultObjArr[] = $this->getStorageRecordEntityByDatabaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }
    public function getOAuthStorageRecordEntityCount(int $createTimeStart = -1, int $createTimeEnd = -1, int $lastUpdateStart = -1, int $lastUpdateEnd = -1, ?string $relatedMaskID = null, ?string $relatedClientID = null, int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID) : int{
        if(!empty($relatedMaskID)){
            $this->db->where('mask_id','%' . $relatedMaskID . '%', 'LIKE');
        }
        if(!empty($relatedClientID)){
            $this->db->where('client_id','%' . $relatedClientID . '%', 'LIKE');
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        if($createTimeStart >= 0){
            $this->db->where('created',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('created',$createTimeEnd,'<=');
        }
        if($lastUpdateStart >= 0){
            $this->db->where('last_updated',$lastUpdateStart,'>=');
        }
        if($lastUpdateEnd >= 0){
            $this->db->where('last_updated',$lastUpdateEnd,'<=');
        }
        $count = $this->db->getValue('oauth_ext_storage_records','count(*)');
        if($count === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count;
    }
    public function clearAuthCode(int $createTimeStart = -1, int $createTimeEnd = -1, int $lastUpdateStart = -1, int $lastUpdateEnd = -1, ?string $relatedMaskID = null, ?string $relatedClientID = null, int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID, int $dataOffset = 0, int $dataCountLimit = -1) : void{
        if(!empty($relatedMaskID)){
            $this->db->where('mask_id','%' . $relatedMaskID . '%', 'LIKE');
        }
        if(!empty($relatedClientID)){
            $this->db->where('client_id','%' . $relatedClientID . '%', 'LIKE');
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        if($createTimeStart >= 0){
            $this->db->where('created',$createTimeStart,'>=');
        }
        if($createTimeEnd >= 0){
            $this->db->where('created',$createTimeEnd,'<=');
        }
        if($lastUpdateStart >= 0){
            $this->db->where('last_updated',$lastUpdateStart,'>=');
        }
        if($lastUpdateEnd >= 0){
            $this->db->where('last_updated',$lastUpdateEnd,'<=');
        }
        $result = $this->db->delete('oauth_ext_storage_records');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
}