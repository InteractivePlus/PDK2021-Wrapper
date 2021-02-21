<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntityStorage;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;

use MysqliDb;

class APPTokenStorageMySQLImpl extends APPTokenEntityStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    public function __construct(MysqliDb $db)
    {
        $this->db = $db;
    }
    public function createTables() : void{
        $accessTokenLen = APPFormat::getAPPAccessTokenStringLength();
        $refreshTokenLen = APPFormat::getAPPRefreshTokenStringLength();
        $clientIDLen = APPFormat::getAPPIDStringLength();
        $maskIDLen = MaskIDFormat::getMaskIDStringLength();

        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `oauth_tokens` (
                `access_token` CHAR({$accessTokenLen}) NOT NULL,
                `refresh_token` CHAR({$refreshTokenLen}) NOT NULL,
                `issue_time` INT UNSIGNED NOT NULL,
                `expire_time` INT UNSIGNED NOT NULL,
                `last_renew_time` INT UNSIGNED NOT NULL,
                `refresh_expire_time` INT UNSIGNED NOT NULL,
                `mask_id` CHAR({$maskIDLen}) NOT NULL,
                `appuid` INT UNSIGNED NOT NULL,
                `client_id` CHAR({$clientIDLen}) NOT NULL,
                `obtained_method` INT UNSIGNED NOT NULL,
                `scopes` TINYTEXT,
                `valid` TINYINT(1) NOT NULL,
                PRIMARY KEY ( `access_token`, `refresh_token` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `oauth_tokens`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `oauth_tokens`;'
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

    protected function __addAPPTokenEntity(APPTokenEntity $Token) : void{
        $dataToAdd = array(
            'access_token' => $Token->getAccessToken(),
            'refresh_token' => $Token->getRefreshToken(),
            'issue_time' => $Token->issueTime,
            'expire_time' => $Token->expireTime,
            'last_renew_time' => $Token->lastRefreshTime,
            'refresh_expire_time' => $Token->refreshExpireTime,
            'mask_id' => $Token->getMaskID(),
            'appuid' => $Token->appuid,
            'client_id' => $Token->getClientID(),
            'obtained_method' => $Token->getObtainedMethod(),
            'scopes' => $this->scopeArrayToScopeData($Token->scopes),
            'valid' => $Token->valid ? 1 : 0
        );
        $result = $this->db->insert('oauth_tokens',$dataToAdd);
        if(!$result){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function checkAccessTokenExist(string $AccessTokenString) : bool{
        $this->db->where('access_token',$AccessTokenString);
        $count = $this->db->getValue('oauth_tokens','count(*)');
        if(!$count){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count >= 1;
    }
    public function checkRefreshTokenExist(string $RefreshTokenString) : bool{
        $this->db->where('refresh_token',$RefreshTokenString);
        $count = $this->db->getValue('oauth_tokens','count(*)');
        if(!$count){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count >= 1;
    }
    protected function getAPPTokenEntityFromDatabaseRow(array $dataRow) : APPTokenEntity{
        $newEntity = new APPTokenEntity(
            $dataRow['access_token'],
            $dataRow['refresh_token'],
            $dataRow['issue_time'],
            $dataRow['expire_time'],
            $dataRow['last_renew_time'],
            $dataRow['refresh_expire_time'],
            $dataRow['mask_id'],
            $dataRow['appuid'],
            $dataRow['client_id'],
            $dataRow['obtained_method'],
            $this->scopeDataToScopeArray($dataRow['scopes']),
            $dataRow['valid'] === 1
        );
        return $newEntity;
    }
    public function getAPPTokenEntity(string $accessToken) : ?APPTokenEntity{
        $this->db->where('access_token',$accessToken);
        $result = $this->db->getOne('oauth_tokens');
        if(!$result){
            return null;
        }
        return $this->getAPPTokenEntityFromDatabaseRow($result);
    }
    public function getAPPTokenEntitybyRefreshToken(string $refreshToken) : ?APPTokenEntity{
        $this->db->where('refresh_token',$refreshToken);
        $result = $this->db->getOne('oauth_tokens');
        if(!$result){
            return null;
        }
        return $this->getAPPTokenEntityFromDatabaseRow($result);
    }

    public function updateAPPTokenEntity(APPTokenEntity $Token) : bool{
        $dataToUpdate = array(
            'refresh_token' => $Token->getRefreshToken(),
            'issue_time' => $Token->issueTime,
            'expire_time' => $Token->expireTime,
            'last_renew_time' => $Token->lastRefreshTime,
            'refresh_expire_time' => $Token->refreshExpireTime,
            'mask_id' => $Token->getMaskID(),
            'appuid' => $Token->appuid,
            'client_id' => $Token->getClientID(),
            'obtained_method' => $Token->getObtainedMethod(),
            'scopes' => $this->scopeArrayToScopeData($Token->scopes),
            'valid' => $Token->valid ? 1 : 0
        );
        $this->db->where('access_token',$Token->getAccessToken());
        $result = $this->db->update('oauth_tokens',$dataToUpdate);
        if(!$result){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $this->db->count >= 1;
    }
    
    public function setAPPTokenEntityInvalid(string $TokenString) : void{
        $dataToUpdate = array(
            'valid' => 0
        );
        $this->db->where('access_token',$TokenString);
        $result = $this->db->update('oauth_tokens',$dataToUpdate);
        if(!$result){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return;
    }

    public function searchAPPToken(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $lastRenewTimeMin = 0, int $lastRenewTimeMax = 0, int $refreshExpireMin = 0, int $refreshExpireMax = 0, ?string $maskID = null, int $appid = APPSystemConstants::NO_APP_RELATED_APPUID, int $dataOffset = 0, int $dataLimit = -1) : MultipleResult{
        if($issueTimeMin > 0){
            $this->db->where('issue_time',$issueTimeMin,'>=');
        }
        if($issueTimeMax > 0){
            $this->db->where('issue_time',$issueTimeMax,'<=');
        }
        if($expireTimeMin >= 0){
            $this->db->where('expire_time',$expireTimeMin,'>=');
        }
        if($expireTimeMax >= 0){
            $this->db->where('expire_time',$expireTimeMax,'<=');
        }
        if($lastRenewTimeMin >= 0){
            $this->db->where('last_renew_time',$lastRenewTimeMin,'>=');
        }
        if($lastRenewTimeMax >= 0){
            $this->db->where('last_renew_time',$lastRenewTimeMax,'<=');
        }
        if($refreshExpireMin >= 0){
            $this->db->where('refresh_expire_time',$refreshExpireMin,'>=');
        }
        if($refreshExpireMax >= 0){
            $this->db->where('refresh_expire_time',$refreshExpireMax,'<=');
        }
        if(!empty($maskID)){
            $this->db->where('mask_id','%' . $maskID . '%', 'LIKE');
        }
        if($appid != APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$appid);
        }
        $result = $this->db->withTotalCount()->get('oauth_tokens');
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $resultObjArr[] = $this->getAPPTokenEntityFromDatabaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }

    public function clearAPPToken(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $lastRenewTimeMin = 0, int $lastRenewTimeMax = 0, int $refreshExpireMin = 0, int $refreshExpireMax = 0, ?string $maskID = null, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID) : void{
        if($issueTimeMin > 0){
            $this->db->where('issue_time',$issueTimeMin,'>=');
        }
        if($issueTimeMax > 0){
            $this->db->where('issue_time',$issueTimeMax,'<=');
        }
        if($expireTimeMin >= 0){
            $this->db->where('expire_time',$expireTimeMin,'>=');
        }
        if($expireTimeMax >= 0){
            $this->db->where('expire_time',$expireTimeMax,'<=');
        }
        if($lastRenewTimeMin >= 0){
            $this->db->where('last_renew_time',$lastRenewTimeMin,'>=');
        }
        if($lastRenewTimeMax >= 0){
            $this->db->where('last_renew_time',$lastRenewTimeMax,'<=');
        }
        if($refreshExpireMin >= 0){
            $this->db->where('refresh_expire_time',$refreshExpireMin,'>=');
        }
        if($refreshExpireMax >= 0){
            $this->db->where('refresh_expire_time',$refreshExpireMax,'<=');
        }
        if(!empty($maskID)){
            $this->db->where('mask_id','%' . $maskID . '%', 'LIKE');
        }
        if($appuid != APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$appuid);
        }
        $result = $this->db->delete('oauth_tokens');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    
    public function getAPPTokenCount(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $lastRenewTimeMin = 0, int $lastRenewTimeMax = 0, int $refreshExpireMin = 0, int $refreshExpireMax = 0, ?string $maskID = null, int $appuid = APPSystemConstants::NO_APP_RELATED_APPUID) : int{
        if($issueTimeMin > 0){
            $this->db->where('issue_time',$issueTimeMin,'>=');
        }
        if($issueTimeMax > 0){
            $this->db->where('issue_time',$issueTimeMax,'<=');
        }
        if($expireTimeMin >= 0){
            $this->db->where('expire_time',$expireTimeMin,'>=');
        }
        if($expireTimeMax >= 0){
            $this->db->where('expire_time',$expireTimeMax,'<=');
        }
        if($lastRenewTimeMin >= 0){
            $this->db->where('last_renew_time',$lastRenewTimeMin,'>=');
        }
        if($lastRenewTimeMax >= 0){
            $this->db->where('last_renew_time',$lastRenewTimeMax,'<=');
        }
        if($refreshExpireMin >= 0){
            $this->db->where('refresh_expire_time',$refreshExpireMin,'>=');
        }
        if($refreshExpireMax >= 0){
            $this->db->where('refresh_expire_time',$refreshExpireMax,'<=');
        }
        if(!empty($maskID)){
            $this->db->where('mask_id','%' . $maskID . '%', 'LIKE');
        }
        if($appuid != APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$appuid);
        }
        $result = $this->db->getValue('oauth_tokens','count(*)');
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result;
    }

}