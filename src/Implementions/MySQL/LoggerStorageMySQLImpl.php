<?php
namespace InteractivePlus\PDK2021\Implementions\MySQL;

use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Formats\IPFormat;
use InteractivePlus\PDK2021Core\Base\Logger\LogEntity;
use InteractivePlus\PDK2021Core\Base\Logger\LoggerStorage;
use InteractivePlus\PDK2021Core\Base\Logger\PDKLogLevel;
use MysqliDb;

class LoggerStorageMySQLImpl extends LoggerStorage implements MySQLStorageImpl{
    private MysqliDb $db;
    public function __construct(MysqliDb $db)
    {
        $this->db = $db;
    }
    public function createTables() : void{
        $ipMaxLen = IPFormat::IPV6_STR_MAX_LEN;
        $createResult = $this->db->rawQuery(
            "CREATE TABLE IF NOT EXISTS `logs` (
                `action_id` INT NOT NULL,
                `app_uid` INT UNSIGNED,
                `user_uid` INT UNSIGNED,
                `log_time` INT UNSIGNED NOT NULL,
                `log_level` TINYINT UNSIGNED NOT NULL,
                `log_message` TINYTEXT,
                `operation_success` TINYINT(1),
                `pdk_exception_code` INT NOT NULL,
                `client_addr` VARCHAR({$ipMaxLen}),
                `log_context` BLOB
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $deleteResult = $this->db->rawQuery(
            'TRUNCATE TABLE `logs`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteTables() : void{
        $deleteResult = $this->db->rawQuery(
            'DROP TABLE `logs`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function addLogItem(LogEntity $logEntity) : void{
        $dataToAdd = array(
            'action_id' => $logEntity->actionID,
            'app_uid' => $logEntity->getAPPUID() === APPSystemConstants::NO_APP_RELATED_APPUID ? null : $logEntity->getAPPUID(),
            'user_uid' => $logEntity->getUID() === UserSystemConstants::NO_USER_RELATED_UID ? null : $logEntity->getUID(),
            'log_time' => $logEntity->time,
            'log_level' => $logEntity->getLogLevel(),
            'log_message' => $logEntity->message,
            'operation_success' => $logEntity->success ? 1 : 0,
            'pdk_exception_code' => $logEntity->PDKExceptionCode,
            'client_addr' => $logEntity->getClientAddr(),
            'log_context' => empty($logEntity->getContexts()) ? null : gzcompress(json_encode($logEntity->getContexts()))
        );
        $addRst = $this->db->insert('logs',$dataToAdd);
        if(!$addRst){
            throw new PDKStorageEngineError('Failed to insert data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function deleteLogItems(int $fromTime = -1, int $toTime = -1, int $highestLogLevel = PDKLogLevel::INFO) : void{
        if($fromTime > 0){
            $this->db->where('log_time',$fromTime,'>=');
        }
        if($toTime >= 0){
            $this->db->where('log_time',$toTime,'<=');
        }
        $this->db->where('log_level',$highestLogLevel,'<=');
        $this->db->delete('logs');
    }
    protected function LogEntityFromDataRow(array $dataRow) : LogEntity{
        $newEntity = new LogEntity(
            $dataRow['action_id'],
            empty($dataRow['app_uid']) ? APPSystemConstants::NO_APP_RELATED_APPUID : $dataRow['app_uid'],
            empty($dataRow['user_uid']) ? UserSystemConstants::NO_USER_RELATED_UID : $dataRow['user_uid'],
            $dataRow['log_time'],
            $dataRow['log_level'],
            $dataRow['operation_success'] == 1,
            $dataRow['pdk_exception_code'],
            empty($dataRow['client_addr']) ? null : $dataRow['client_addr'],
            empty($dataRow['log_message']) ? null : $dataRow['log_message'],
            empty($dataRow['log_context']) ? null : gzuncompress($dataRow['log_context'])
        );
        return $newEntity;
    }
    public function getLogItemsBetween(int $fromTime = -1, int $toTime = -1, int $offset = 0, int $count = -1, int $lowestLogLevel = PDKLogLevel::NOTICE) : MultipleResult{
        if($fromTime > 0){
            $this->db->where('log_time',$fromTime,'>=');
        }
        if($toTime >= 0){
            $this->db->where('log_time',$toTime,'<=');
        }
        $this->db->where('log_level',$lowestLogLevel,'>=');
        $numRows = null;
        if($count > 0){
            $numRows = array($offset,$count);
        }
        $result = $this->db->withTotalCount()->get('logs',$numRows);
        if(!$result){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultOBJArray = array();
        foreach ($result as $singleDataRow){
            $resultOBJArray[] = $this->LogEntityFromDataRow($singleDataRow);
        }
        $returnResult = new MultipleResult(
            $this->db->count,
            $resultOBJArray,
            $this->db->totalCount,
            $count > 0 ? $offset : 0
        );
        return $returnResult;
    }
    public function getLogCount(int $fromTime = -1, int $toTime = -1, int $lowestLogLevel = PDKLogLevel::NOTICE) : int{
        if($fromTime > 0){
            $this->db->where('log_time',$fromTime,'>=');
        }
        if($toTime >= 0){
            $this->db->where('log_time',$toTime,'<=');
        }
        $this->db->where('log_level',$lowestLogLevel,'>=');
        $count = $this->db->getValue('logs','count(*)');
        return $count;
    }
    
}