<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemNotFoundError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Formats\IPFormat;
use InteractivePlus\PDK2021Core\User\Formats\UserFormat;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntityStorage;
use InteractivePlus\PDK2021Core\User\UserSystemFormatSetting;
use libphonenumber\PhoneNumber;
use MysqliDb;

class UserEntityStorageMySQLImpl extends UserEntityStorage implements MySQLStorageImpl{
    const PHONE_DEFAULT_COUNTRY = \LibI18N\Region::REGION_CN;

    private MysqliDb $db;

    public function __construct(MysqliDb $db, UserSystemFormatSetting $formatSetting){
        parent::__construct($formatSetting);
        $this->db = $db;
    }

    public function createTables() : void{
        $ipMaxLen = IPFormat::IPV6_STR_MAX_LEN;
        $pwdHashStrLen = UserFormat::PASSWORD_HASH_LEN;
        $phoneNumStrLen = UserPhoneUtil::E164_FORMAT_STR_MAX_LENGTH;
        $usernameStrLen = $this->getFormatSetting()->getUserNameMaxLen();
        $nicknameStrLen = $this->getFormatSetting()->getNickNameMaxLen();
        $signatureStrLen = $this->getFormatSetting()->getSignatureMaxLen();
        $emailStrLen = $this->getFormatSetting()->getEmailAddrMaxLen();
        

        $mysqli = $this->db->mysqli();
        
        $createResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `user_infos` (
                `uid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR({$usernameStrLen}) NOT NULL,
                `nickname` VARCHAR({$nicknameStrLen}),
                `signature` VARCHAR({$signatureStrLen}),
                `password` CHAR({$pwdHashStrLen}) NOT NULL,
                `email` VARCHAR({$emailStrLen}),
                `phone` VARCHAR({$phoneNumStrLen}),
                `email_verified` TINYINT(1) NOT NULL,
                `phone_verified` TINYINT(1) NOT NULL,
                `create_time` INT UNSIGNED NOT NULL,
                `create_client_addr` VARCHAR({$ipMaxLen}) NOT NULL,
                `frozen` TINYINT(1) NOT NULL,
                PRIMARY KEY ( `uid`, `username` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `user_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `user_infos`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    protected function __addUserEntity(UserEntity $userEntity) : int{
        $dataToInsert = array(
            'username' => $userEntity->getUsername(),
            'nickname' => $userEntity->getNickName(),
            'signature' => $userEntity->getSignature(),
            'password' => $userEntity->getPasswordHash(),
            'email' => $userEntity->getEmail(),
            'phone' => $userEntity->getPhoneNumber() === null ? null : UserPhoneUtil::outputPhoneNumberE164($userEntity->getPhoneNumber()),
            'email_verified' => $userEntity->isEmailVerified() ? 1 : 0,
            'phone_verified' => $userEntity->isPhoneVerified() ? 1 : 0,
            'create_time' => $userEntity->getAccountCreateTime(),
            'create_client_addr' => $userEntity->getAccountCreateIP(),
            'frozen' => $userEntity->isAccountFrozen() ? 1 : 0
        );
        $insertResult = $this->db->insert('user_infos',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            return $insertResult;
        }
    }
    protected function __updateUserEntity(UserEntity $user) : bool{
        if($user->getUID() < 0){
            return false;
        }
        $dataToUpdate = array(
            'username' => $user->getUsername(),
            'nickname' => $user->getNickName(),
            'signature' => $user->getSignature(),
            'password' => $user->getPasswordHash(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhoneNumber() === null ? null : UserPhoneUtil::outputPhoneNumberE164($user->getPhoneNumber()),
            'email_verified' => $user->isEmailVerified() ? 1 : 0,
            'phone_verified' => $user->isPhoneVerified() ? 1 : 0,
            'create_time' => $user->getAccountCreateTime(),
            'create_client_addr' => $user->getAccountCreateIP(),
            'frozen' => $user->isAccountFrozen() ? 1 : 0
        );
        $this->db->where('uid',$user->getUID());
        $result = $this->db->update('user_infos',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $this->db->count >= 1;
    }
    public function checkUsernameExist(string $username) : int{
        $this->db->where('username',$username);
        $uid = $this->db->getValue('user_infos','uid');
        return $uid === null ? -1 : $uid;
    }
    public function checkEmailExist(string $email) : int{
        $this->db->where('email',$email);
        $uid = $this->db->getValue('user_infos','uid');
        return $uid === null ? -1 : $uid;
    }
    public function checkPhoneNumExist(PhoneNumber $phoneNumber) : int{
        $this->db->where('phone',UserPhoneUtil::outputPhoneNumberE164($phoneNumber));
        $uid = $this->db->getValue('user_infos','uid');
        return $uid === null ? -1 : $uid;
    }

    public function checkNicknameExist(string $nickname) : int{
        $this->db->where('nickname',$nickname);
        $uid = $this->db->getValue('user_infos','uid');
        return $uid === null ? -1 : $uid;
    }

    protected function getUserEntityByDatabaseRow(array $dataRow) : UserEntity{
        $newEntity = UserEntity::fromDatabase(
            $dataRow['uid'],
            $dataRow['username'],
            $dataRow['nickname'],
            $dataRow['signature'],
            $dataRow['password'],
            $dataRow['email'],
            empty($dataRow['phone']) ? null : UserPhoneUtil::parsePhone($dataRow['phone'],self::PHONE_DEFAULT_COUNTRY),
            $dataRow['email_verified'] === 1 ? true : false,
            $dataRow['phone_verified'] === 1 ? true : false,
            $dataRow['create_time'],
            $dataRow['create_client_addr'],
            $dataRow['frozen'] === 1 ? true : false,
            $this->getFormatSetting()
        );
        return $newEntity;
    }

    public function getUserEntityByUsername(string $username) : ?UserEntity{
        $this->db->where('username',$username);
        $singleRow = $this->db->getOne('user_infos');
        if(!$singleRow){
            return null;
        }
        return $this->getUserEntityByDatabaseRow($singleRow);
    }
    public function getUserEntityByEmail(string $email) : ?UserEntity{
        $this->db->where('email',$email);
        $singleRow = $this->db->getOne('user_infos');
        if(!$singleRow){
            return null;
        }
        return $this->getUserEntityByDatabaseRow($singleRow);
    }
    public function getUserEntityByPhoneNum(PhoneNumber $phoneNumber) : ?UserEntity{
        $this->db->where('phone',UserPhoneUtil::outputPhoneNumberE164($phoneNumber));
        $singleRow = $this->db->getOne('user_infos');
        if(!$singleRow){
            return null;
        }
        return $this->getUserEntityByDatabaseRow($singleRow);
    }
    public function getUserEntityByUID(int $uid) : ?UserEntity{
        $this->db->where('uid',$uid);
        $singleRow = $this->db->getOne('user_infos');
        if(!$singleRow){
            return null;
        }
        return $this->getUserEntityByDatabaseRow($singleRow);
    }
    public function searchUserIdentity(?string $username = null, ?string $email = null, ?string $number = null, int $regTimeStart = -1, int $regTimeEnd = -1, int $dataOffset = 0, int $dataCountLimit = -1) : MultipleResult{
        if(!empty($username)){
            $this->db->where('username','%' . $username '%','LIKE');
        }
        if(!empty($email)){
            $this->db->where('email','%' . $email . '%','LIKE');
        }
        if(!empty($number)){
            $this->db->where('phone','%' . $number . '%','LIKE');
        }
        if($regTimeStart > 0){
            $this->db->where('create_time',$regTimeStart,'>=');
        }
        if($regTimeEnd >= 0){
            $this->db->where('create_time',$regTimeEnd,'<=');
        }
        $dataLimit = null;
        if($dataCountLimit != -1){
            $dataLimit = array($dataOffset, $dataCountLimit);            
        }
        $result = $this->db->withTotalCount()->get('user_infos',$dataLimit);
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $objResultArr = array();
        foreach($result as $singleRow){
            $objResultArr[] = $this->getUserEntityByDatabaseRow($singleRow);
        }
        return new MultipleResult(
            $this->db->count,
            $objResultArr,
            $this->db->totalCount,
            $dataCountLimit != -1 ? $dataOffset : 0
        );
    }

    public function getUserCount(?string $username = null, ?string $email = null, ?string $number = null, int $regTimeStart = -1, int $regTimeEnd = -1) : int{
        if(!empty($username)){
            $this->db->where('username',$username,'LIKE');
        }
        if(!empty($email)){
            $this->db->where('email',$email,'LIKE');
        }
        if(!empty($number)){
            $this->db->where('phone',$number,'LIKE');
        }
        if($regTimeStart > 0){
            $this->db->where('create_time',$regTimeStart,'>=');
        }
        if($regTimeEnd >= 0){
            $this->db->where('create_time',$regTimeEnd,'<=');
        }
        $result = $this->db->getValue('user_infos','count(*)');
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result;
    }

    public function deleteUserEntity(UserEntity $user) : void{
        if($user->getUID() < 0){
            return;
        }
        $this->db->where('uid',$user->getUID());
        $result = $this->db->delete('user_infos');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
}