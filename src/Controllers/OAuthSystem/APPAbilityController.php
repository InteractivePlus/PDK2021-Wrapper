<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\OutputUtils\MaskIDOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenScopes;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class APPAbilityController{
    public function getBasicInfo(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ScopeRequired = APPTokenScopes::SCOPE_BASIC_INFO()->getScopeName();

        $VerifyScopeAndTokenResult = CommonFunction::checkAPPTokenValidAndScopeSatisfiedResponse($REQ_ACCESS_TOKEN,$ScopeRequired,$ctime);
        if(!$VerifyScopeAndTokenResult->succeed){
            return $VerifyScopeAndTokenResult->returnableResponse->toResponse($response);
        }

        $APPTokenEntity = $VerifyScopeAndTokenResult->tokenEntity;
        $MaskIDEntityStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();
        $MaskIDEntity = $MaskIDEntityStorage->getMaskIDEntityByMaskID($APPTokenEntity->getMaskID());
        if($MaskIDEntity === null){
            return ReturnableResponse::fromInnerError('Cannot find mask_id of a existing and verified access_token')->toResponse($response);
        }
        
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['info'] = MaskIDOutputUtil::getMaskIDAsOAuthInfoAssocArray($MaskIDEntity);
        return $returnResponse->toResponse($response);
    }
    public function sendNotification(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_TITLE = $REQ_PARAMS['title'];
        $REQ_CONTENT = $REQ_PARAMS['content'];
        $REQ_PREFERRED_SEND_METHOD = $REQ_PARAMS['preferred_send_method'];
        $REQ_IS_SALES = $REQ_PARAMS['is_sales'];
        
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        
        
        if($REQ_IS_SALES){
            $REQ_IS_SALES = true;
        }else{
            $REQ_IS_SALES = false;
        }

        $ScopeRequired = $REQ_IS_SALES ? APPTokenScopes::SCOPE_SEND_SALES()->getScopeName() : APPTokenScopes::SCOPE_SEND_NOTIFICATIONS()->getScopeName();

        $VerifyScopeAndTokenResult = CommonFunction::checkAPPTokenValidAndScopeSatisfiedResponse($REQ_ACCESS_TOKEN,$ScopeRequired,$ctime);
        if(!$VerifyScopeAndTokenResult->succeed){
            return $VerifyScopeAndTokenResult->returnableResponse->toResponse($response);
        }

        $TITLE_MAX_LEN = $REQ_IS_SALES ? PDK2021Wrapper::$config->OAUTH_SALE_TITLE_MAX_LEN : PDK2021Wrapper::$config->OAUTH_NOTIFICATION_TITLE_MAX_LEN;
        if(empty($REQ_TITLE) || !is_string($REQ_TITLE) || strlen($REQ_TITLE) > $TITLE_MAX_LEN){
            return ReturnableResponse::fromIncorrectFormattedParam('title')->toResponse($response);
        }

        $CONTENT_MAX_LEN = $REQ_IS_SALES ? PDK2021Wrapper::$config->OAUTH_SALE_CONTENT_MAX_LEN : PDK2021Wrapper::$config->OAUTH_NOTIFICATION_CONTENT_MAX_LEN;
        if(empty($REQ_CONTENT) || !is_string($REQ_CONTENT) || strlen($REQ_CONTENT) > $CONTENT_MAX_LEN){
            return ReturnableResponse::fromIncorrectFormattedParam('content')->toResponse($response);
        }
        
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $MaskIDEntityStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();

        $APPTokenEntity = $VerifyScopeAndTokenResult->tokenEntity;
        $APPEntity = $APPEntityStorage->getAPPEntityByAPPUID($APPTokenEntity->appuid);
        if($APPEntity === null){
            return ReturnableResponse::fromInnerError('Cannot find APPEntity of a existing and verified APPToken')->toResponse($response);
        }
        $MaskIDEntity = $MaskIDEntityStorage->getMaskIDEntityByMaskID($APPTokenEntity->getMaskID());
        if($MaskIDEntity === null){
            return ReturnableResponse::fromInnerError('Cannot find MaskIDEntity of a existing and verified APPToken')->toResponse($response);
        }
        $UserEntity = $UserEntityStorage->getUserEntityByUID($MaskIDEntity->uid);
        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('Cannot find UserEntity of a existing and verified MaskIDEntity')->toResponse($response);
        }

        $sentResult = CommonFunction::sendThirdAPPMessage(
            !$REQ_IS_SALES,
            $REQ_PREFERRED_SEND_METHOD,
            $REQ_TITLE,
            $REQ_CONTENT,
            $UserEntity,
            $MaskIDEntity,
            $APPEntity,
            $APPTokenEntity,
            $ctime,
            $REMOTE_ADDR,
        );

        if(!$sentResult->succeed){
            return $sentResult->returnableResponse->toResponse($response);
        }

        $returnResponse = new ReturnableResponse(201,0);
        $returnResponse->returnDataLevelEntries['sent_method'] = $sentResult->sentMethod;
        return $returnResponse->toResponse($response);
    }
    
}