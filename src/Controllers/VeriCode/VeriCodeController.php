<?php
namespace InteractivePlus\PDK2021\Controllers\VeriCode;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKInnerArgumentError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemAlreadyExistError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Exception\PDKErrCode;
use InteractivePlus\PDK2021Core\Base\Exception\PDKException;
use InteractivePlus\PDK2021Core\Base\Logger\ActionID;
use InteractivePlus\PDK2021Core\Base\Logger\LogEntity;
use InteractivePlus\PDK2021Core\Base\Logger\PDKLogLevel;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeID;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeIDs;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class VeriCodeController{
    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_VERICODE = $args['veriCode'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        //first check if this vericode is a valid one
        if(!VeriCodeFormat::isValidVerificationCode($REQ_VERICODE)){
            return ReturnableResponse::fromIncorrectFormattedParam('veriCode')->toResponse($response);
        }
        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        $veriCodeEntity = $veriCodeStorage->getVeriCodeEntity($REQ_VERICODE);
        if($veriCodeEntity === null){
            return ReturnableResponse::fromItemExpiredOrUsedError('veriCode')->toResponse($response);
        }
        if($veriCodeEntity->getVeriCodeID()->getVeriCodeID() !== VeriCodeIDs::VERICODE_VERIFY_EMAIL()->getVeriCodeID()){
            return ReturnableResponse::fromItemExpiredOrUsedError('veriCode')->toResponse($response);
        }
        if(!$veriCodeEntity->canUse($ctime)){
            return ReturnableResponse::fromItemExpiredOrUsedError('veriCode')->toResponse($response);
        }

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
        if(!VeriCodeFormat::isValidPartialPhoneVerificationCode($REQ_VERICODE)){
            return ReturnableResponse::fromIncorrectFormattedParam('veriCode')->toResponse($response);
        }
        if(empty($REQ_UID) || $REQ_UID < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('uid')->toResponse($response);
        }

        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        
        $searchedResults = $veriCodeStorage->searchPhoneVeriCode($ctime + 1,0,$REQ_UID,APPSystemConstants::INTERACTIVEPDK_APPUID,$REQ_VERICODE);
        if($searchedResults->getNumResultsStored() < 1){
            return ReturnableResponse::fromItemExpiredOrUsedError('veriCode')->toResponse($response);
        }
        $veriCodeEntity = null;
        $searchedResultsArr = $searchedResults->getResultArray();
        foreach($searchedResultsArr as $singleResult){
            if($singleResult->getVeriCodeID()->getVeriCodeID() === VeriCodeIDs::VERICODE_VERIFY_PHONE()->getVeriCodeID() && $singleResult->canUse($ctime)){
                $veriCodeEntity = $singleResult;
            }
        }
        
        if($veriCodeEntity === null){
            return ReturnableResponse::fromItemExpiredOrUsedError('veriCode')->toResponse($response);
        }

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

}
