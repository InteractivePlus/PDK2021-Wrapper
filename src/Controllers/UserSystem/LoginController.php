<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\OutputUtils\UserOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKInnerArgumentError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Exception\PDKException;
use InteractivePlus\PDK2021Core\User\Formats\TokenFormat;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\Login\LoginFailedReasons;
use InteractivePlus\PDK2021Core\User\UserSystemFormatSetting;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LoginController{
    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_USERNAME = $REQ_PARAMS['username'];
        $REQ_EMAIL = $REQ_PARAMS['email'];
        $REQ_PHONE = $REQ_PARAMS['phone'];
        $REQ_PASSWORD = $REQ_PARAMS['password'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $DeviceUA = empty($request->getHeader('User-Agent')) ? null : implode('; ',$request->getHeader('User-Agent'));
        $ctime = time();

        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();

        if(empty($REQ_USERNAME) && empty($REQ_EMAIL) && empty($REQ_PHONE)){
            return ReturnableResponse::fromIncorrectFormattedParam('username|email|phone')->toResponse($response);
        }
        if(!empty($REQ_USERNAME) && !$UserSystemFormatConfig->checkUserName($REQ_USERNAME)){
            return ReturnableResponse::fromIncorrectFormattedParam('username')->toResponse($response);
        }
        if(!empty($REQ_EMAIL) && !$UserSystemFormatConfig->checkEmailAddr($REQ_EMAIL)){
            return ReturnableResponse::fromIncorrectFormattedParam('email')->toResponse($response);
        }
        $parsedPhone = null;
        if(!empty($REQ_PHONE)){
            try{
                $parsedPhone = UserPhoneUtil::parsePhone($REQ_PHONE);
            }catch(PDKInnerArgumentError $e){
                return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
            }
            if(!UserPhoneUtil::verifyPhoneNumberObj($parsedPhone)){
                return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
            }
        }
        $userEntity = null;
        $loginMethod = '';
        if(!empty($REQ_USERNAME)){
            $userEntity = $UserEntityStorage->getUserEntityByUsername($REQ_USERNAME);
            $loginMethod = 'username';
        }else if(!empty($REQ_EMAIL)){
            $userEntity = $UserEntityStorage->getUserEntityByEmail($REQ_EMAIL);
            $loginMethod = 'email';
        }else{
            $userEntity = $UserEntityStorage->getUserEntityByPhoneNum($parsedPhone);
            $loginMethod = 'phone';
        }
        if($userEntity === null){
            return ReturnableResponse::fromCredentialMismatchError('password')->toResponse($response);
        }
        if(!$userEntity->checkPassword($REQ_PASSWORD)){
            return ReturnableResponse::fromCredentialMismatchError('password')->toResponse($response);
        }
        $reasonReceiver = LoginFailedReasons::UNKNOWN;
        if(!$userEntity->checkIfCanLogin($reasonReceiver)){
            $result = ReturnableResponse::fromPermissionDeniedError('you cannot login at this time');
            $result->returnFirstLevelEntries['errorReason'] = $reasonReceiver;
            if($reasonReceiver === LoginFailedReasons::EMAIL_NOT_VERIFIED && !empty($userEntity->getEmail())){
                $result->returnDataLevelEntries['email'] = $userEntity->getEmail();
            }else if($reasonReceiver === LoginFailedReasons::PHONE_NOT_VERIFIED && $userEntity->getPhoneNumber() != null){
                $result->returnDataLevelEntries['phone'] = UserPhoneUtil::outputPhoneNumberE164($userEntity->getPhoneNumber());
            }
            return $result->toResponse($response);
        }
        $tokenEntity = null;
        try{
            $tokenEntity = PDK2021Wrapper::$pdkCore->createNewTokenAndSave($userEntity,$ctime,PDK2021Wrapper::$config->TOKEN_AVAILABLE_DURATION,PDK2021Wrapper::$config->REFRESH_TOKEN_AVAILABLE_DURATION,$REMOTE_ADDR,$DeviceUA);
        }catch(PDKException $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $successReturn = new ReturnableResponse(201,0);
        $successReturn->returnDataLevelEntries = array(
            "access_token" => $tokenEntity->getTokenStr(),
            "refresh_token" => $tokenEntity->getRefreshTokenStr(),
            "expire_time" => $tokenEntity->expireTime,
            "refresh_expire" => $tokenEntity->refreshTokenExpireTime,
            "user" => UserOutputUtil::getUserEntityAsAssocArray($userEntity)
        );
        return $successReturn->toResponse($response);
    }
    public function checkTokenValid(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_QUERY_PARAMS = $request->getQueryParams();
        $REQ_ACCESS_TOKEN = $REQ_QUERY_PARAMS['access_token'];
        $REQ_UID = (int) $REQ_QUERY_PARAMS['uid'];
        $ctime = time();

        if(!TokenFormat::isValidToken($REQ_ACCESS_TOKEN)){
            return ReturnableResponse::fromIncorrectFormattedParam('access_token')->toResponse($response);
        }
        if(empty($REQ_UID) || $REQ_UID < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('uid')->toResponse($response);
        }else{
            $REQ_UID = (int) $REQ_UID;
        }
        $TokenEntityStorage = PDK2021Wrapper::$pdkCore->getTokenEntityStorage();
        $TokenEntity = $TokenEntityStorage->getTokenEntity($REQ_ACCESS_TOKEN);
        if($TokenEntity === null){
            return ReturnableResponse::fromCredentialMismatchError('access_token')->toResponse($response);
        }
        if($TokenEntity->getRelatedUID() !== $REQ_UID){
            return ReturnableResponse::fromCredentialMismatchError('access_token')->toResponse($response);
        }
        if(!$TokenEntity->isValid($ctime)){
            return ReturnableResponse::fromItemExpiredOrUsedError('access_token')->toResponse($response);
        }
        $returnResult = new ReturnableResponse(200,0);
        return $returnResult->toResponse($response);
    }
    public function refreshToken(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_QUERY_PARAMS = $request->getQueryParams();
        $REQ_REFRESH_TOKEN = $REQ_QUERY_PARAMS['refresh_token'];
        $REQ_UID = (int) $REQ_QUERY_PARAMS['uid'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $DEVICE_UA = empty($request->getHeader('User-Agent')) ? null : implode('; ',$request->getHeader('User-Agent'));
        $ctime = time();

        if(!TokenFormat::isValidToken($REQ_REFRESH_TOKEN)){
            return ReturnableResponse::fromIncorrectFormattedParam('refresh_token')->toResponse($response);
        }
        if(empty($REQ_UID) || $REQ_UID < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('uid')->toResponse($response);
        }else{
            $REQ_UID = (int) $REQ_UID;
        }

        $TokenEntityStorage = PDK2021Wrapper::$pdkCore->getTokenEntityStorage();
        $TokenEntity = $TokenEntityStorage->getTokenEntitybyRefreshToken($REQ_REFRESH_TOKEN);
        if($TokenEntity === null){
            return ReturnableResponse::fromCredentialMismatchError('refresh_token')->toResponse($response);
        }
        if($TokenEntity->getRelatedUID() !== $REQ_UID){
            return ReturnableResponse::fromCredentialMismatchError('refresh_token')->toResponse($response);
        }
        if(!$TokenEntity->isRefreshValid($ctime)){
            return ReturnableResponse::fromItemExpiredOrUsedError('refresh_token')->toResponse($response);
        }
        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        try{
        $UserEntity = $UserEntityStorage->getUserEntityByUID($REQ_UID);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('cannot find the user entity of an existing token')->toResponse($response);
        }
        $newAllocatedToken = null;
        try{
            $newAllocatedToken = PDK2021Wrapper::$pdkCore->createNewTokenAndSave($UserEntity,$ctime,PDK2021Wrapper::$config->TOKEN_AVAILABLE_DURATION,PDK2021Wrapper::$config->REFRESH_TOKEN_AVAILABLE_DURATION,$REMOTE_ADDR,$DEVICE_UA);
        }catch(PDKException $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        
        $TokenEntity->valid = false;
        try{
            $TokenEntityStorage->updateTokenEntity($TokenEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $successReturn = new ReturnableResponse(201,0);
        $successReturn->returnDataLevelEntries = array(
            "access_token" => $newAllocatedToken->getTokenStr(),
            "refresh_token" => $newAllocatedToken->getRefreshTokenStr(),
            "expire_time" => $newAllocatedToken->expireTime,
            "refresh_expire" => $newAllocatedToken->refreshTokenExpireTime,
            "user" => UserOutputUtil::getUserEntityAsAssocArray($UserEntity)
        );
        return $successReturn->toResponse($response);
    }
}