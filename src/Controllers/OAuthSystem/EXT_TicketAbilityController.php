<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
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
}