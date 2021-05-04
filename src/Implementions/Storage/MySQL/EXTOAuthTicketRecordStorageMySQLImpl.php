<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;

use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\EXT_Ticket\OAuthTicketFormat;
use InteractivePlus\PDK2021Core\EXT_Ticket\OAuthTicketFormatSetting;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketRecordEntity;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketRecordStorage;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketSingleContent;
use MysqliDb;

use function PHPSTORM_META\map;

class EXTOAuthTicketRecordStorageMySQLImpl extends OAuthTicketRecordStorage{
    private MysqliDb $db;
    private OAuthTicketFormatSetting $_formatSetting;
    public function __construct(MysqliDb $db, OAuthTicketFormatSetting $formatSetting)
    {
        $this->db = $db;
        $this->_formatSetting = $formatSetting;
    }
    public function createTables() : void{
        $clientIDLen = APPFormat::getAPPIDStringLength();
        $maskIDLen = MaskIDFormat::getMaskIDStringLength();
        $accessTokenLen = APPFormat::getAPPAccessTokenStringLength();
        $ticketIDLen = OAuthTicketFormat::getTicketIDLength();
        $ticketTitleLen = $this->getFormatSetting()->getTitleMaxLen();
        $responderNameLen = $this->getFormatSetting()->getResponderNameMaxLen();

        $mysqli = $this->db->mysqli();
        
        $createMainTableResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `oauth_ext_ticket_records` (
                `ticket_id` CHAR({$ticketIDLen}) NOT NULL,
                `title` VARCHAR({$ticketTitleLen}),
                `appuid` INT UNSIGNED NOT NULL,
                `client_id` CHAR({$clientIDLen}),
                `uid` INT UNSIGNED NOT NULL,
                `mask_id` CHAR({$maskIDLen}),
                `access_token` CHAR({$accessTokenLen}),
                `is_urgent` TINYINT(1) NOT NULL,
                `created` INT UNSIGNED NOT NULL,
                `last_updated` INT UNSIGNED NOT NULL,
                PRIMARY KEY ( `ticket_id` )
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createMainTableResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $createIndependentRecordTableResult = $mysqli->query(
            "CREATE TABLE IF NOT EXISTS `oauth_ext_ticket_response_records` (
                `related_ticket_id` CHAR({$ticketIDLen}) NOT NULL,
                `content` TEXT,
                `is_creator_content` TINYINT(1) NOT NULL,
                `responder_name` VARCHAR({$responderNameLen}),
                `responded` INT UNSIGNED NOT NULL,
                `last_edited` INT UNSIGNED NOT NULL
            )ENGINE=InnoDB CHARSET=utf8;"
        );
        if(!$createIndependentRecordTableResult){
            throw new PDKStorageEngineError('Failed to create table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function clearTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'TRUNCATE TABLE `oauth_ext_ticket_records`;'
        );

        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }

        $deleteIndependentRecordTableResult = $mysqli->query(
            'TRUNCATE TABLE `oauth_ext_ticket_response_records`;'
        );
        if(!$deleteIndependentRecordTableResult){
            throw new PDKStorageEngineError('Failed to clear table data',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }

    }
    public function deleteTables() : void{
        $mysqli = $this->db->mysqli();
        
        $deleteResult = $mysqli->query(
            'DROP TABLE `oauth_ext_ticket_records`;'
        );
        if(!$deleteResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }

        $deleteIndependentRecordTableResult = $mysqli->query(
            'DROP TABLE `oauth_ext_ticket_response_records`;'
        );
        if($deleteIndependentRecordTableResult){
            throw new PDKStorageEngineError('Failed to drop table',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function __addOAuthTicketRecord(OAuthTicketRecordEntity $entity) : void{
        $dataToInsert = array(
            'ticket_id' => $entity->getTicketID(),
            'title' => $entity->getTicketTitle(),
            'appuid' => $entity->appuid,
            'client_id' => $entity->getClientID(),
            'uid' => $entity->uid,
            'mask_id' => $entity->getMaskID(),
            'access_token' => $entity->getAccessToken(),
            'is_urgent' => $entity->isUrgent ? 1 : 0,
            'created' => $entity->createTime,
            'last_updated' => $entity->lastUpdateTime
        );
        $insertResult = $this->db->insert('oauth_ext_ticket_records',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            return;
        }
        foreach($entity->contentListing as $content){
            $this->insertOAuthReponse($entity->getTicketID(),$content);
        }
    }

    protected function insertOAuthReponse(string $ticket_id, OAuthTicketSingleContent $singleContent) : void{
        $dataToInsert = array(
            'related_ticket_id' => $ticket_id,
            'content' => $singleContent->getContentStr(),
            'is_creator_content' => $singleContent->isTicketCreatorContent ? 1 : 0,
            'responder_name' => $singleContent->getResponderName(),
            'responded' => $singleContent->respondTime,
            'last_edited' => $singleContent->lastEditTime
        );
        $insertResult = $this->db->insert('oauth_ext_ticket_response_records',$dataToInsert);
        if(!$insertResult){
            throw new PDKStorageEngineError('failed to insert data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }else{
            return;
        }
    }

    protected function getTicketRecordEntityByDatabaseRow(array $dataRow, array $responseRows) : OAuthTicketRecordEntity{
        $allTicketResponses = [];
        foreach($responseRows as $singleResponseRow){
            $allTicketResponses[] = $this->getTicketResponseEntityByDatabaseRow($singleResponseRow);
        }
        $newEntity = new OAuthTicketRecordEntity(
            $dataRow['title'],
            $allTicketResponses,
            $dataRow['ticket_id'],
            $dataRow['appuid'],
            $dataRow['client_id'],
            $dataRow['uid'],
            $dataRow['mask_id'],
            $dataRow['access_token'],
            $dataRow['is_urgent'] === 1 ? true : false, 
            $dataRow['created'],
            $dataRow['last_updated'],
            $this->getFormatSetting()
        );
        return $newEntity;
    }

    protected function getTicketResponseEntityByDatabaseRow(array $dataRow) : OAuthTicketSingleContent{
        $newEntity = new OAuthTicketSingleContent(
            $dataRow['content'],
            $dataRow['is_creator_content'] === 1 ? true : false,
            $dataRow['responder_name'],
            intval($dataRow['responded']),
            intval($dataRow['last_edited']),
            $this->getFormatSetting()
        );
        return $newEntity;
    }

    protected function getTicketResponseRows(string $ticketID) : array{
        $this->db->where('related_ticket_id',$ticketID);
        $rows = $this->db->get('oauth_ext_ticket_response_records');
        if($rows === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $rows;
    }

    public function checkOAuthTicketRecordExist(string $ticket_id) : bool{
        $this->db->where('ticket_id',$ticket_id);
        $result = $this->db->getValue('oauth_ext_ticket_records','count(*)');
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $result >= 1;
    }
    public function getOAuthTicketRecord(string $ticket_id) : ?OAuthTicketRecordEntity{
        $this->db->where('ticket_id',$ticket_id);
        $result = $this->db->getOne('oauth_ext_ticket_records');
        if(!$result){
            return null;
        }
        $allResponseRows = $this->getTicketResponseRows($ticket_id);
        return $this->getTicketRecordEntityByDatabaseRow($result,$allResponseRows);
    }
    public function updateOAuthTicketRecord(OAuthTicketRecordEntity $entity) : void{
        $dataToUpdate = array(
            'title' => $entity->getTicketTitle(),
            'appuid' => $entity->appuid,
            'client_id' => $entity->getClientID(),
            'uid' => $entity->uid,
            'mask_id' => $entity->getMaskID(),
            'access_token' => $entity->getAccessToken(),
            'is_urgent' => $entity->isUrgent ? 1 : 0,
            'created' => $entity->createTime,
            'last_update' => $entity->lastUpdateTime
        );
        $this->db->where('ticket_id',$entity->getTicketID());

        $result = $this->db->update('oauth_ext_ticket_records',$dataToUpdate, 1);
        if(!$result){
            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }

    public function updateOAuthTicketResponses(OAuthTicketRecordEntity $entity) : void{
        $this->db->where('related_ticket_id',$entity->getTicketID());
        $responseDBRows = $this->db->get(
            'oauth_ext_ticket_response_records',
            null,
            array(
                'is_creator_content',
                'responder_name',
                'responded',
                'last_edited'
            )
        );
        if($responseDBRows === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }

        $tempResultDBRows = $responseDBRows;

        //Finish fetching $responseDBRows, compare it with $entity->contentListing
        //Check existing entity in $entity->contentListing first
        foreach($entity->contentListing as $entitySingleContent){
            //Check if it exists in responseDBRows
            $found = false;
            foreach($tempResultDBRows as $dbSingleKey => $dbSingleRow){
                if(
                    $entitySingleContent->respondTime === $dbSingleRow['responded'] &&
                    $entitySingleContent->isTicketCreatorContent === ($dbSingleRow['is_creator_content'] === 1 ? true : false) &&
                    $entitySingleContent->getResponderName() === $dbSingleRow['responder_name']
                ){
                    $found = true;
                    //Check if need update
                    if($entitySingleContent->lastEditTime !== $dbSingleRow['last_edited']){
                        //Update this row
                        $this->db->where('responded',$dbSingleRow['responded']);
                        $this->db->where('is_creator_content',$dbSingleRow['is_creator_content']);
                        $this->db->where('responder_name',$dbSingleRow['responder_name']);
                        $this->db->where('last_edited',$dbSingleRow['last_edited']);
                        $updateArray = array(
                            'content' => $entitySingleContent->getContentStr(),
                            'last_edited' => $entitySingleContent->lastEditTime
                        );
                        $updateRst = $this->db->update('oauth_ext_ticket_response_records',$updateArray,1);
                        if(!$updateRst){
                            throw new PDKStorageEngineError('failed to update data to database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
                        }
                    }
                    unset($tempResultDBRows[$dbSingleKey]);
                    break;
                }
            }
            if(!$found){
                //Add this content to DB
                $this->insertOAuthReponse($entity->getTicketID(),$entitySingleContent);
            }
        }

        //Any db rows not deleted in $tempResultDBRows should be deleted from DB.
        foreach($tempResultDBRows as $dbSingleKey => $dbSingleRow){
            $this->db->where('responded',$dbSingleRow['responded']);
            $this->db->where('is_creator_content',$dbSingleRow['is_creator_content']);
            $this->db->where('responder_name',$dbSingleRow['responder_name']);
            $this->db->where('last_edited',$dbSingleRow['last_edited']);
            $deleteRst = $this->db->delete('oauth_ext_ticket_response_records');
            if(!$deleteRst){
                throw new PDKStorageEngineError('failed to delete data in database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
            }
        }

    }

    public function searchOAuthTicketRecordEntity(
        int $createTimeStart = -1,
        int $createTimeEnd = -1, 
        int $lastUpdateStart = -1, 
        int $lastUpdateEnd = -1, 
        int $relatedUID = UserSystemConstants::NO_USER_RELATED_UID,
        ?string $relatedMaskID = null,
        ?string $relatedClientID = null,
        int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID, 
        ?string $relatedAccessToken = null,
        int $dataOffset = 0, 
        int $dataCountLimit = -1
    ) : MultipleResult{
        /*
        `ticket_id` CHAR({$ticketIDLen}) NOT NULL,
        `title` VARCHAR({$ticketTitleLen}),
        `appuid` INT UNSIGNED NOT NULL,
        `client_id` CHAR({$clientIDLen}),
        `uid` INT UNSIGNED NOT NULL,
        `mask_id` CHAR({$maskIDLen}),
        `access_token` CHAR({$accessTokenLen}),
        `is_urgent` TINYINT(1) NOT NULL,
        `created` INT UNSIGNED NOT NULL,
        `last_updated` INT UNSIGNED NOT NULL,
        */
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
        if($relatedUID !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('uid',$relatedUID);
        }
        if($relatedMaskID !== null){
            $this->db->where('mask_id',$relatedMaskID);
        }
        if($relatedClientID !== null){
            $this->db->where('client_id',$relatedClientID);
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        if($relatedAccessToken !== null){
            $this->db->where('access_token',$relatedAccessToken);
        }

        $dataLimit = null;
        if($dataCountLimit != -1){
            $dataLimit = array($dataOffset, $dataCountLimit);            
        }
        $result = $this->db->withTotalCount()->get('oauth_ext_ticket_records',$dataLimit);
        if($result === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        $resultObjArr = array();
        foreach($result as $singleRow){
            $currentTicketRelatedContentRows = $this->getTicketResponseRows($singleRow['ticket_id']);
            $resultObjArr[] = $this->getTicketRecordEntityByDatabaseRow($singleRow,$currentTicketRelatedContentRows);
        }
        return new MultipleResult(
            $this->db->count,
            $resultObjArr,
            $this->db->totalCount,
            0
        );
    }
    public function getOAuthTicketRecordEntityCount(
        int $createTimeStart = -1,
        int $createTimeEnd = -1, 
        int $lastUpdateStart = -1, 
        int $lastUpdateEnd = -1, 
        int $relatedUID = UserSystemConstants::NO_USER_RELATED_UID,
        ?string $relatedMaskID = null,
        ?string $relatedClientID = null,
        int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID, 
        ?string $relatedAccessToken = null
    ) : int{
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
        if($relatedUID !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('uid',$relatedUID);
        }
        if($relatedMaskID !== null){
            $this->db->where('mask_id',$relatedMaskID);
        }
        if($relatedClientID !== null){
            $this->db->where('client_id',$relatedClientID);
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        if($relatedAccessToken !== null){
            $this->db->where('access_token',$relatedAccessToken);
        }
        $count = $this->db->getValue('oauth_ext_ticket_records','count(*)');
        if($count === null){
            throw new PDKStorageEngineError('failed to fetch data from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
        return $count;
    }
    public function clearTicketRecord(
        int $createTimeStart = -1,
        int $createTimeEnd = -1, 
        int $lastUpdateStart = -1, 
        int $lastUpdateEnd = -1, 
        int $relatedUID = UserSystemConstants::NO_USER_RELATED_UID,
        ?string $relatedMaskID = null,
        ?string $relatedClientID = null,
        int $relatedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID, 
        ?string $relatedAccessToken = null,
        int $dataOffset = 0, 
        int $dataCountLimit = -1
    ) : void{
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
        if($relatedUID !== UserSystemConstants::NO_USER_RELATED_UID){
            $this->db->where('uid',$relatedUID);
        }
        if($relatedMaskID !== null){
            $this->db->where('mask_id',$relatedMaskID);
        }
        if($relatedClientID !== null){
            $this->db->where('client_id',$relatedClientID);
        }
        if($relatedAPPUID !== APPSystemConstants::NO_APP_RELATED_APPUID){
            $this->db->where('appuid',$relatedAPPUID);
        }
        if($relatedAccessToken !== null){
            $this->db->where('access_token',$relatedAccessToken);
        }
        $result = $this->db->delete('oauth_ext_ticket_records');
        if(!$result){
            throw new PDKStorageEngineError('failed to delete from database',MySQLErrorParams::paramsFromMySQLiDBObject($this->db));
        }
    }
    public function getFormatSetting() : OAuthTicketFormatSetting{
        return $this->_formatSetting;
    }
}