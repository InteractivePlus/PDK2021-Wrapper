<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\Controllers\VeriCode\VeriCodeController;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKSenderServiceError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeID;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeIDs;
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
            $checkAccessTokenResponse = LoginController::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
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
}