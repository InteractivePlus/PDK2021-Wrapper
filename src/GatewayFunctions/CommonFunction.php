<?php
namespace InteractivePlus\PDK2021\GatewayFunctions;


use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\Format\APPFormat;
use InteractivePlus\PDK2021Core\APP\Format\MaskIDFormat;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Captcha\Format\CaptchaFormat;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
use InteractivePlus\PDK2021Core\User\Formats\TokenFormat;

class CommonFunction{
    public static function useAndCheckCaptchaResult($captcha_id) : ?ReturnableResponse{
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
    public static function checkAPPTokenValidResponse($access_token, $client_id, $mask_id, int $currentTime) : checkAPPTokenResponse{
        if(empty($access_token) || !is_string($access_token) || !APPFormat::isValidAPPAccessToken($access_token)){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('access_token'),null);
        }
        if(empty($client_id) || !is_string($client_id) || !APPFormat::isValidAPPID($client_id)){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('appuid'),null);
        }
        if(empty($mask_id) || !is_string($mask_id) || !MaskIDFormat::isValidMaskID($mask_id)){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromIncorrectFormattedParam('mask_id'),null);
        }
        $fetchedTokenEntity = null;
        $appTokenEntityStorage = PDK2021Wrapper::$pdkCore->getAPPTokenEntityStorage();
        try{
            $fetchedTokenEntity = $appTokenEntityStorage->getAPPTokenEntity($access_token);
            if($fetchedTokenEntity === null){
                return new CheckAPPTokenResponse(false,ReturnableResponse::fromCredentialMismatchError('access_token'),null);
            }
        }catch(PDKStorageEngineError $e){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromPDKException($e),null);
        }
        if(!APPFormat::isAPPIDStringEqual($fetchedTokenEntity->getClientID(),$client_id) || !MaskIDFormat::isMaskIDStringEqual($fetchedTokenEntity->getMaskID(),$mask_id)){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromCredentialMismatchError('access_token'),null);
        }
        if(!$fetchedTokenEntity->valid || $currentTime >= $fetchedTokenEntity->expireTime){
            return new CheckAPPTokenResponse(false,ReturnableResponse::fromItemExpiredOrUsedError('access_token'),$fetchedTokenEntity);
        }
        return new CheckAPPTokenResponse(true,null,$fetchedTokenEntity);
    }
}