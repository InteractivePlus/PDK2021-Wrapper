<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\OutputUtils\APPTokenOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenObtainedMethod;
use InteractivePlus\PDK2021Core\APP\AuthCode\AuthCodeChallengeType;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AccessCodeController{
    public function createAccessCode(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_AUTHCODE = $REQ_PARAMS['code'];
        $REQ_CLIENT_ID = $REQ_PARAMS['client_id'];
        $REQ_CLIENT_SECRET = $REQ_PARAMS['client_secret'];
        $REQ_CODE_VERIFIER = $REQ_PARAMS['code_verifier'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        if(!is_string($REQ_AUTHCODE) || !APPFormat::isValidAuthCode($REQ_AUTHCODE)){
            return ReturnableResponse::fromIncorrectFormattedParam('code')->toResponse($response);
        }
        if(empty($REQ_CLIENT_ID) || !is_string($REQ_CLIENT_ID) || !APPFormat::isValidAPPID($REQ_CLIENT_ID)){
            return ReturnableResponse::fromIncorrectFormattedParam('client_id')->toResponse($response);
        }
        if(!empty($REQ_CLIENT_SECRET) && !APPFormat::isValidAPPSecert($REQ_CLIENT_SECRET)){
            return ReturnableResponse::fromIncorrectFormattedParam('client_secret')->toResponse($response);
        }
        
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $AuthCodeStorage = PDK2021Wrapper::$pdkCore->getAPPAuthCodeStorage();
        $OAuthTokenStorage = PDK2021Wrapper::$pdkCore->getAPPTokenEntityStorage();

        //Let's check type of this auth grant first
        $AuthCodeEntity = $AuthCodeStorage->getAuthCodeEntity($REQ_AUTHCODE);
        if($AuthCodeEntity === null){
            return ReturnableResponse::fromCredentialMismatchError('code')->toResponse($response);
        }
        $APPEntity = $APPEntityStorage->getAPPEntityByClientID($REQ_CLIENT_ID);
        if($APPEntity === null){
            return ReturnableResponse::fromItemNotFound('client_id')->toResponse($response);
        }

        //Check if the AuthCode APPUID is the current APP
        if($AuthCodeEntity->appUID !== $APPEntity->getAPPUID()){
            return ReturnableResponse::fromCredentialMismatchError('code')->toResponse($response);
        }

        //Check if the AuthCode has expired
        if($AuthCodeEntity->used || $AuthCodeEntity->expireTime <= $ctime){
            return ReturnableResponse::fromItemExpiredOrUsedError('code')->toResponse($response);
        }

        $grantType = APPTokenObtainedMethod::GRANTTYPE_NO_SECRET_AUTH_CODE_PKCE;

        //Check if the Auth Grant is PKCE form
        if($AuthCodeEntity->getChallengeType() !== AuthCodeChallengeType::NO_CHALLENGE){
            //Is PKCE Form! Check Code Verifier
            $grantType = APPTokenObtainedMethod::GRANTTYPE_NO_SECRET_AUTH_CODE_PKCE;
            if(!is_string($REQ_CODE_VERIFIER)){
                return ReturnableResponse::fromIncorrectFormattedParam('code_verifier')->toResponse($response);
            }
            if(!$AuthCodeEntity->checkCodeVerifier($REQ_CODE_VERIFIER)){
                return ReturnableResponse::fromCredentialMismatchError('code_verifier')->toResponse($response);
            }
        }else{
            //Not PKCE Form, Server Grant, need to check Client Secret
            $grantType = APPTokenObtainedMethod::GRANTTYPE_WITH_SECRET_AUTH_CODE;
            if(empty($REQ_CLIENT_SECRET) || !is_string($REQ_CLIENT_SECRET) || !$APPEntity->checkClientSecret($REQ_CLIENT_SECRET)){
                return ReturnableResponse::fromCredentialMismatchError('client_secret')->toResponse($response);
            }
        }

        //Update AuthCode State to USED
        $AuthCodeStorage->useAuthCode($REQ_AUTHCODE);

        //Everything is checked and ready to go!
        $createdAPPTokenEntity = new APPTokenEntity(
            APPFormat::generateAPPAccessToken(),
            APPFormat::generateAPPRefreshToken(),
            $ctime,
            $ctime + PDK2021Wrapper::$config->OAUTH_AUTHCODE_AVAILABLE_DURATION,
            $ctime,
            $ctime + PDK2021Wrapper::$config->OAUTH_REFRESH_TOKEN_AVAILABLE_DURATION,
            $AuthCodeEntity->getMaskID(),
            $APPEntity->getAPPUID(),
            $APPEntity->getClientID(),
            $grantType,
            $AuthCodeEntity->scopes,
            true
        );

        //Save APPToken To DB
        $returnedAPPTokenEntity = $OAuthTokenStorage->addAPPTokenEntity($createdAPPTokenEntity,true,true);
        if($returnedAPPTokenEntity === null){
            return ReturnableResponse::fromInnerError('Unknown error happened when trying to put created APPToken into DB')->toResponse($response);
        }

        $returnResponse = new ReturnableResponse(201,0);
        $returnResponse->returnDataLevelEntries['token'] = APPTokenOutputUtil::getAPPTokenAsAssocArray($returnedAPPTokenEntity);
        return $returnResponse->toResponse($response);
    }
}