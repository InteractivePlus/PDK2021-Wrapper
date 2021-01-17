<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Formats\IPFormat;
use InteractivePlus\PDK2021Core\User\Formats\TokenFormat;
use InteractivePlus\PDK2021Core\User\Login\TokenEntity;
use InteractivePlus\PDK2021Core\User\Login\TokenEntityStorage;
use MysqliDb;

class TokenEntityStorageMySQLImpl extends TokenEntityStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    public function __construct(MysqliDb $db)
    {
        $this->db = $db;
    }
    public function createTables() : void{
        $ipMaxLen = IPFormat::IPV6_STR_MAX_LEN;
        $tokenMaxLen = TokenFormat::getTokenStringLength();
        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `login_infos` (
                `related_uid` INT UNSIGNED NOT NULL,
                `access_token` CHAR({$tokenMaxLen}) NOT NULL,
                `refresh_token` CHAR({$tokenMaxLen}) NOT NULL,
                `issue_time` INT UNSIGNED NOT NULL,
                `expire_time` INT UNSIGNED NOT NULL,
                `last_renew_time` INT UNSIGNED NOT NULL,
                `refresh_expire_time` INT UNSIGNED NOT NULL,
                `remote_addr` VARCHAR({$ipMaxLen}),
                `device_ua` TINYTEXT,
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
            'TRUNCATE TABLE `login_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `login_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    protected function __addTokenEntity(TokenEntity $Token) : void{
        $dataToAdd = array(
            'related_uid' => $Token->getRelatedUID(),
            'access_token' => $Token->getTokenStr(),
            'refresh_token' => $Token->getRefreshTokenStr(),
            'issue_time' => $Token->issueTime,
            'expire_time' => $Token->expireTime,
            'last_renew_time' => $Token->lastRenewTime,
            'refresh_expire_time' => $Token->refreshTokenExpireTime,
            'remote_addr' => $Token->getRemoteAddr(),
            'device_ua' => $Token->getDeviceUA(),
            'valid' => $Token->valid ? 1 : 0
        );
        $result = $this->db->insert('login_infos',$dataToAdd);
        if(!$result){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function checkTokenExist(string $TokenString) : bool{
        $this->db->where('access_token',$TokenString);
        $count = $this->db->getValue('login_infos','count(*)');
        return $count >= 1;
    }
    public function checkRefreshTokenExist(string $RefreshTokenString) : bool{
        $this->db->where('refresh_token',$RefreshTokenString);
        $count = $this->db->getValue('login_infos','count(*)');
        return $count >= 1;
    }
    protected function getTokenEntityFromDatabaseRow(array $dataRow) : TokenEntity{
        $newEntity = new TokenEntity(
            $dataRow['related_uid'],
            $dataRow['issue_time'],
            $dataRow['expire_time'],
            $dataRow['refresh_expire_time'],
            $dataRow['last_renew_time'],
            empty($dataRow['remote_addr']) ? null : $dataRow['remote_addr'],
            empty($dataRow['device_ua']) ? null : $dataRow['device_ua'],
            $dataRow['access_token'],
            $dataRow['refresh_token'],
            $dataRow['valid'] === 1
        );
        return $newEntity;
    }
    public function getTokenEntity(string $TokenString) : ?TokenEntity{
        $this->db->where('access_token',$TokenString);
        $result = $this->db->getOne('login_infos');
        if(!$result){
            return null;
        }
        return $this->getTokenEntityFromDatabaseRow($result);
    }
    public function getTokenEntitybyRefreshToken(string $refreshToken) : ?TokenEntity{
        $this->db->where('refresh_token',$refreshToken);
        $result = $this->db->getOne('login_infos');
        if(!$result){
            return null;
        }
        return $this->getTokenEntityFromDatabaseRow($result);
    }

    public function updateTokenEntity(TokenEntity $Token) : bool{
        $dataToUpdate = array(
            'issue_time' => $Token->issueTime,
            'expire_time' => $Token->expireTime,
            'last_renew_time' => $Token->lastRenewTime,
            'refresh_expire_time' => $Token->refreshTokenExpireTime,
            'remote_addr' => $Token->getRemoteAddr(),
            'device_ua' => $Token->getDeviceUA(),
            'valid' => $Token->valid ? 1 : 0
        );
        $this->db->where('access_token',$Token->getTokenStr());
        $result = $this->db->update('login_infos',$dataToUpdate);
        if(!$result){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $this->db->count >= 1;
    }
    
    public function setTokenEntityInvalid(string $TokenString) : void{
        $dataToUpdate = array(
            'valid' => 0
        );
        $this->db->where('access_token',$TokenString);
        $result = $this->db->update('login_infos',$dataToUpdate);
        if(!$result){
            throw new PDKStorageEngineError('failed to insert data into database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return;
    }

    public function searchToken(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $lastRenewTimeMin = 0, int $lastRenewTimeMax = 0, int $refreshExpireMin = 0, int $refreshExpireMax = 0, int $uid = UserSystemConstants::NO_USER_RELATED_UID) : MultipleResult{
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
        if($uid > 0){
            $this->db->where('related_uid',$uid);
        }
        $result = $this->db->withTotalCount()->get('login_infos');
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $resultObjArr[] = $this->getTokenEntityFromDatabaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }

    public function clearToken(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $lastRenewTimeMin = 0, int $lastRenewTimeMax = 0, int $refreshExpireMin = 0, int $refreshExpireMax = 0, int $uid = UserSystemConstants::NO_USER_RELATED_UID) : void{
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
        if($uid > 0){
            $this->db->where('related_uid',$uid);
        }
        $result = $this->db->delete('login_infos');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    
    public function getTokenCount(int $issueTimeMin = 0, int $issueTimeMax = 0, int $expireTimeMin = 0, int $expireTimeMax =0, int $lastRenewTimeMin = 0, int $lastRenewTimeMax = 0, int $refreshExpireMin = 0, int $refreshExpireMax = 0, int $uid = UserSystemConstants::NO_USER_RELATED_UID) : int{
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
        if($uid > 0){
            $this->db->where('related_uid',$uid);
        }
        $result = $this->db->getValue('login_infos','count(*)');
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result;
    }

}