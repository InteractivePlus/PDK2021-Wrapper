<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
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
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RegisterController{
    public function register(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $requestParams = json_decode($request->getBody(),true);
        $REQ_USERNAME = $requestParams['username'];
        $REQ_PASSWORD = $requestParams['password'];
        $REQ_EMAIL = $requestParams['email'];
        $REQ_PHONE = $requestParams['phone'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        //first check if every parameter complies to rules
        if(empty($REQ_USERNAME)){
            return ReturnableResponse::fromIncorrectFormattedParam('username')->toResponse($response);
        }else if(empty($REQ_PASSWORD)){
            return ReturnableResponse::fromIncorrectFormattedParam('password')->toResponse($response);
        }else if(empty($REQ_EMAIL) && empty($REQ_PHONE)){
            return ReturnableResponse::fromIncorrectFormattedParam('email|phone')->toResponse($response);
        }
        $formatSetting = PDK2021Wrapper::$pdkCore->getUserEntityStorage()->getFormatSetting();
        if(!$formatSetting->checkUserName($REQ_USERNAME)){
            return ReturnableResponse::fromIncorrectFormattedParam('username')->toResponse($response);
        }else if(!$formatSetting->checkPassword($REQ_PASSWORD)){
            return ReturnableResponse::fromIncorrectFormattedParam('password')->toResponse($response);
        }else if(!empty($REQ_EMAIL) && !$formatSetting->checkEmailAddr($REQ_EMAIL)){
            return ReturnableResponse::fromIncorrectFormattedParam('email')->toResponse($response);
        }
        $usrPhone = null;
        if(!empty($REQ_PHONE)){
            try{
                $usrPhone = UserPhoneUtil::parsePhone($REQ_PHONE);
                if(!UserPhoneUtil::verifyPhoneNumberObj($usrPhone)){
                    return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
                }
            }catch(PDKInnerArgumentError $e){
                return ReturnableResponse::fromIncorrectFormattedParam('phone')->toResponse($response);
            }
        }

        $entityStorage = PDK2021Wrapper::$pdkCore->getUserEntityStorage();

        //check if any username, email, phone has conflict
        if($entityStorage->checkUsernameExist($REQ_USERNAME) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('username')->toResponse($response);
        }
        if(!empty($REQ_EMAIL) && $entityStorage->checkEmailExist($REQ_EMAIL) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('email')->toResponse($response);
        }
        if($usrPhone !== null && $entityStorage->checkPhoneNumExist($usrPhone) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('phone')->toResponse($response);
        }
        $newUsrEntity = UserEntity::create(
            $REQ_USERNAME,
            null,
            null,
            $REQ_PASSWORD,
            $REQ_EMAIL,
            $usrPhone,
            false,
            false,
            $ctime,
            $REMOTE_ADDR,
            false,
            $formatSetting
        );
        $updatedUsrEntity = null;
        try{
            $updatedUsrEntity = $entityStorage->addUserEntity($newUsrEntity);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($updatedUsrEntity === null){
            return ReturnableResponse::fromInnerError('something happened when trying to put your user entity into the database')->toResponse($response);
        }

        $veriCodeStorage = PDK2021Wrapper::$pdkCore->getVeriCodeStorage();
        //Let's send out verification Email and SMS
        if(!empty($updatedUsrEntity->getEmail())){
            $optionalPDKException = PDK2021Wrapper::$pdkCore->createAndSendVerificationEmail(
                $updatedUsrEntity->getEmail(),
                $updatedUsrEntity,
                $ctime,
                PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
                $REMOTE_ADDR
            );
            if($optionalPDKException !== null){
                return ReturnableResponse::fromPDKException($optionalPDKException)->toResponse($response);
            }
        }
        $phoneVerificationMethodReceiver = SentMethod::NOT_SENT;
        if($updatedUsrEntity->getPhoneNumber() !== null){
            $optionalPDKException = PDK2021Wrapper::$pdkCore->createAndSendVerificationPhone(
                $updatedUsrEntity->getPhoneNumber(),
                $updatedUsrEntity,
                $ctime,
                PDK2021Wrapper::$config->VERICODE_AVAILABLE_DURATION,
                $phoneVerificationMethodReceiver,
                $REMOTE_ADDR,
                true
            );
            if($optionalPDKException !== null){
                return ReturnableResponse::fromPDKException($optionalPDKException)->toResponse($response);
            }
        }
        $returnable = new ReturnableResponse(201,0);
        $returnable->returnDataLevelEntries = array(
            'uid' => $updatedUsrEntity->getUID(),
            'username' => $updatedUsrEntity->getUsername(),
            'email' => $updatedUsrEntity->getEmail(),
            'phone' => ($updatedUsrEntity->getPhoneNumber() === null) ? null : UserPhoneUtil::outputPhoneNumberE164($updatedUsrEntity->getPhoneNumber()),
            'phoneVerificationSentMethod' => $phoneVerificationMethodReceiver
        );
        return $returnable->toResponse($response);
    }
}