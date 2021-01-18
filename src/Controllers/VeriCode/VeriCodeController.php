<?php
namespace InteractivePlus\PDK2021\Controllers\VeriCode;

use InteractivePlus\PDK2021\Controllers\CheckVericodeResponse;
use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\Controllers\UserSystem\LoginController;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKInnerArgumentError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemAlreadyExistError;
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
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

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
            true
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
        $veriCode = null;
        if(($REQ_PREF_SEND_METHOD === SentMethod::EMAIL && !empty($UserEntity->getEmail()) && $UserEntity->isEmailVerified()) || ($REQ_PREF_SEND_METHOD !== SentMethod::EMAIL && ($UserEntity->getPhoneNumber() === null || !$UserEntity->isPhoneVerified()))){
            $actualSendingMethod = SentMethod::EMAIL;
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
            try{
                PDK2021Wrapper::$pdkCore->getVeriCodeEmailSender()->sendVeriCode($veriCode,$UserEntity,$UserEntity->getEmail());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else if($UserEntity->getPhoneNumber() !== null && $UserEntity->isPhoneVerified()){
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
            $sender = PDK2021Wrapper::$pdkCore->getPhoneSender($actualSendingMethod,$REQ_PREF_SEND_METHOD !== SentMethod::PHONE_CALL);
            try{
                $sender->sendVeriCode($veriCode,$UserEntity,$UserEntity->getPhoneNumber());
            }catch(PDKSenderServiceError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }else{
            return ReturnableResponse::fromItemNotFound('communication_method')->toResponse($response);
        }
        if($veriCode === null){
            return ReturnableResponse::fromInnerError('allocated vericode disappeared')->toResponse($response);
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
        return $returnResult->toResponse($response);
    }
    
    
}
