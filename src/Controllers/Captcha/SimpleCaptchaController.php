<?php
namespace InteractivePlus\PDK2021\Controllers\Captcha;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Captcha\Format\CaptchaFormat;
use InteractivePlus\PDK2021Core\Captcha\Implemention\PDKSimpleCaptchaSystemImpl;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SimpleCaptchaController{
    public static function useAndCheckCaptchaResult($captchaID) : ?ReturnableResponse{
        if(empty($captchaID) || !is_string($captchaID) || !CaptchaFormat::isValidCaptchaID($captchaID)){
            return ReturnableResponse::fromIncorrectFormattedParam('captcha_id');
        }
        $captchaSystem = PDK2021Wrapper::$pdkCore->getCaptchaSystem();

        $checkResult = false;
        try{
            $checkResult = $captchaSystem->checkAndUseCpatcha($captchaID);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e);
        }
        if(!$checkResult){
            return ReturnableResponse::fromCredentialMismatchError('captcha_id');
        }
        return null;
    }

    public function getSimpleCaptcha(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $params = $request->getQueryParams();
        $captchaSystem = PDK2021Wrapper::$pdkCore->getCaptchaSystem();
        $generatedCaptcha = null;
        try{
            $generatedCaptcha = $captchaSystem->generateAndSaveCaptchaToStorage($params);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $successResponse = new ReturnableResponse(201,0);
        $successResponse->returnDataLevelEntries = [
            'captcha_id' => $generatedCaptcha->getCaptchaID(),
            'captcha_data' => $generatedCaptcha->getClientData(),
            'expire_time' => $generatedCaptcha->getExpireUTCTime()
        ];
        return $successResponse->toResponse($response);
    }
    public function getSimpleCaptchaSubmitResult(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $params = $request->getQueryParams();
        $REQ_CAPTCHA_ID = $args['captcha_id'];
        $REQ_CAPTCHA_PHRASE = $params['phrase'];
        $captchaSystem = PDK2021Wrapper::$pdkCore->getCaptchaSystem();
        $verifyResult = false;

        if(empty($REQ_CAPTCHA_ID) || !is_string($REQ_CAPTCHA_ID) || !CaptchaFormat::isValidCaptchaID($REQ_CAPTCHA_ID)){
            return ReturnableResponse::fromIncorrectFormattedParam('captcha_id')->toResponse($response);
        }
        if(empty($REQ_CAPTCHA_PHRASE) || !is_string($REQ_CAPTCHA_PHRASE) || ($captchaSystem instanceof PDKSimpleCaptchaSystemImpl && $captchaSystem->getStorage()->getPhraseLen() !== strlen($REQ_CAPTCHA_PHRASE))){
            return ReturnableResponse::fromIncorrectFormattedParam('phrase')->toResponse($response);
        }

        try{
            $verifyResult = $captchaSystem->trySubmitCaptchaPhrase($REQ_CAPTCHA_ID,$REQ_CAPTCHA_PHRASE);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }

        if(!$verifyResult){
            return ReturnableResponse::fromCredentialMismatchError('phrase')->toResponse($response);
        }
        $successResult = new ReturnableResponse(200,0);
        return $successResult->toResponse($response);
    }
}