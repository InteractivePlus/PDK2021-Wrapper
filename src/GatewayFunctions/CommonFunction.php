<?php
namespace InteractivePlus\PDK2021\GatewayFunctions;


use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenObtainedMethod;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKSenderServiceError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Captcha\Format\CaptchaFormat;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeID;
use InteractivePlus\PDK2021Core\User\Formats\TokenFormat;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class CommonFunction{
    public static function useAndCheckCaptchaResult($captcha_id) : ?ReturnableResponse{
        if(PDK2021Wrapper::$config->DEVELOPMENT_MODE){
            return null;
        }
        if(empty($captcha_id) || !is_string($captcha_id) || !CaptchaFormat::isValidCaptchaID($captcha_id)){
            return ReturnableResponse::fromIncorrectFormattedParam('captcha_id');
        }
        $captchaSystem = PDK2021Wrapper::$pdkCore->getCaptchaSystem();

        $checkResult = false;
        try{
            $checkResult = $captchaSystem->checkAndUseCpatcha($captcha_id);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e);
        }
        if(!$checkResult){
            return ReturnableResponse::fromCredentialMismatchError('captcha_id');
        }
        return null;
    }
    public static function checkTokenValidResponse($uid, $access_token, int $currentTime) : ?ReturnableResponse{
        $REQ_ACCESS_TOKEN = $access_token;
        $REQ_UID = $uid;

        if(empty($REQ_ACCESS_TOKEN) || !is_string($access_token) || !TokenFormat::isValidToken($REQ_ACCESS_TOKEN)){
            return ReturnableResponse::fromIncorrectFormattedParam('access_token');
        }
        if(empty($REQ_UID) || $REQ_UID < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('uid');
        }else{
            $REQ_UID = (int) $REQ_UID;
        }
        $TokenEntityStorage = PDK2021Wrapper::$pdkCore->getTokenEntityStorage();
        $TokenEntity = $TokenEntityStorage->getTokenEntity($REQ_ACCESS_TOKEN);
        if($TokenEntity === null){
            return ReturnableResponse::fromCredentialMismatchError('access_token');
        }
        if($TokenEntity->getRelatedUID() !== $REQ_UID){
            return ReturnableResponse::fromCredentialMismatchError('access_token');
        }
        if(!$TokenEntity->isValid($currentTime)){
            return ReturnableResponse::fromItemExpiredOrUsedError('access_token');
        }
        return null;
    }
    public static function getCheckVerificationCodeResponse($veriCode, int $vericodeID, int $currentTime, int $appuid) : CheckVericodeResponse{
        if(empty($veriCode) || !is_string($veriCode) || !VeriCodeFormat::isValidVerificationCode($veriCode)){
            return new CheckVericodeResponse(false,ReturnableResponse::fromIncorrectFormattedParam('veriCode'),null);
        }
        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $veriCodeEntity = $veriCodeStorage->getVeriCodeEntity($veriCode);
        if($veriCodeEntity === null){
            return new CheckVericodeResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('veriCode'),null);
        }
        if($veriCodeEntity->getVeriCodeID()->getVeriCodeID() !== $vericodeID){
            return new CheckVericodeResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('veriCode'),null);
        }
        if(!$veriCodeEntity->canUse($currentTime)){
            return new CheckVericodeResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('veriCode'),null);
        }
        if($veriCodeEntity->getAPPUID() != $appuid){
            return new CheckVericodeResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('veriCode'),null);
        }
        return new CheckVericodeResponse(true,null,$veriCodeEntity);
    }
    public static function getCheckAnyVeriCodeResponse($verificationCode, $uid, int $vericodeID, int $currentTime, int $appuid) : CheckVericodeResponse{
        if(!empty($verificationCode) && is_string($verificationCode) && VeriCodeFormat::isValidPartialPhoneVerificationCode($verificationCode)){
            return CommonFunction::getCheckPhonePartialCodeResponse($verificationCode,$uid,$vericodeID,$currentTime, $appuid);
        }else{
            return CommonFunction::getCheckVerificationCodeResponse($verificationCode,$vericodeID,$currentTime, $appuid);
        }
    }
    public static function getCheckPhonePartialCodeResponse($verificationCode, $uid, int $vericodeID, int $currentTime, int $appuid) : CheckVericodeResponse{
        if(empty($verificationCode) || !is_string($verificationCode) || !VeriCodeFormat::isValidPartialPhoneVerificationCode($verificationCode)){
            return new CheckVericodeResponse(false,ReturnableResponse::fromIncorrectFormattedParam('veriCode'),null);
        }
        
        if(empty($uid) || $uid < 0){
            return new CheckVericodeResponse(false,ReturnableResponse::fromIncorrectFormattedParam('uid'),null);
        }else{
            $uid = (int) $uid;
        }

        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        
        $searchedResults = $veriCodeStorage->searchPhoneVeriCode($currentTime + 1,0,$uid,$appuid,$verificationCode,$vericodeID);
        if($searchedResults->getNumResultsStored() < 1){
            return new CheckVericodeResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('veriCode'),null);
        }
        $veriCodeEntity = null;
        $searchedResultsArr = $searchedResults->getResultArray();
        foreach($searchedResultsArr as $singleResult){
            if($singleResult->canUse($currentTime) && ($singleResult->getSentMethod() === SentMethod::PHONE_CALL || $singleResult->getSentMethod() === SentMethod::SMS_MESSAGE)){
                $veriCodeEntity = $singleResult;
                break;
            }
        }
        if($veriCodeEntity === null){
            return new CheckVericodeResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('veriCode'),null);
        }
        return new CheckVericodeResponse(true,null,$veriCodeEntity);
    }
    public static function checkAPPTokenValidResponse($access_token,int $currentTime, $client_id, $client_secret, $mask_id) : checkAPPTokenResponse{
        if(empty($access_token) || !is_string($access_token) || !APPFormat::isValidAPPAccessToken($access_token)){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('access_token'),null);
        }
        if(!empty($client_id) && (!is_string($client_id) || !APPFormat::isValidAPPID($client_id))){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('client_id'),null);
        }
        if(!empty($client_secret) && (!is_string($client_secret) || !APPFormat::isValidAPPSecert($client_secret))){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('client_secret'),null);
        }
        if(!empty($mask_id) && (!is_string($mask_id) || !MaskIDFormat::isValidMaskID($mask_id))){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('mask_id'),null);
        }
        $fetchedTokenEntity = null;
        $appTokenEntityStorage = PDK2021Wrapper::$pdkCore->getAPPTokenEntityStorage();
        $appEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        try{
            $fetchedTokenEntity = $appTokenEntityStorage->getAPPTokenEntity($access_token);
            if($fetchedTokenEntity === null){
                return new CheckAPPTokenResponse(false,ReturnableResponse::fromCredentialMismatchError('access_token'),null);
            }
        }catch(PDKStorageEngineError $e){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromPDKException($e),null);
        }
        if((!empty($client_id) && !APPFormat::isAPPIDStringEqual($fetchedTokenEntity->getClientID(),$client_id)) || (!empty($mask_id) && !MaskIDFormat::isMaskIDStringEqual($fetchedTokenEntity->getMaskID(),$mask_id))){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromCredentialMismatchError('access_token'),null);
        }
        if(!$fetchedTokenEntity->getObtainedMethod() === APPTokenObtainedMethod::GRANTTYPE_WITH_SECRET_AUTH_CODE){
            $APPEntity = $appEntityStorage->getAPPEntityByAPPUID($fetchedTokenEntity->appuid);
            if($APPEntity === null){
                return new CheckAPPTokenResponse(false,ReturnableResponse::fromInnerError('Cannot find APP with an issued APPToken'),null);
            }
            if(!$APPEntity->checkClientSecret($client_secret)){
                return new CheckAPPTokenResponse(false,ReturnableResponse::fromCredentialMismatchError('client_secret'),$fetchedTokenEntity);
            }
        }
        if(!$fetchedTokenEntity->valid || $currentTime >= $fetchedTokenEntity->expireTime){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('access_token'),$fetchedTokenEntity);
        }
        return new CheckAPPTokenResponse(true,null,$fetchedTokenEntity);
    }
    public static function checkAPPTokenValidAndScopeSatisfiedResponse($access_token, string $scope, int $currentTime, $client_id, $client_secret, $mask_id) : CheckAPPTokenResponse{
        $checkTokenResponse = self::checkAPPTokenValidResponse($access_token,$currentTime,$client_id,$client_secret,$mask_id);
        if(!$checkTokenResponse->succeed){
            return $checkTokenResponse;
        }
        if(!in_array(strtolower($scope),$checkTokenResponse->tokenEntity->scopes)){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromPermissionDeniedError('scope not granted'),$checkTokenResponse->tokenEntity);
        }
        return new CheckAPPTokenResponse(true,null,$checkTokenResponse->tokenEntity);
    }
    public static function sendVeriCode(VeriCodeID $veriCodeID, $preferredSendMethod, UserEntity $user, int $currentTime, string $remoteAddr, int $appuid = APPSystemConstants::INTERACTIVEPDK_APPUID) : SendVeriCodeResponse{
        $actualSendingMethod = SentMethod::NOT_SENT;
        $veriCode = new VeriCodeEntity(
            $veriCodeID,
            $currentTime,
            $currentTime + PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
            $user->getUID(),
            $appuid,
            null,
            $remoteAddr
        );
        $VeriCodeEntityStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        while($VeriCodeEntityStorage->checkVeriCodeExist($veriCode->getVeriCodeString())){
            $veriCode = $veriCode->withVeriCodeStringReroll();
        }

        if(($preferredSendMethod === SentMethod::EMAIL && !empty($user->getEmail()) && $user->isEmailVerified()) || ($preferredSendMethod !== SentMethod::EMAIL && ($user->getPhoneNumber() === null || !$user->isPhoneVerified()))){
            $actualSendingMethod = SentMethod::EMAIL;
            try{
                PDK2021Wrapper::$pdkCore->getVeriCodeEmailSender()->sendVeriCode($veriCode,$user,$user->getEmail());
            }catch(PDKSenderServiceError $e){
                return new SendVeriCodeResponse(false,ReturnableResponse::fromPDKException($e));
            }
        }else if($user->getPhoneNumber() !== null && $user->isPhoneVerified()){
            $sender = PDK2021Wrapper::$pdkCore->getPhoneSender($actualSendingMethod,$preferredSendMethod !== SentMethod::PHONE_CALL);
            try{
                $sender->sendVeriCode($veriCode,$user,$user->getPhoneNumber());
            }catch(PDKSenderServiceError $e){
                return new SendVeriCodeResponse(false,ReturnableResponse::fromPDKException($e));
            }
        }else{
            return new SendVeriCodeResponse(false,ReturnableResponse::fromItemNotFound('communication_method'));
        }
        $veriCode = $veriCode->withSentMethod($actualSendingMethod);
        $updatedEntity = null;
        try{
            $updatedEntity = $VeriCodeEntityStorage->addVeriCodeEntity($veriCode,false);
        }catch(PDKStorageEngineError $e){
            return new SendVeriCodeResponse(false,ReturnableResponse::fromPDKException($e));
        }
        if($updatedEntity === null){
            return new SendVeriCodeResponse(false,ReturnableResponse::fromInnerError('failed to add vericode to database'));
        }

        return new SendVeriCodeResponse(true,null,$actualSendingMethod);
    }
}