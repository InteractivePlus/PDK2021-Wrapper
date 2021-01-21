<?php
namespace InteractivePlus\PDK2021\Controllers\VeriCode;

use InteractivePlus\PDK2021\Controllers\Captcha\SimpleCaptchaController;
use InteractivePlus\PDK2021\Controllers\CheckVericodeResponse;
use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\Controllers\UserSystem\LoginController;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKInnerArgumentError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemAlreadyExistError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKRequestParamFormatError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKSenderServiceError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Exception\PDKErrCode;
use InteractivePlus\PDK2021Core\Base\Exception\PDKException;
use InteractivePlus\PDK2021Core\Base\Logger\ActionID;
use InteractivePlus\PDK2021Core\Base\Logger\LogEntity;
use InteractivePlus\PDK2021Core\Base\Logger\PDKLogLevel;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeID;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeIDs;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\Login\LoginFailedReasons;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntityStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class VeriCodeController{
    public static function getCheckVerificationCodeResponse($verificationCode, int $vericodeID, int $currentTime, int $appuid) : CheckVericodeResponse{
        if(empty($verificationCode) || !is_string($verificationCode) || !VeriCodeFormat::isValidVerificationCode($verificationCode)){
            return new CheckVericodeResponse(false,ReturnableResponse::fromIncorrectFormattedParam('veriCode'),null);
        }
        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $veriCodeEntity = $veriCodeStorage->getVeriCodeEntity($verificationCode);
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

    public static function getCheckAnyVeriCodeResponse($verificationCode, $uid, int $vericodeID, int $currentTime, int $appuid) : CheckVericodeResponse{
        if(!empty($verificationCode) && is_string($verificationCode) && VeriCodeFormat::isValidPartialPhoneVerificationCode($verificationCode)){
            return self::getCheckPhonePartialCodeResponse($verificationCode,$uid,$vericodeID,$currentTime, $appuid);
        }else{
            return self::getCheckVerificationCodeResponse($verificationCode,$vericodeID,$currentTime, $appuid);
        }
    }

    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_VERICODE = $args['veriCode'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        //first check if this vericode is a valid one
        $checkVeriCodeResponse = self::getCheckVerificationCodeResponse($REQ_VERICODE,VeriCodeIDs::VERICODE_VERIFY_EMAIL()->getVeriCodeID(),$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
        if(!$checkVeriCodeResponse->succeed){
            return $checkVeriCodeResponse->returnableResponse->toResponse($response);
        }

        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $veriCodeEntity = $checkVeriCodeResponse->veriCode;

        $userEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $relatedUser = $userEntityStorage->getUserEntityByUID($veriCodeEntity->getUID());
        if($relatedUser === null){
            PDK2021Wrapper::$pdkCore->getLogger()->addLogItem(new LogEntity(
                ActionID::PSRLog,
                APPSystemConstants::INTERACTIVEPDK_APPUID,
                $veriCodeEntity->getUID(),
                $ctime,
                PDKLogLevel::CRITICAL,
                false,
                PDKErrCode::ITEM_NOT_FOUND_ERROR,
                $REMOTE_ADDR,
                'could not find the related user of a valid verification code',
                array(
                    'vericode' => $REQ_VERICODE
                )
            ));
            return ReturnableResponse::fromInnerError('for some reason the related user of this vericode is not found in the database')->toResponse($response);
        }
        if(empty($relatedUser->getEmail())){
            PDK2021Wrapper::$pdkCore->getLogger()->addLogItem(new LogEntity(
                ActionID::PSRLog,
                APPSystemConstants::INTERACTIVEPDK_APPUID,
                $veriCodeEntity->getUID(),
                $ctime,
                PDKLogLevel::CRITICAL,
                false,
                PDKErrCode::ITEM_NOT_FOUND_ERROR,
                $REMOTE_ADDR,
                'A user entity without a valid email address tried to verify its email with a valid veriCode',
                array(
                    'vericode' => $REQ_VERICODE
                )
            ));
        }
        $relatedUser->setEmailVerified(true);
        try{
            $updateResult = $userEntityStorage->updateUserEntity($relatedUser);
            if(!$updateResult){
                return ReturnableResponse::fromInnerError('failed to update user entity')->toResponse($response);
            }
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        try{
            $veriCodeStorage->useVeriCodeEntity($veriCodeEntity->getVeriCodeString());
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $successReturnable = new ReturnableResponse(200,0);
        $successReturnable->returnDataLevelEntries = array(
            'username' => $relatedUser->getUsername(),
            'nickname' => $relatedUser->getNickName(),
            'email' => $relatedUser->getEmail()
        );
        return $successReturnable->toResponse($response);
    }
    public function verifyPhone(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_VERICODE = $args['veriCode'];
        $REQ_GET_PARAMS = $request->getQueryParams();
        $REQ_UID = $REQ_GET_PARAMS['uid'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        //first check if this vericode is a valid one
        $checkVeriCodeResponse = self::getCheckPhonePartialCodeResponse($REQ_VERICODE,$REQ_UID,VeriCodeIDs::VERICODE_VERIFY_PHONE()->getVeriCodeID(),$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
        if(!$checkVeriCodeResponse->succeed){
            return $checkVeriCodeResponse->returnableResponse->toResponse($response);
        }

        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $veriCodeEntity = $checkVeriCodeResponse->veriCode;

        $userEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $relatedUser = $userEntityStorage->getUserEntityByUID($veriCodeEntity->getUID());
        if($relatedUser === null){
            PDK2021Wrapper::$pdkCore->getLogger()->addLogItem(new LogEntity(
                ActionID::PSRLog,
                APPSystemConstants::INTERACTIVEPDK_APPUID,
                $veriCodeEntity->getUID(),
                $ctime,
                PDKLogLevel::CRITICAL,
                false,
                PDKErrCode::ITEM_NOT_FOUND_ERROR,
                $REMOTE_ADDR,
                'could not find the related user of a valid verification code',
                array(
                    'vericode' => $REQ_VERICODE
                )
            ));
            return ReturnableResponse::fromInnerError('for some reason the related user of this vericode is not found in the database')->toResponse($response);
        }
        if($relatedUser->getPhoneNumber() === null){
            PDK2021Wrapper::$pdkCore->getLogger()->addLogItem(new LogEntity(
                ActionID::PSRLog,
                APPSystemConstants::INTERACTIVEPDK_APPUID,
                $veriCodeEntity->getUID(),
                $ctime,
                PDKLogLevel::CRITICAL,
                false,
                PDKErrCode::ITEM_NOT_FOUND_ERROR,
                $REMOTE_ADDR,
                'A user entity without a valid phone number tried to verify its phone number with a valid veriCode',
                array(
                    'vericode' => $REQ_VERICODE
                )
            ));
        }
        $relatedUser->setPhoneVerified(true);
        try{
            $updateResult = $userEntityStorage->updateUserEntity($relatedUser);
            if(!$updateResult){
                return ReturnableResponse::fromInnerError('failed to update user entity')->toResponse($response);
            }
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        try{
            $veriCodeStorage->useVeriCodeEntity($veriCodeEntity->getVeriCodeString());
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $successReturnable = new ReturnableResponse(200,0);
        $successReturnable->returnDataLevelEntries = array(
            'username' => $relatedUser->getUsername(),
            'nickname' => $relatedUser->getNickName(),
            'phone' => UserPhoneUtil::outputPhoneNumberE164($relatedUser->getPhoneNumber())
        );
        return $successReturnable->toResponse($response);
    }
    public function requestVerificationEmailResend(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_EMAIL = $REQ_PARAMS['email'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        $userEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $userSystemConfig = $userEntityStorage->getFormatSetting();
        if(!$userSystemConfig->checkEmailAddr($REQ_EMAIL)){
            return ReturnableResponse::fromIncorrectFormattedParam('email')->toResponse($response);
        }

        $REQ_CAPTCHA_ID = $REQ_PARAMS['captcha_id'];
        $captchaResponse = SimpleCaptchaController::useAndCheckCaptchaResult($REQ_CAPTCHA_ID);
        if($captchaResponse !== null){
            return $captchaResponse->toResponse($response);
        }

        $userEntity = $userEntityStorage->getUserEntityByEmail($REQ_EMAIL);
        if($userEntity === null){
            return ReturnableResponse::fromItemNotFound('email')->toResponse($response);
        }
        if($userEntity->isEmailVerified()){
            return ReturnableResponse::fromItemAlreadyExist('email')->toResponse($response);
        }


        $optionalException = PDK2021Wrapper::$pdkCore->createAndSendVerificationEmail(
            $userEntity->getEmail(),
            $userEntity,
            $ctime,
            PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
            $REMOTE_ADDR
        );
        if($optionalException !== null){
            return ReturnableResponse::fromPDKException($optionalException)->toResponse($response);
        }
        $finalResponse = new ReturnableResponse(201,0);
        return $finalResponse->toResponse($response);
    }
    public function requestVerificationPhoneResend(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_PHONE = $REQ_PARAMS['phone'];
        $REQ_PREFER_SEND_METHOD = $REQ_PARAMS['preferred_send_method'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        if(empty($REQ_PREFER_SEND_METHOD) || $REQ_PREFER_SEND_METHOD == SentMethod::EMAIL || $REQ_PREFER_SEND_METHOD == SentMethod::NOT_SENT || !SentMethod::isSentMethodValid($REQ_PREFER_SEND_METHOD)){
            $REQ_PREFER_SEND_METHOD = SentMethod::SMS_MESSAGE;
        }else{
            $REQ_PREFER_SEND_METHOD = (int) $REQ_PREFER_SEND_METHOD;
        }

        $REQ_CAPTCHA_ID = $REQ_PARAMS['captcha_id'];
        $captchaResponse = SimpleCaptchaController::useAndCheckCaptchaResult($REQ_CAPTCHA_ID);
        if($captchaResponse !== null){
            return $captchaResponse->toResponse($response);
        }

        $userEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $userSystemConfig = $userEntityStorage->getFormatSetting();
        
        $parsedPhone = null;
        try{
            $parsedPhone = UserPhoneUtil::parsePhone($REQ_PHONE);
        }catch(PDKInnerArgumentError $e){
            return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
        }
        if(!UserPhoneUtil::verifyPhoneNumberObj($parsedPhone)){
            return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
        }
        $userEntity = $userEntityStorage->getUserEntityByPhoneNum($parsedPhone);
        if($userEntity === null){
            return ReturnableResponse::fromItemNotFound('phone')->toResponse($response);
        }
        if($userEntity->isPhoneVerified()){
            return ReturnableResponse::fromItemAlreadyExist('phone')->toResponse($response);
        }
        $methodReceiver = SentMethod::NOT_SENT;
        $optionalException = PDK2021Wrapper::$pdkCore->createAndSendVerificationPhone(
            $userEntity->getPhoneNumber(),
            $userEntity,
            $ctime,
            PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
            $methodReceiver,
            $REMOTE_ADDR,
            $REQ_PREFER_SEND_METHOD !== SentMethod::PHONE_CALL
        );
        if($optionalException !== null){
            return ReturnableResponse::fromPDKException($optionalException)->toResponse($response);
        }
        $finalResponse = new ReturnableResponse(201,0);
        $finalResponse->returnDataLevelEntries = array(
            'phoneVerificationSentMethod' => $methodReceiver
        );
        return $finalResponse->toResponse($response);
    }

    public function requestChangeEmailAddressVeriCode(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_NEW_EMAIL = $REQ_PARAMS['new_email'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_PREF_SEND_METHOD = $REQ_PARAMS['preferred_send_method'];

        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        
        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();
        $VeriCodeEntityStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();

        if(empty($REQ_NEW_EMAIL) || !$UserSystemFormatConfig->checkEmailAddr($REQ_NEW_EMAIL)){
            return ReturnableResponse::fromIncorrectFormattedParam('new_email')->toResponse($response);
        }
        if(empty($REQ_UID) || $REQ_UID < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('uid')->toResponse($response);
        }else{
            $REQ_UID = (int) $REQ_UID;
        }
        if(empty($REQ_PREF_SEND_METHOD) || $REQ_PREF_SEND_METHOD == SentMethod::NOT_SENT || !SentMethod::isSentMethodValid((int) $REQ_PREF_SEND_METHOD)){
            $REQ_PREF_SEND_METHOD = SentMethod::EMAIL;
        }else{
            $REQ_PREF_SEND_METHOD = (int) $REQ_PREF_SEND_METHOD;
        }

        //check token entity
        $tokenCheckResponse = LoginController::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($tokenCheckResponse !== null){
            return $tokenCheckResponse->toResponse($response);
        }
        
        //check if user email is set or not
        $UserEntity = null;
        try{
            $UserEntity = $UserEntityStorage->getUserEntityByUID($REQ_UID);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('A user entity with valid token could not be found in the database')->toResponse($response);
        }
        if(empty($UserEntity->getEmail())){
            return ReturnableResponse::fromPermissionDeniedError('Why would you request a swap-email verification code if you don\'t even need one?')->toResponse($response);
        }

        //check if the email conflicts with any existing user
        //we don't even have to check if the existing email addr = new email addr because we are not comparing the user having the new email with the current user, but comparing the uid having the email with -1.
        if($UserEntityStorage->checkEmailExist($REQ_NEW_EMAIL) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('new_email')->toResponse($response);
        }

        //determine which method to send
        $actualSendingMethod = SentMethod::NOT_SENT;
        $veriCode = new VeriCodeEntity(
            VeriCodeIDs::VERICODE_CHANGE_EMAIL(),
            $ctime,
            $ctime + PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
            $UserEntity->getUID(),
            APPSystemConstants::INTERACTIVEPDK_APPUID,
            array(
                'new_email' => $REQ_NEW_EMAIL
            ),
            $REMOTE_ADDR
        );
        while($VeriCodeEntityStorage->checkVeriCodeExist($veriCode->getVeriCodeString())){
            $veriCode = $veriCode->withVeriCodeStringReroll();
        }
        if(($REQ_PREF_SEND_METHOD === SentMethod::EMAIL && !empty($UserEntity->getEmail()) && $UserEntity->isEmailVerified()) || ($REQ_PREF_SEND_METHOD !== SentMethod::EMAIL && ($UserEntity->getPhoneNumber() === null || !$UserEntity->isPhoneVerified()))){
            $actualSendingMethod = SentMethod::EMAIL;
            try{
                PDK2021Wrapper::$pdkCore->getVeriCodeEmailSender()->sendVeriCode($veriCode,$UserEntity,$UserEntity->getEmail());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else if($UserEntity->getPhoneNumber() !== null && $UserEntity->isPhoneVerified()){
            $sender = PDK2021Wrapper::$pdkCore->getPhoneSender($actualSendingMethod,$REQ_PREF_SEND_METHOD !== SentMethod::PHONE_CALL);
            try{
                $sender->sendVeriCode($veriCode,$UserEntity,$UserEntity->getPhoneNumber());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            return ReturnableResponse::fromItemNotFound('communication_method')->toResponse($response);
        }
        $veriCode = $veriCode->withSentMethod($actualSendingMethod);
        $updatedEntity = null;
        try{
            $updatedEntity = $VeriCodeEntityStorage->addVeriCodeEntity($veriCode,false);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($updatedEntity === null){
            return ReturnableResponse::fromInnerError('failed to add vericode to database')->toResponse($response);
        }

        $returnResult = new ReturnableResponse(201,0);
        $returnResult->returnDataLevelEntries['sent_method'] = $actualSendingMethod;
        return $returnResult->toResponse($response);
    }
    
    public function requestChangePhoneNumVeriCode(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_NEW_PHONE = $REQ_PARAMS['new_phone'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_PREF_SEND_METHOD = $REQ_PARAMS['preferred_send_method'];

        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        
        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();
        $VeriCodeEntityStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();

        if(empty($REQ_NEW_PHONE) || !is_string($REQ_NEW_PHONE)){
            return ReturnableResponse::fromIncorrectFormattedParam('new_phone')->toResponse($response);
        }
        $parsedNewPhone = null;
        try{
            $parsedNewPhone = UserPhoneUtil::parsePhone($REQ_NEW_PHONE);
        }catch(PDKInnerArgumentError $e){
            return ReturnableResponse::fromIncorrectFormattedParam('new_phone')->toResponse($response);
        }
        if(!UserPhoneUtil::verifyPhoneNumberObj($parsedNewPhone)){
            return ReturnableResponse::fromIncorrectFormattedParam('new_phone')->toResponse($response);
        }
        if(empty($REQ_UID) || $REQ_UID < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('uid')->toResponse($response);
        }else{
            $REQ_UID = (int) $REQ_UID;
        }
        if(empty($REQ_PREF_SEND_METHOD) || $REQ_PREF_SEND_METHOD == SentMethod::NOT_SENT || !SentMethod::isSentMethodValid((int) $REQ_PREF_SEND_METHOD)){
            $REQ_PREF_SEND_METHOD = SentMethod::SMS_MESSAGE;
        }else{
            $REQ_PREF_SEND_METHOD = (int) $REQ_PREF_SEND_METHOD;
        }

        //check token entity
        $tokenCheckResponse = LoginController::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($tokenCheckResponse !== null){
            return $tokenCheckResponse->toResponse($response);
        }
        
        //check if user phone is set or not
        $UserEntity = null;
        try{
            $UserEntity = $UserEntityStorage->getUserEntityByUID($REQ_UID);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('A user entity with valid token could not be found in the database')->toResponse($response);
        }
        if($UserEntity->getPhoneNumber() === null){
            return ReturnableResponse::fromPermissionDeniedError('Why would you request a swap-phone-number verification code if you don\'t even need one?')->toResponse($response);
        }

        //check if the phone number conflicts with any existing user
        //we don't even have to check if the existing phone number = new phone addr because we are not comparing the user having the new phone with the current user, but comparing the uid having the phone with -1.
        if($UserEntityStorage->checkPhoneNumExist($parsedNewPhone) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('new_phone')->toResponse($response);
        }

        //determine which method to send
        $actualSendingMethod = SentMethod::NOT_SENT;
        $veriCode = new VeriCodeEntity(
            VeriCodeIDs::VERICODE_CHANGE_PHONE(),
            $ctime,
            $ctime + PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
            $UserEntity->getUID(),
            APPSystemConstants::INTERACTIVEPDK_APPUID,
            array(
                'new_phone' => UserPhoneUtil::outputPhoneNumberE164($parsedNewPhone)
            ),
            $REMOTE_ADDR
        );
        while($VeriCodeEntityStorage->checkVeriCodeExist($veriCode->getVeriCodeString())){
            $veriCode = $veriCode->withVeriCodeStringReroll();
        }

        if(($REQ_PREF_SEND_METHOD === SentMethod::EMAIL && !empty($UserEntity->getEmail()) && $UserEntity->isEmailVerified()) || ($REQ_PREF_SEND_METHOD !== SentMethod::EMAIL && ($UserEntity->getPhoneNumber() === null || !$UserEntity->isPhoneVerified()))){
            $actualSendingMethod = SentMethod::EMAIL;
            try{
                PDK2021Wrapper::$pdkCore->getVeriCodeEmailSender()->sendVeriCode($veriCode,$UserEntity,$UserEntity->getEmail());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else if($UserEntity->getPhoneNumber() !== null && $UserEntity->isPhoneVerified()){
            $sender = PDK2021Wrapper::$pdkCore->getPhoneSender($actualSendingMethod,$REQ_PREF_SEND_METHOD !== SentMethod::PHONE_CALL);
            try{
                $sender->sendVeriCode($veriCode,$UserEntity,$UserEntity->getPhoneNumber());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            return ReturnableResponse::fromItemNotFound('communication_method')->toResponse($response);
        }
        $veriCode = $veriCode->withSentMethod($actualSendingMethod);
        $updatedEntity = null;
        try{
            $updatedEntity = $VeriCodeEntityStorage->addVeriCodeEntity($veriCode,false);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($updatedEntity === null){
            return ReturnableResponse::fromInnerError('failed to add vericode to database')->toResponse($response);
        }

        $returnResult = new ReturnableResponse(201,0);
        $returnResult->returnDataLevelEntries['sent_method'] = $actualSendingMethod;
        return $returnResult->toResponse($response);
    }
    public function requestChangePassword(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_PREFERRED_METHOD = $REQ_PARAMS['preferred_send_method'];

        $REQ_FORGOT_PWD_EMAIL = $REQ_PARAMS['email'];
        $REQ_FORGOT_PWD_PHONE = $REQ_PARAMS['phone'];
        $REQ_FORGOT_PWD_USERNAME = $REQ_PARAMS['username'];

        $REMOTE_ADDR = $request->getAttribute('ip');
        $BROWSER_UA = empty($request->getHeader('User-Agent')) ? null : implode('; ', $request->getHeader('User-Agent'));
        $ctime = time();

        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();
        $VericodeEntityStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();

        if(empty($REQ_PREFERRED_METHOD) || $REQ_PREFERRED_METHOD == SentMethod::NOT_SENT || !SentMethod::isSentMethodValid($REQ_PREFERRED_METHOD)){
            $REQ_PREFERRED_METHOD = SentMethod::EMAIL;
        }else{
            $REQ_PREFERRED_METHOD = (int) $REQ_PREFERRED_METHOD;
        }
        
        $VeriCodeID = empty($REQ_ACCESS_TOKEN) ? VeriCodeIDs::VERICODE_FORGET_PASSWORD() : VeriCodeIDs::VERICODE_CHANGE_PASSWORD();
        $UserEntity = null;
        if(!empty($REQ_ACCESS_TOKEN)){
            $checkTokenResult = LoginController::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
            if($checkTokenResult !== null){
                return $checkTokenResult->toResponse($response);
            }
            try{
                $UserEntity = $UserEntityStorage->getUserEntityByUID((int) $REQ_UID);
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            $REQ_CAPTCHA_ID = $REQ_PARAMS['captcha_id'];
            $captchaResponse = SimpleCaptchaController::useAndCheckCaptchaResult($REQ_CAPTCHA_ID);
            if($captchaResponse !== null){
                return $captchaResponse->toResponse($response);
            }
            if(!empty($REQ_FORGOT_PWD_EMAIL)){
                if(!is_string($REQ_FORGOT_PWD_EMAIL) || !$UserSystemFormatConfig->checkEmailAddr($REQ_FORGOT_PWD_EMAIL)){
                    return ReturnableResponse::fromIncorrectFormattedParam('email')->toResponse($response);
                }
                try{
                    $UserEntity = $UserEntityStorage->getUserEntityByEmail($REQ_FORGOT_PWD_EMAIL);
                }catch(PDKStorageEngineError $e){
                    return ReturnableResponse::fromPDKException($e)->toResponse($response);
                }
                if($UserEntity === null){
                    return ReturnableResponse::fromItemNotFound('email')->toResponse($response);
                }
            }else if(!empty($REQ_FORGOT_PWD_PHONE)){
                if(!is_string($REQ_FORGOT_PWD_PHONE)){
                    return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
                }
                try{
                    $parsedPhone = UserPhoneUtil::parsePhone($REQ_FORGOT_PWD_PHONE);
                    if(!UserPhoneUtil::verifyPhoneNumberObj($parsedPhone)){
                        throw new PDKInnerArgumentError('phone');
                    }
                }catch(PDKInnerArgumentError $e){
                    return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
                }
                try{
                    $UserEntity = $UserEntityStorage->getUserEntityByPhoneNum($parsedPhone);
                }catch(PDKStorageEngineError $e){
                    return ReturnableResponse::fromPDKException($e)->toResponse($response);
                }
                if($UserEntity === null){
                    return ReturnableResponse::fromItemNotFound('phone')->toResponse($response);
                }
            }else if(!empty($REQ_FORGOT_PWD_USERNAME)){
                if(!is_string($REQ_FORGOT_PWD_USERNAME) || !$UserSystemFormatConfig->checkUserName($REQ_FORGOT_PWD_USERNAME)){
                    return ReturnableResponse::fromIncorrectFormattedParam('username')->toResponse($response);
                }
                try{
                    $UserEntity = $UserEntityStorage->getUserEntityByUsername($REQ_FORGOT_PWD_USERNAME);
                }catch(PDKStorageEngineError $e){
                    return ReturnableResponse::fromPDKException($e)->toResponse($response);
                }
                if($UserEntity === null){
                    return ReturnableResponse::fromItemNotFound('username')->toResponse($response);
                }
            }else{
                return ReturnableResponse::fromIncorrectFormattedParam('email|phone|username')->toResponse($response);
            }
            if($UserEntity === null){
                return ReturnableResponse::fromItemNotFound('email|phone|username')->toResponse($response);
            }
            $reasonReceiver = LoginFailedReasons::UNKNOWN;
            if(!$UserEntity->checkIfCanLogin($reasonReceiver)){
                $result = ReturnableResponse::fromPermissionDeniedError('Your account must be verified and not frozen to be able to reset your password');
                $result->returnDataLevelEntries['errorReason'] = $reasonReceiver;
                return $result->toResponse($response);
            }
        }

        if($UserEntity === null){
            return ReturnableResponse::fromInnerError('could not find user entity with a valid access_token')->toResponse($response);
        }
        
        $setPasswordVeriCode = new VeriCodeEntity(
            $VeriCodeID,
            $ctime,
            $ctime + PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
            $UserEntity->getUID(),
            APPSystemConstants::INTERACTIVEPDK_APPUID,
            null,
            $REMOTE_ADDR
        );
        while($VericodeEntityStorage->checkVeriCodeExist($setPasswordVeriCode->getVeriCodeString())){
            $setPasswordVeriCode = $setPasswordVeriCode->withVeriCodeStringReroll();
        }

        $actualSendingMethod = SentMethod::NOT_SENT;
        if(($REQ_PREFERRED_METHOD === SentMethod::EMAIL && !empty($UserEntity->getEmail()) && $UserEntity->isEmailVerified()) || ($REQ_PREFERRED_METHOD !== SentMethod::EMAIL && ($UserEntity->getPhoneNumber() === null || !$UserEntity->isPhoneVerified()))){
            $actualSendingMethod = SentMethod::EMAIL;
            try{
                PDK2021Wrapper::$pdkCore->getVeriCodeEmailSender()->sendVeriCode($setPasswordVeriCode,$UserEntity,$UserEntity->getEmail());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else if($UserEntity->getPhoneNumber() !== null && $UserEntity->isPhoneVerified()){
            $sender = PDK2021Wrapper::$pdkCore->getPhoneSender($actualSendingMethod,$REQ_PREFERRED_METHOD !== SentMethod::PHONE_CALL);
            try{
                $sender->sendVeriCode($setPasswordVeriCode,$UserEntity,$UserEntity->getPhoneNumber());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            return ReturnableResponse::fromItemNotFound('communication_method')->toResponse($response);
        }
        $setPasswordVeriCode = $setPasswordVeriCode->withSentMethod($actualSendingMethod);
        $updatedEntity = null;
        try{
            $updatedEntity = $VericodeEntityStorage->addVeriCodeEntity($setPasswordVeriCode,false);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($updatedEntity === null){
            return ReturnableResponse::fromInnerError('failed to add vericode to database')->toResponse($response);
        }

        $returnResult = new ReturnableResponse(201,0);
        $returnResult->returnDataLevelEntries['sent_method'] = $actualSendingMethod;
        return $returnResult->toResponse($response);
    }
    public function changePassword(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_VERICODE = $REQ_PARAMS['veriCode'];
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_NEW_PWD = $REQ_PARAMS['new_password'];

        $REQ_FORGOT_USERNAME = $REQ_PARAMS['username'];
        $REQ_FORGOT_EMAIL = $REQ_PARAMS['email'];
        $REQ_FORGOT_PHONE = $REQ_PARAMS['phone'];

        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        $UserEntityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();
        $UserSystemFormatConfig = $UserEntityStorage->getFormatSetting();
        
        if(empty($REQ_NEW_PWD) || !is_string($REQ_NEW_PWD) || !$UserSystemFormatConfig->checkPassword($REQ_NEW_PWD)){
            return ReturnableResponse::fromIncorrectFormattedParam('new_password')->toResponse($response);
        }

        $relatedUserEntity = null;
        $relatedUID = null;
        $vericodeID = 0;
        if(!empty($REQ_FORGOT_USERNAME) || !empty($REQ_FORGOT_EMAIL) || !empty($REQ_FORGOT_PHONE)){
            if(!empty($REQ_FORGOT_USERNAME)){
                if(!is_string($REQ_FORGOT_USERNAME) || !$UserSystemFormatConfig->checkUserName($REQ_FORGOT_USERNAME)){
                    return ReturnableResponse::fromIncorrectFormattedParam('username')->toResponse($response);
                }
                try{
                    $relatedUserEntity = $UserEntityStorage->getUserEntityByUsername($REQ_FORGOT_USERNAME);
                }catch(PDKStorageEngineError $e){
                    return ReturnableResponse::fromPDKException($e)->toResponse($response);
                }
            }else if(!empty($REQ_FORGOT_EMAIL)){
                if(!is_string($REQ_FORGOT_EMAIL) || !$UserSystemFormatConfig->checkEmailAddr($REQ_FORGOT_EMAIL)){
                    return ReturnableResponse::fromIncorrectFormattedParam('email')->toResponse($response);
                }
                try{
                    $relatedUserEntity = $UserEntityStorage->getUserEntityByEmail($REQ_FORGOT_EMAIL);
                }catch(PDKStorageEngineError $e){
                    return ReturnableResponse::fromPDKException($e)->toResponse($response);
                }
            }else{
                $usrPhone = null;
                try{
                    if(!is_string($REQ_FORGOT_PHONE)){
                        throw new PDKRequestParamFormatError('phone');
                    }
                    $usrPhone = UserPhoneUtil::parsePhone($REQ_FORGOT_PHONE);
                    if(!UserPhoneUtil::verifyPhoneNumberObj($usrPhone)){
                        throw new PDKRequestParamFormatError('phone');
                    }
                }catch(PDKException $e){
                    return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
                }
                try{
                    $relatedUserEntity = $UserEntityStorage->getUserEntityByPhoneNum($usrPhone);
                }catch(PDKStorageEngineError $e){
                    return ReturnableResponse::fromPDKException($e)->toResponse($response);
                }
            }
            if($relatedUserEntity === null){
                return ReturnableResponse::fromCredentialMismatchError('veriCode')->toResponse($response);
            }
            $relatedUID = $relatedUserEntity->getUID();
            $vericodeID = VeriCodeIDs::VERICODE_FORGET_PASSWORD()->getVeriCodeID();
        }else{
            $relatedUID = $REQ_UID;
            $vericodeID = VeriCodeIDs::VERICODE_CHANGE_PASSWORD()->getVeriCodeID();
        }

        $checkVericodeStatus = self::getCheckAnyVeriCodeResponse($REQ_VERICODE,$relatedUID,$vericodeID,$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
        if(!$checkVericodeStatus->succeed){
            return $checkVericodeStatus->returnableResponse->toResponse($response);
        }
        $VeriCodeEntity = $checkVericodeStatus->veriCode;
        if($relatedUserEntity === null){
            try{
                $relatedUserEntity = $UserEntityStorage->getUserEntityByUID($VeriCodeEntity->getUID());
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
            if($relatedUserEntity === null){
                return ReturnableResponse::fromInnerError('could not find user entity of a valid verification code')->toResponse($response);
            }
        }
        
        $relatedUserEntity->setPassword($REQ_NEW_PWD);
        try{
            $UserEntityStorage->updateUserEntity($relatedUserEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $resultResponse = new ReturnableResponse(200,0);
        return $resultResponse->toResponse($response);
    }
}
