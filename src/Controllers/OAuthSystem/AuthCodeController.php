<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\InputUtils\ScopeInputUtil;
use InteractivePlus\PDK2021\OAuth\OAuthScope;
use InteractivePlus\PDK2021\OAuth\OAuthScopes;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\AuthCode\AuthCodeChallengeType;
use InteractivePlus\PDK2021Core\APP\AuthCode\AuthCodeEntity;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthCodeController{
    public function getAuthCode(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_MASK_ID = $REQ_PARAMS['mask_id'];
        $REQ_CLIENT_ID = $REQ_PARAMS['client_id'];
        $REQ_CODE_CHALLENGE = $REQ_PARAMS['code_challenge'];
        $REQ_CODE_CHALLENGE_TYPE = $REQ_PARAMS['code_challenge_type'];
        $REQ_SCOPES = $REQ_PARAMS['scope'];
        $REQ_REDIRECT_URI = $REQ_PARAMS['redirect_uri'];
        $REQ_STATE = $REQ_PARAMS['state'];


        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        $tokenCheckStatus = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($tokenCheckStatus !== null){
            return $tokenCheckStatus->toResponse($response);
        }

        
        $realChallengeType = AuthCodeChallengeType::parseChallengeType($REQ_CODE_CHALLENGE_TYPE);
        if(!APPFormat::isValidAPPID($REQ_CLIENT_ID)){
            return ReturnableResponse::fromIncorrectFormattedParam('client_id')->toResponse($response);
        }
        if($realChallengeType === AuthCodeChallengeType::SHA256 && !APPFormat::isValidChallengeS256S($REQ_CODE_CHALLENGE)){
            return ReturnableResponse::fromIncorrectFormattedParam('code_challenge')->toResponse($response);
        }
        if(!MaskIDFormat::isValidMaskID($REQ_MASK_ID)){
            return ReturnableResponse::fromIncorrectFormattedParam('mask_id')->toResponse($response);
        }
        if(empty($REQ_SCOPES)){
            return ReturnableResponse::fromIncorrectFormattedParam('scope')->toResponse($response);
        }
        $parsedScopes = ScopeInputUtil::parseScopeArray($REQ_SCOPES);
        if(!in_array(OAuthScopes::SCOPE_BASIC_INFO()->getScopeName(),$parsedScopes)){
            return ReturnableResponse::fromIncorrectFormattedParam('scope')->toResponse($response);
        }


        
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $MaskIDEntityStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();
        $AuthCodeStorage = PDK2021Wrapper::$pdkCore->getAPPAuthCodeStorage();

        $APPEntity = $APPEntityStorage->getAPPEntityByClientID($REQ_CLIENT_ID);
        if($APPEntity === null){
            return ReturnableResponse::fromItemNotFound('client_id')->toResponse($response);
        }


        //Fetch MaskID and see if it corresponds to this APP & this User
        $MaskID = $MaskIDEntityStorage->getMaskIDEntityByMaskID($REQ_MASK_ID);
        if($MaskID === null){
            return ReturnableResponse::fromItemNotFound('mask_id')->toResponse($response);
        }
        if($MaskID->uid !== intval($REQ_UID)){
            return ReturnableResponse::fromItemNotFound('mask_id')->toResponse($response);
        }
        if($MaskID->appuid !== $APPEntity->getAPPUID()){
            return ReturnableResponse::fromItemNotFound('mask_id')->toResponse($response);
        }

        //Now let's check if the Auth Mode is correct for the APP
        $authModeIsServer = $realChallengeType === AuthCodeChallengeType::NO_CHALLENGE;
        if(
            ($authModeIsServer && !$APPEntity->canUseServerGrant())
            || (!$authModeIsServer && !$APPEntity->canUsePKCEGrant())
        ){
            return ReturnableResponse::fromPermissionDeniedError('This application cannot use this type of grant')->toResponse($response);
        }

        //Check if the RedirectURI is valid
        if(empty($REQ_REDIRECT_URI) || !$APPEntity->checkRedirectURI($REQ_REDIRECT_URI)){
            return ReturnableResponse::fromIncorrectFormattedParam('redirect_uri')->toResponse($response);
        }

        $createdAuthCode = null;
        if($authModeIsServer){
            $createdAuthCode = new AuthCodeEntity(
                null,
                $APPEntity->getAPPUID(),
                $MaskID->getMaskID(),
                $ctime,
                $ctime + PDK2021Wrapper::$config->OAUTH_AUTHCODE_AVAILABLE_DURATION,
                $parsedScopes,
                null,
                AuthCodeChallengeType::NO_CHALLENGE,
                false
            );
        }else{
            $createdAuthCode = new AuthCodeEntity(
                null,
                $APPEntity->getAPPUID(),
                $MaskID->getMaskID(),
                $ctime,
                $ctime + PDK2021Wrapper::$config->OAUTH_AUTHCODE_AVAILABLE_DURATION,
                $parsedScopes,
                $REQ_CODE_CHALLENGE,
                $realChallengeType,
                false
            );
        }

        //Let's put AuthCode into DB
        $returnedAuthCode = $AuthCodeStorage->addAuthCodeEntity($createdAuthCode,true);
        if($returnedAuthCode === null){
            return ReturnableResponse::fromInnerError('Unknown Error Happened When Trying to Add AuthCode to DB')->toResponse($response);
        }
        
        $composedRedirectURI = strval($REQ_REDIRECT_URI) . 'code=' . $returnedAuthCode->getAuthCodeStr() . (empty($REQ_STATE) ? '' : '&state=' . $REQ_STATE);
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['redirect'] = $composedRedirectURI;
        $returnResponse->returnDataLevelEntries['expires'] = $returnedAuthCode->expireTime;
        return $returnResponse->toResponse($response);
    }
}