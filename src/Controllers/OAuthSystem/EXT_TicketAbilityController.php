<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\OutputUtils\MultipleResultOutputUtil;
use InteractivePlus\PDK2021\OutputUtils\TicketOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Formats\IPFormat;
use InteractivePlus\PDK2021Core\EXT_Ticket\OAuthTicketFormat;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketRecordEntity;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketSingleContent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EXT_TicketAbilityController{
    public function createTicket(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_TITLE = $REQ_PARAMS['title'];
        $REQ_CONTENT = $REQ_PARAMS['content'];
        $REQ_UID = $REQ_PARAMS['uid']; //Only Present if client_id is empty
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token']; //OAuth/Frontend Token
        $REQ_IS_FRONTEND_TOKEN = $REQ_PARAMS['is_frontend_token'];
        $REQ_CAPTCHA_ID = $REQ_PARAMS['captcha_id'];

        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        
        $TicketEntityStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        $TicketFormat = $TicketEntityStorage->getFormatSetting();
        if(empty($REQ_TITLE) || !is_string($REQ_TITLE) || !$TicketFormat->checkTitle($REQ_TITLE)){
            return ReturnableResponse::fromIncorrectFormattedParam('title')->toResponse($response);
        }
        if(empty($REQ_CONTENT) || !is_string($REQ_CONTENT) || !$TicketFormat->checkContent($REQ_CONTENT)){
            return ReturnableResponse::fromIncorrectFormattedParam('content')->toResponse($response);
        }

        $captchaResult = CommonFunction::useAndCheckCaptchaResult($REQ_CAPTCHA_ID);
        if($captchaResult !== null){
            return $captchaResult->toResponse($response);
        }
        
        $actualAPPUID = 0;
        $actualClientID = null;
        $actualMaskID = null;
        $actualUID = null;
        $actualOAuthToken = null;

        $actualMaskEntity = null;
        $actualUserEntity = null;

        if($REQ_IS_FRONTEND_TOKEN){
            if(empty($REQ_UID) || intval($REQ_UID) < 0){
                return ReturnableResponse::fromIncorrectFormattedParam('uid')->toResponse($response);
            }else{
                $REQ_UID = intval($REQ_UID);
            }
            $actualAPPUID = APPSystemConstants::INTERACTIVEPDK_APPUID;
            $actualUID = $REQ_UID;
            $actualUserEntity = PDK2021Wrapper::$pdkCore->getUserEntityStorage()->getUserEntityByUID($actualUID);
            //check if frontend token is right
            $tokenValidResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
            if($tokenValidResponse !== null){
                return $tokenValidResponse->toResponse($response);
            }
        }else{
            $appTokenValidResponse = CommonFunction::checkAPPTokenValidResponse($REQ_ACCESS_TOKEN,$ctime);
            if(!$appTokenValidResponse->succeed){
                return $appTokenValidResponse->returnableResponse->toResponse($response);
            }
            $actualAPPUID = $appTokenValidResponse->tokenEntity->appuid;
            $actualClientID = $appTokenValidResponse->tokenEntity->getClientID();
            $actualMaskID = $appTokenValidResponse->tokenEntity->getMaskID();
            $actualMaskEntity = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage()->getMaskIDEntityByMaskID($actualMaskID);
            $actualUID = $actualMaskEntity->uid;
            $actualUserEntity = PDK2021Wrapper::$pdkCore->getUserEntityStorage()->getUserEntityByUID($actualUID);
            $actualOAuthToken = $REQ_ACCESS_TOKEN;
        }

        
        $ticketContents = [
            new OAuthTicketSingleContent(
                $REQ_CONTENT,
                true,
                null,
                $ctime,
                $ctime,
                $TicketEntityStorage->getFormatSetting()
            )
        ];
        $TicketEntity = new OAuthTicketRecordEntity(
            $REQ_TITLE,
            $ticketContents,
            OAuthTicketFormat::generateTicketID(),
            $actualAPPUID,
            $actualClientID,
            $actualUID,
            $actualMaskID,
            $actualOAuthToken,
            false,
            $ctime,
            $ctime,
            false,
            false,
            $TicketEntityStorage->getFormatSetting()
        );
        //Put Ticket Into DB
        $returnedTicketEntity = null;
        try{
            $returnedTicketEntity = $TicketEntityStorage->addOAuthTicketRecord($TicketEntity,true);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($returnedTicketEntity === null){
            return ReturnableResponse::fromInnerError('Error when trying to save ticket record into database')->toResponse($response);
        }

        //return created Ticket
        $returnResponse = new ReturnableResponse(201,0);
        $returnResponse->returnDataLevelEntries['ticket'] = TicketOutputUtil::getTicketAsAssoc($returnedTicketEntity);
        return $returnResponse->toResponse($response);
    }
    public function listOwnedTickets(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_UID = $REQ_PARAMS['uid']; //Only present if using frontend token
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_IS_FRONTEND_TOKEN = $REQ_PARAMS['is_frontend_token'];
        $REQ_MASKID = $REQ_PARAMS['mask_id']; //Used as filter if UID is present, optional
        $REQ_CLIENT_ID = $REQ_PARAMS['client_id']; //Used as filter if UID is present, optional
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        
        $searchMaskIDParam = null;
        $searchClientIDParam = null;
        $searchUIDParam = UserSystemConstants::NO_USER_RELATED_UID;

        $TicketEntityStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        if($REQ_IS_FRONTEND_TOKEN){
            $verifyFrontendTokenResult = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
            if($verifyFrontendTokenResult !== null){
                return $verifyFrontendTokenResult->toResponse($response);
            }
            if(!empty($REQ_MASKID)){
                if(!is_string($REQ_MASKID) || !MaskIDFormat::isValidMaskID($REQ_MASKID)){
                    return ReturnableResponse::fromIncorrectFormattedParam('mask_id')->toResponse($response);
                }else{
                    $searchMaskIDParam = MaskIDFormat::formatMaskID($REQ_MASKID);
                }
            }
            if(!empty($REQ_CLIENT_ID)){
                if(!is_string($REQ_CLIENT_ID) || !APPFormat::isValidAPPID($REQ_CLIENT_ID)){
                    return ReturnableResponse::fromIncorrectFormattedParam('client_id')->toResponse($response);
                }else{
                    $searchClientIDParam = APPFormat::formatAPPID($REQ_CLIENT_ID);
                }
            }
            $searchUIDParam = intval($REQ_UID);
        }else{
            $verifyAPPTokenResult = CommonFunction::checkAPPTokenValidResponse($REQ_ACCESS_TOKEN,$ctime);
            if(!$verifyAPPTokenResult->succeed){
                return $verifyAPPTokenResult->returnableResponse->toResponse($response);
            }
            $searchMaskIDParam = $verifyAPPTokenResult->tokenEntity->getMaskID();
            $searchClientIDParam = $verifyAPPTokenResult->tokenEntity->getClientID();
        }

        $allRelatedTickets = $TicketEntityStorage->searchOAuthTicketRecordEntity(
            -1,
            -1,
            -1,
            -1,
            $searchUIDParam,
            $searchMaskIDParam,
            $searchClientIDParam,
            null,
            APPSystemConstants::NO_APP_RELATED_APPUID,
            null,
            0,
            -1
        );
        $returnResponse = new ReturnableResponse(200,0);
        $allTickets = [];
        if($allRelatedTickets->getNumResultsStored() > 0){
            foreach($allRelatedTickets->getResultArray() as $singleTicket){
                $allTickets[] = TicketOutputUtil::getTicketAsAssoc($singleTicket);
            }
        }
        $returnResponse->returnDataLevelEntries['tickets'] = $allTickets;
        return $returnResponse->toResponse($response);
    }
    public function respondToTicket(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_TICKETID = $args['ticket_id'];

        $REQ_IS_FRONTEND_TOKEN = $REQ_PARAMS['is_frontend_token'];
        $REQ_UID = $REQ_PARAMS['uid']; //Only Present if using frontend token
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token']; //OAuth/Frontend Token

        $REQ_CLIENT_ID = $REQ_PARAMS['client_id']; //Only present when APP is trying to respond
        $REQ_CLIENT_SECRET = $REQ_PARAMS['client_secret']; //Only present when client_id is introduced
        $REQ_RESPONDER_NAME = $REQ_PARAMS['responder_name']; //Only present when client_id is introduced

        $REQ_CONTENT = $REQ_PARAMS['content'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        if(empty($REQ_TICKETID) || !is_string($REQ_TICKETID) || !OAuthTicketFormat::isValidTicketID($REQ_TICKETID)){
            return ReturnableResponse::fromIncorrectFormattedParam('ticket_id')->toResponse($response);
        }


        $ticketStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        $ticketEntity = null;
        $isTicketCreatorContent = false;

        if(empty($REQ_CONTENT) || !is_string($REQ_CONTENT) || !$ticketStorage->getFormatSetting()->checkContent($REQ_CONTENT)){
            return ReturnableResponse::fromIncorrectFormattedParam('content')->toResponse($response);
        }

        if(empty($REQ_CLIENT_ID)){
            //User responding
            $isTicketCreatorContent = true;

            if($REQ_IS_FRONTEND_TOKEN){
                $verifyFrontendTokenResult = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
                if($verifyFrontendTokenResult !== null){
                    return $verifyFrontendTokenResult->toResponse($response);
                }
                $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
                if($ticketEntity === null || $ticketEntity->uid !== intval($REQ_UID)){
                    return ReturnableResponse::fromPermissionDeniedError("You don't have permission to edit this ticket")->toResponse($response);
                }
            }else{
                $verifyAPPTokenResult = CommonFunction::checkAPPTokenValidResponse($REQ_ACCESS_TOKEN,$ctime);
                if(!$verifyAPPTokenResult->succeed){
                    return $verifyAPPTokenResult->returnableResponse->toResponse($response);
                }
                $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
                if($ticketEntity === null || $ticketEntity->getMaskID() !== $verifyAPPTokenResult->tokenEntity->getMaskID()){
                    return ReturnableResponse::fromPermissionDeniedError("You don't have permission to edit this ticket")->toResponse($response);
                }
            }
        }else{
            $isTicketCreatorContent = false;

            if(!empty($REQ_RESPONDER_NAME) && !$ticketStorage->getFormatSetting()->checkResponderName($REQ_RESPONDER_NAME)){
                return ReturnableResponse::fromIncorrectFormattedParam('responder_name')->toResponse($response);
            }
            $checkAPPSecretResult = CommonFunction::checkAPPSecretResponse($REQ_CLIENT_ID,$REQ_CLIENT_SECRET);
            if(!$checkAPPSecretResult->succeed){
                return $checkAPPSecretResult->returnableResponse->toResponse($response);
            }
            $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
            if($ticketEntity === null || $ticketEntity->getClientID() !== $checkAPPSecretResult->appEntity->getClientID()){
                return ReturnableResponse::fromPermissionDeniedError("You don't have permission to edit this ticket")->toResponse($response);
            }
        }
        if($ticketEntity->isClosed){
            return ReturnableResponse::fromItemExpiredOrUsedError('ticket_id')->toResponse($response);
        }

        $newTicketResponseItem = new OAuthTicketSingleContent(
            $REQ_CONTENT,
            $isTicketCreatorContent,
            $isTicketCreatorContent ? null : $REQ_RESPONDER_NAME,
            $ctime,
            $ctime,
            $ticketStorage->getFormatSetting()
        );
        $ticketEntity->contentListing[] = $newTicketResponseItem;
        $ticketEntity->lastUpdateTime = $ctime;

        //Update TicketEntity
        try{
            $ticketStorage->updateOAuthTicketRecord($ticketEntity);
            $ticketStorage->updateOAuthTicketResponses($ticketEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }

        $returnResponse = new ReturnableResponse(201,0);
        $returnResponse->returnDataLevelEntries['ticket'] = TicketOutputUtil::getTicketAsAssoc($ticketEntity);
        return $returnResponse->toResponse($response);
    }
    public function getTicketInfo(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_TICKETID = $args['ticket_id'];

        $REQ_IS_FRONTEND_TOKEN = $REQ_PARAMS['is_frontend_token'];
        $REQ_UID = $REQ_PARAMS['uid']; //Only Present if using frontend token
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token']; //OAuth/Frontend Token

        $REQ_CLIENT_ID = $REQ_PARAMS['client_id']; //Only present when APP is trying to respond
        $REQ_CLIENT_SECRET = $REQ_PARAMS['client_secret']; //Only present when client_id is introduced

        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        if(empty($REQ_TICKETID) || !is_string($REQ_TICKETID) || !OAuthTicketFormat::isValidTicketID($REQ_TICKETID)){
            return ReturnableResponse::fromIncorrectFormattedParam('ticket_id')->toResponse($response);
        }


        $ticketStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        $ticketEntity = null;

        if(empty($REQ_CLIENT_ID)){
            //User responding
            if($REQ_IS_FRONTEND_TOKEN){
                $verifyFrontendTokenResult = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
                if($verifyFrontendTokenResult !== null){
                    return $verifyFrontendTokenResult->toResponse($response);
                }
                $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
                if($ticketEntity === null || $ticketEntity->uid !== intval($REQ_UID)){
                    return ReturnableResponse::fromPermissionDeniedError("You don't have permission to view this ticket")->toResponse($response);
                }
            }else{
                $verifyAPPTokenResult = CommonFunction::checkAPPTokenValidResponse($REQ_ACCESS_TOKEN,$ctime);
                if(!$verifyAPPTokenResult->succeed){
                    return $verifyAPPTokenResult->returnableResponse->toResponse($response);
                }
                $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
                if($ticketEntity === null || $ticketEntity->getMaskID() !== $verifyAPPTokenResult->tokenEntity->getMaskID()){
                    return ReturnableResponse::fromPermissionDeniedError("You don't have permission to view this ticket")->toResponse($response);
                }
            }
        }else{
            $checkAPPSecretResult = CommonFunction::checkAPPSecretResponse($REQ_CLIENT_ID,$REQ_CLIENT_SECRET);
            if(!$checkAPPSecretResult->succeed){
                return $checkAPPSecretResult->returnableResponse->toResponse($response);
            }
            $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
            if($ticketEntity === null || $ticketEntity->getClientID() !== $checkAPPSecretResult->appEntity->getClientID()){
                return ReturnableResponse::fromPermissionDeniedError("You don't have permission to view this ticket")->toResponse($response);
            }
        }

        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['ticket'] = TicketOutputUtil::getTicketAsAssoc($ticketEntity);
        return $returnResponse->toResponse($response);
    }
    public function getAPPOwnedTicketCounts(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_CLIENT_ID = $args['client_id'];
        $REQ_CLIENT_SECRET = $REQ_PARAMS['client_secret'];
        $REQ_MASKID = $REQ_PARAMS['mask_id']; //Optional Param to filter
        $REQ_TITLE = $REQ_PARAMS['title']; //Optional Param to filter

        $verifyClientSecretResult = CommonFunction::checkAPPSecretResponse($REQ_CLIENT_ID,$REQ_CLIENT_SECRET);
        if(!$verifyClientSecretResult->succeed){
            return $verifyClientSecretResult->returnableResponse->toResponse($response);
        }

        $searchMaskID = null;
        $searchTitle = null;

        if(empty($REQ_MASKID)){
            $searchMaskID = null;
        }else{
            if(!is_string($REQ_MASKID) || !MaskIDFormat::isValidMaskID($REQ_MASKID)){
                return ReturnableResponse::fromIncorrectFormattedParam('mask_id')->toResponse($response);
            }
            $searchMaskID = MaskIDFormat::formatMaskID($REQ_MASKID);
        }

        if(empty($REQ_TITLE)){
            $searchTitle = null;
        }else{
            if(!is_string($REQ_TITLE)){
                return ReturnableResponse::fromIncorrectFormattedParam('title')->toResponse($response);
            }
            $searchTitle = $REQ_TITLE;
        }

        $ticketStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        $searchedTicketCount = $ticketStorage->getOAuthTicketRecordEntityCount(
            -1,
            -1,
            -1,
            -1,
            UserSystemConstants::NO_USER_RELATED_UID,
            $searchMaskID,
            APPFormat::formatAPPID($REQ_CLIENT_ID),
            $searchTitle,
            APPSystemConstants::NO_APP_RELATED_APPUID,
            null
        );
        
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['total_count'] = $searchedTicketCount;
        return $returnResponse->toResponse($response);
    }
    public function getAPPOwnedTickets(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_CLIENT_ID = $args['client_id'];
        $REQ_CLIENT_SECRET = $REQ_PARAMS['client_secret'];
        $REQ_MASKID = $REQ_PARAMS['mask_id']; //Optional Param to filter
        $REQ_TITLE = $REQ_PARAMS['title']; //Optional Param to filter
        $REQ_OFFSET = $REQ_PARAMS['offset'];
        $REQ_COUNT = $REQ_PARAMS['count'];

        if(empty($REQ_OFFSET)){
            $REQ_OFFSET = 0;
        }else{
            $REQ_OFFSET = intval($REQ_OFFSET);
        }

        if(empty($REQ_COUNT)){
            $REQ_COUNT = -1;
        }else{
            $REQ_COUNT = intval($REQ_COUNT);
            if($REQ_COUNT < -1){
                $REQ_COUNT = -1;
            }
        }

        if($REQ_COUNT === 0){
            return ReturnableResponse::fromIncorrectFormattedParam('count')->toResponse($response);
        }

        $verifyClientSecretResult = CommonFunction::checkAPPSecretResponse($REQ_CLIENT_ID,$REQ_CLIENT_SECRET);
        if(!$verifyClientSecretResult->succeed){
            return $verifyClientSecretResult->returnableResponse->toResponse($response);
        }

        $searchMaskID = null;
        $searchTitle = null;

        if(empty($REQ_MASKID)){
            $searchMaskID = null;
        }else{
            if(!is_string($REQ_MASKID) || !MaskIDFormat::isValidMaskID($REQ_MASKID)){
                return ReturnableResponse::fromIncorrectFormattedParam('mask_id')->toResponse($response);
            }
            $searchMaskID = MaskIDFormat::formatMaskID($REQ_MASKID);
        }

        if(empty($REQ_TITLE)){
            $searchTitle = null;
        }else{
            if(!is_string($REQ_TITLE)){
                return ReturnableResponse::fromIncorrectFormattedParam('title')->toResponse($response);
            }
            $searchTitle = $REQ_TITLE;
        }

        $ticketStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        $searchedTickets = $ticketStorage->searchOAuthTicketRecordEntity(
            -1,
            -1,
            -1,
            -1,
            UserSystemConstants::NO_USER_RELATED_UID,
            $searchMaskID,
            APPFormat::formatAPPID($REQ_CLIENT_ID),
            $searchTitle,
            APPSystemConstants::NO_APP_RELATED_APPUID,
            null,
            $REQ_OFFSET,
            $REQ_COUNT
        );
        
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['result'] = MultipleResultOutputUtil::getMultipleResultAsAssoc($searchedTickets,array(TicketOutputUtil::class,'getTicketAsAssoc'));
        return $returnResponse->toResponse($response);
    }
    public function changeTicketStatus(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_TICKETID = $args['ticket_id'];

        $REQ_IS_FRONTEND_TOKEN = $REQ_PARAMS['is_frontend_token'];
        $REQ_UID = $REQ_PARAMS['uid']; //Only Present if using frontend token
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token']; //OAuth/Frontend Token

        $REQ_CLIENT_ID = $REQ_PARAMS['client_id']; //Only present when APP is trying to respond
        $REQ_CLIENT_SECRET = $REQ_PARAMS['client_secret']; //Only present when client_id is introduced

        $REQ_SET_RESOLVED = $REQ_PARAMS['set_resolved'];
        $REQ_SET_CLOSED = $REQ_PARAMS['set_closed'];

        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        if(empty($REQ_TICKETID) || !is_string($REQ_TICKETID) || !OAuthTicketFormat::isValidTicketID($REQ_TICKETID)){
            return ReturnableResponse::fromIncorrectFormattedParam('ticket_id')->toResponse($response);
        }


        $ticketStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage();
        $ticketEntity = null;

        if($REQ_SET_RESOLVED === null && !$REQ_SET_CLOSED){
            return ReturnableResponse::fromIncorrectFormattedParam('set_resolved')->toResponse($response);
        }

        
        if(empty($REQ_CLIENT_ID)){
            //User responding
            if($REQ_IS_FRONTEND_TOKEN){
                $verifyFrontendTokenResult = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
                if($verifyFrontendTokenResult !== null){
                    return $verifyFrontendTokenResult->toResponse($response);
                }
                $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
                if($ticketEntity === null || $ticketEntity->uid !== intval($REQ_UID)){
                    return ReturnableResponse::fromPermissionDeniedError("You don't have permission to edit this ticket")->toResponse($response);
                }
            }else{
                $verifyAPPTokenResult = CommonFunction::checkAPPTokenValidResponse($REQ_ACCESS_TOKEN,$ctime);
                if(!$verifyAPPTokenResult->succeed){
                    return $verifyAPPTokenResult->returnableResponse->toResponse($response);
                }
                $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
                if($ticketEntity === null || $ticketEntity->getMaskID() !== $verifyAPPTokenResult->tokenEntity->getMaskID()){
                    return ReturnableResponse::fromPermissionDeniedError("You don't have permission to edit this ticket")->toResponse($response);
                }
            }
        }else{
            $checkAPPSecretResult = CommonFunction::checkAPPSecretResponse($REQ_CLIENT_ID,$REQ_CLIENT_SECRET);
            if(!$checkAPPSecretResult->succeed){
                return $checkAPPSecretResult->returnableResponse->toResponse($response);
            }
            $ticketEntity = $ticketStorage->getOAuthTicketRecord($REQ_TICKETID);
            if($ticketEntity === null || $ticketEntity->getClientID() !== $checkAPPSecretResult->appEntity->getClientID()){
                return ReturnableResponse::fromPermissionDeniedError("You don't have permission to edit this ticket")->toResponse($response);
            }
        }
        if($ticketEntity->isClosed){
            return ReturnableResponse::fromItemExpiredOrUsedError('ticket_id')->toResponse($response);
        }

        if($REQ_SET_CLOSED !== null){
            if($REQ_SET_RESOLVED){
                $ticketEntity->isResolved = true;
            }else{
                $ticketEntity->isResolved = false;
            }
        }

        if($REQ_SET_CLOSED){
            $ticketEntity->isClosed = true;
        }

        $ticketEntity->lastUpdateTime = $ctime;

        //Update TicketEntity
        try{
            $ticketStorage->updateOAuthTicketRecord($ticketEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }

        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['ticket'] = TicketOutputUtil::getTicketAsAssoc($ticketEntity);
        return $returnResponse->toResponse($response);
    }
}