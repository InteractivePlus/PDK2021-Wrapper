<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;
use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntityStorage;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\User\Setting\SettingBoolean;
use InteractivePlus\PDK2021Core\User\Setting\UserSetting;
use MysqliDb;

class MaskIDStorageMySQLImpl extends MaskIDEntityStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    

    public function __construct(MysqliDb $db){
        $this->db = $db;
    }

    public function createTables() : void{
        $MaskIDLen = MaskIDFormat::getMaskIDStringLength();
        $MaskDisplayNameLen = MaskIDFormat::getMaskIDDispalyNameLength();

        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `maskid_infos` (
                `mask_id` CHAR({$MaskIDLen}) NOT NULL,
                `appuid` INT UNSIGNED NOT NULL,
                `uid` INT UNSIGNED NOT NULL,
                `display_name` VARCHAR({$MaskDisplayNameLen}),
                `create_time` INT UNSIGNED NOT NULL,
                `settings` TEXT,
                PRIMARY KEY ( `mask_id` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `maskid_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `maskid_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    protected function settingObjToSettingJSONArr(UserSetting $setting) : array{
        return array(
            'notificationEmail' => $setting->allowNotificationEmails(),
            'saleEmail' => $setting->allowSaleEmails(),
            'notificationSMS' => $setting->allowNotificationSMS(),
            'saleSMS' => $setting->allowSaleSMS(),
            'notificationCall' => $setting->allowNotificationCall(),
            'saleCall' => $setting->allowSaleCall()
        );
    }

    protected function settingArrToSettingObj(array $settingArr) : UserSetting{
        return new UserSetting(
            is_int($settingArr['notificationEmail']) ? $settingArr['notificationEmail'] : SettingBoolean::INHERIT,
            is_int($settingArr['saleEmail']) ? $settingArr['saleEmail'] : SettingBoolean::INHERIT,
            is_int($settingArr['notificationSMS']) ? $settingArr['notificationSMS'] : SettingBoolean::INHERIT,
            is_int($settingArr['saleSMS']) ? $settingArr['saleSMS'] : SettingBoolean::INHERIT,
            is_int($settingArr['notificationCall']) ? $settingArr['notificationCall'] : SettingBoolean::INHERIT,
            is_int($settingArr['saleCall']) ? $settingArr['saleCall'] : SettingBoolean::INHERIT,
        );
    }

    protected function __addMaskIDEntity(MaskIDEntity $entity) : void{
        $dataToInsert = array(
            'mask_id' => $entity->getMaskID(),
            'appuid' => $entity->appuid,
            'uid' => $entity->uid,
            'display_name' => $entity->getDisplayName(),
            'create_time' => $entity->createTime,
            'settings' => json_encode($this->settingObjToSettingJSONArr($entity->getSettings()))
        );
        $insertResult = $this->db->insert('maskid_infos',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    protected function __updateMaskIDEntity(MaskIDEntity $entity) : void{
        $dataToUpdate = array(
            'appuid' => $entity->appuid,
            'uid' => $entity->uid,
            'display_name' => $entity->getDisplayName(),
            'create_time' => $entity->createTime,
            'settings' => json_encode($this->settingObjToSettingJSONArr($entity->getSettings()))
        );
        $this->db->where('mask_id',$entity->getMaskID());
        $result = $this->db->update('maskid_infos',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return;
    }

    protected function getMaskIDEntityByDatabaseRow(array $dataRow) : MaskIDEntity{
        $maskSetting = $this->settingArrToSettingObj(!empty($dataRow['settings']) ? json_decode($dataRow['settings'],true) : array());
        $newEntity = new MaskIDEntity(
            $dataRow['mask_id'],
            $dataRow['appuid'],
            $dataRow['uid'],
            $dataRow['display_name'],
            $dataRow['create_time'],
            $maskSetting
        );
        return $newEntity;
    }

    public function checkMaskIDExist(string $maskID) : int{
        $this->db->where('mask_id',$$maskID);
        $result = $this->db->getOne('maskid_infos');
        if($result === null){
            return -1;
        }else{
            return $result['uid'];
        }
    }
    public function getMaskIDEntityByMaskID(string $maskID) : ?MaskIDEntity{
        $this->db->where('mask_id',$$maskID);
        $result = $this->db->getOne('maskid_infos');
        if(!$result){
            return null;
        }
        return $this->getMaskIDEntityByDatabaseRow($result);
    }
    public function deleteMaskID(string $maskID) : void{
        $this->db->where('mask_id',$maskID);
        $result = $this->db->delete('maskid_infos');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function searchMaskIDEntity(?string $maskID = null, int $createTimeStart = -1, int $createTimeEnd = -1, int $ownerUID = UserSystemConstants::NO_USER_RELATED_UID, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID, int $dataOffset = 0, int $dataCountLimit = -1) : MultipleResult{
        if(!empty($maskID)){
            $this->db->where('mask_id','%' . $maskID . '%', 'LIKE');
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
        if($appuid !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$appuid);
        }
        $dataLimit = null;
        if($dataCountLimit != -1){
            $dataLimit = array($dataOffset, $dataCountLimit);            
        }
        $result = $this->db->withTotalCount()->get('maskid_infos',$dataLimit);
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $resultObjArr[] = $this->getMaskIDEntityByDatabaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }
    public function getMaskIDEntityCount(?string $maskID = null, int $createTimeStart = -1, int $createTimeEnd = -1, int $ownerUID = UserSystemConstants::NO_USER_RELATED_UID, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID) : int{
        if(!empty($maskID)){
            $this->db->where('mask_id','%' . $maskID . '%', 'LIKE');
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
        if($appuid !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$appuid);
        }
        $count = $this->db->getValue('maskid_infos','count(*)');
        if(!$count){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count;
    }
}