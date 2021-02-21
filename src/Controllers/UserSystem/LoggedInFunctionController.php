<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use GuzzleHttp\Psr7\ServerRequest;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\Controllers\VeriCode\VeriCodeController;
use InteractivePlus\PDK2021\OutputUtils\UserOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKInnerArgumentError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKSenderServiceError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeID;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeIDs;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\UserSystemFormatSetting;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LoggedInFunctionController{
    public function changeEmailAddress(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_VERIFICATION_CODE = $REQ_PARAMS['veriCode'];
        $REQ_NEW_EMAIL = $REQ_PARAMS['new_email'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        $realNewEmail = '';
        $mustNotHaveExistingEmail = true;

        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $VeriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();
        $UserEntity = null;

        if(!empty($REQ_VERIFICATION_CODE)){
            //check verification code
            $mustNotHaveExistingEmail = false;
            $checkVerificationCodeResponse = VeriCodeController::getCheckAnyVeriCodeResponse($REQ_VERIFICATION_CODE,$REQ_UID,VeriCodeIDs::VERICODE_CHANGE_EMAIL()->getVeriCodeID(),$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
            if(!$checkVerificationCodeResponse->succeed){
                return $checkVerificationCodeResponse->returnableResponse->toResponse($response);
            }
            $realNewEmail = $checkVerificationCodeResponse->veriCode->getVeriCodeParam('new_email');
            try{
                $UserEntity = $UserEntityStorage->getUserEntityByUID($checkVerificationCodeResponse->veriCode->getUID());
                $VeriCodeStorage->useVeriCodeEntity($checkVerificationCodeResponse->veriCode->getVeriCodeString());
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            $mustNotHaveExistingEmail = true;
            if(empty($REQ_NEW_EMAIL) || !is_string($REQ_NEW_EMAIL) || !$UserSystemFormatConfig->checkEmailAddr($REQ_NEW_EMAIL)){ //check new email
                return ReturnableResponse::fromIncorrectFormattedParam('new_email')->toResponse($response);
            }
            $checkAccessTokenResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
            if($checkAccessTokenResponse !== null){
                return $checkAccessTokenResponse->toResponse($response);
            }
            $realNewEmail = $REQ_NEW_EMAIL;
            try{
                $UserEntity = $UserEntityStorage->getUserEntityByUID($REQ_UID);
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }
        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('could not find user entity with a valid access_token/veriCode')->toResponse($response);
        }
        if($mustNotHaveExistingEmail && !empty($UserEntity->getEmail())){
            return ReturnableResponse::fromPermissionDeniedError('You cannot change your email address without a veriCode if there is already an email address set')->toResponse($response);
        }
        if($UserEntityStorage->checkEmailExist($realNewEmail) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('new_email')->toResponse($response);
        }
        $UserEntity->setEmail($realNewEmail);
        $UserEntity->setEmailVerified(false);
        $VeriCodeStorage->clearVeriCode(0,0,0,0,$UserEntity->getUID(),APPSystemConstants::INTERACTIVEPDK_APPUID,VeriCodeIDs::VERICODE_VERIFY_EMAIL()->getVeriCodeID());

        try{
            $UserEntityStorage->updateUserEntity($UserEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        
        //create and send verification email to new email
        $veriCodePDKError = PDK2021Wrapper::$pdkCore->createAndSendVerificationEmail($realNewEmail,$UserEntity,$ctime,PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,$REMOTE_ADDR);
        if($veriCodePDKError !== null){
            return ReturnableResponse::fromPDKException($veriCodePDKError)->toResponse($response);
        }

        $result = new ReturnableResponse(200,0);
        return $result->toResponse($response);
    }
    public function changePhoneNumber(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_VERIFICATION_CODE = $REQ_PARAMS['veriCode'];
        $REQ_NEW_PHONE = $REQ_PARAMS['new_phone'];
        $REQ_PREFERRED_METHOD = $REQ_PARAMS['preferred_send_method'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        if(empty($REQ_PREFERRED_METHOD) || ($REQ_PREFERRED_METHOD != SentMethod::SMS_MESSAGE && $REQ_PREFERRED_METHOD != SentMethod::PHONE_CALL)){
            $REQ_PREFERRED_METHOD = SentMethod::SMS_MESSAGE;
        }else{
            $REQ_PREFERRED_METHOD = (int) $REQ_PREFERRED_METHOD;
        }

        $realNewPhoneObj = null;
        $mustNotHaveExistingPhone = true;

        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $VeriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();
        $UserEntity = null;

        if(!empty($REQ_VERIFICATION_CODE)){
            //check verification code
            $mustNotHaveExistingPhone = false;
            $checkVerificationCodeResponse = VeriCodeController::getCheckAnyVeriCodeResponse($REQ_VERIFICATION_CODE,$REQ_UID,VeriCodeIDs::VERICODE_CHANGE_PHONE()->getVeriCodeID(),$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
            if(!$checkVerificationCodeResponse->succeed){
                return $checkVerificationCodeResponse->returnableResponse->toResponse($response);
            }
            try{
                $realNewPhoneObj = UserPhoneUtil::parsePhone($checkVerificationCodeResponse->veriCode->getVeriCodeParam('new_phone'));
            }catch(PDKInnerArgumentError $e){
                return ReturnableResponse::fromInnerError('could not parse stored new_phone data')->toResponse($response);
            }
            try{
                $UserEntity = $UserEntityStorage->getUserEntityByUID($checkVerificationCodeResponse->veriCode->getUID());
                $VeriCodeStorage->useVeriCodeEntity($checkVerificationCodeResponse->veriCode->getVeriCodeString());
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            $mustNotHaveExistingPhone = true;
            if(empty($REQ_NEW_PHONE) || !is_string($REQ_NEW_PHONE)){ //check new phone
                return ReturnableResponse::fromIncorrectFormattedParam('new_phone')->toResponse($response);
            }
            try{
                $realNewPhoneObj = UserPhoneUtil::parsePhone($REQ_NEW_PHONE);
            }catch(PDKInnerArgumentError $e){
                return ReturnableResponse::fromIncorrectFormattedParam('new_phone')->toResponse($response);
            }
            $checkAccessTokenResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
            if($checkAccessTokenResponse !== null){
                return $checkAccessTokenResponse->toResponse($response);
            }
            try{
                $UserEntity = $UserEntityStorage->getUserEntityByUID($REQ_UID);
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }
        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('could not find user entity with a valid access_token/veriCode')->toResponse($response);
        }
        if($realNewPhoneObj === null){
            return ReturnableResponse::fromInnerError('phone number obj is null');
        }
        if($mustNotHaveExistingPhone && $UserEntity->getPhoneNumber() !== null){
            return ReturnableResponse::fromPermissionDeniedError('You cannot change your phone number without a veriCode if there is already a phone number set')->toResponse($response);
        }
        if($UserEntityStorage->checkPhoneNumExist($realNewPhoneObj) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('new_phone')->toResponse($response);
        }
        $UserEntity->setPhoneNumber($realNewPhoneObj);
        $UserEntity->setPhoneVerified(false);
        $VeriCodeStorage->clearVeriCode(0,0,0,0,$UserEntity->getUID(),APPSystemConstants::INTERACTIVEPDK_APPUID,VeriCodeIDs::VERICODE_VERIFY_PHONE()->getVeriCodeID());

        try{
            $UserEntityStorage->updateUserEntity($UserEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        
        //create and send verification SMS/Call to new Phone Num
        $preferSMS = $REQ_PREFERRED_METHOD === SentMethod::SMS_MESSAGE;
        $methodReceiver = SentMethod::NOT_SENT;
        $veriCodePDKError = PDK2021Wrapper::$pdkCore->createAndSendVerificationPhone($realNewPhoneObj,$UserEntity,$ctime,PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,$methodReceiver,$REMOTE_ADDR,$preferSMS);
        if($veriCodePDKError !== null){
            return ReturnableResponse::fromPDKException($veriCodePDKError)->toResponse($response);
        }

        $result = new ReturnableResponse(200,0);
        $result->returnDataLevelEntries['sent_method'] = $methodReceiver;
        return $result->toResponse($response);
    }
    public function changeUserInfo(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $changeNickname = false;
        $changeSignature = false;
        $REQ_NICKNAME = null;
        $REQ_SIGNATURE = null;
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];

        $ctime = time();

        if(isset($REQ_PARAMS['nickname'])){
            $REQ_NICKNAME = $REQ_PARAMS['nickname'];
            $changeNickname = true;
        }
        if(isset($REQ_PARAMS['signature'])){
            $REQ_SIGNATURE = $REQ_PARAMS['signature'];
            $changeSignature = true;
        }
        if(!$changeNickname && !$changeSignature){
            return ReturnableResponse::fromIncorrectFormattedParam('nickname|signature')->toResponse($response);
        }
        
        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();

        if($changeNickname && $REQ_NICKNAME !== null && (!is_string($REQ_NICKNAME) || !$UserSystemFormatConfig->checkNickName($REQ_NICKNAME))){
            return ReturnableResponse::fromIncorrectFormattedParam('nickname')->toResponse($response);
        }
        if($changeSignature && $REQ_SIGNATURE !== null && (!is_string($REQ_SIGNATURE) || !$UserSystemFormatConfig->checkSignature($REQ_SIGNATURE))){
            return ReturnableResponse::fromIncorrectFormattedParam('signature')->toResponse($response);
        }

        //check if user credential is valid
        $credentialCheckResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($credentialCheckResponse !== null){
            return $credentialCheckResponse->toResponse($response);
        }

        //Find User Entity and Edit
        $UserEntity = null;
        try{
            $UserEntity = $UserEntityStorage->getUserEntityByUID((int) $REQ_UID);
            if($UserEntity === null){
                return ReturnableResponse::fromInnerError('could not find user entity with a valid access token')->toResponse($response);
            }
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }

        $nicknameCheckUID = $UserEntityStorage->checkNicknameExist($REQ_NICKNAME);
        if($nicknameCheckUID !== $UserEntity->getUID() && $nicknameCheckUID !== -1){
            return ReturnableResponse::fromItemAlreadyExist('nickname');
        }
        
        if($changeNickname){
            $UserEntity->setNickName($REQ_NICKNAME);
        }
        if($changeSignature){
            $UserEntity->setSignature($REQ_SIGNATURE);
        }

        //Update User Entity
        try{
            $UserEntityStorage->updateUserEntity($UserEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }

        $finalResponse = new ReturnableResponse(200,0);
        $finalResponse->returnDataLevelEntries['user'] = UserOutputUtil::getUserEntityAsAssocArray($UserEntity);
        return $finalResponse->toResponse($response);
    }
}