<?php
namespace InteractivePlus\PDK2021\Controllers\VeriCode;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Base\Exception\PDKErrCode;
use InteractivePlus\PDK2021Core\Base\Exception\PDKException;
use InteractivePlus\PDK2021Core\Base\Logger\ActionID;
use InteractivePlus\PDK2021Core\Base\Logger\LogEntity;
use InteractivePlus\PDK2021Core\Base\Logger\PDKLogLevel;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
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
            return ReturnableResponse::fromItemNotFound('veriCode')->toResponse($response);
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
}
