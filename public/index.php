<?php

use InteractivePlus\PDK2021\Controllers\APPSystem\APPControlFunctionController;
use InteractivePlus\PDK2021\Controllers\Captcha\SimpleCaptchaController;
use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\Controllers\UserSystem\LoggedInFunctionController;
use InteractivePlus\PDK2021\Controllers\UserSystem\LoginController;
use InteractivePlus\PDK2021\Controllers\UserSystem\MaskIDFunctionController;
use InteractivePlus\PDK2021\Controllers\UserSystem\RegisterController;
use InteractivePlus\PDK2021\Controllers\VeriCode\VeriCodeController;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Constants\UserSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\PDKException;
use InteractivePlus\PDK2021Core\Base\Logger\ActionID;
use InteractivePlus\PDK2021Core\Base\Logger\LogEntity;
use InteractivePlus\PDK2021Core\Base\Logger\LoggerStorage;
use InteractivePlus\PDK2021Core\PDKCore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

require __DIR__ . '/../vendor/autoload.php';


//Initiate PDK Core
PDK2021Wrapper::initiatePDKCore();

//Initiate Slim APP
$app = AppFactory::create();

$app->addRoutingMiddleware();

$errMiddleWare = $app->addErrorMiddleware(PDK2021Wrapper::$config->DEVELOPMENT_MODE,true,true,PDK2021Wrapper::$pdkCore->getLogger());

$app->addMiddleware(new RKA\Middleware\IpAddress(PDK2021Wrapper::$config->SLIM_CHECK_PROXY,PDK2021Wrapper::$config->SLIM_PROXY_IPS,'ip'));

$customErrorHandler = function(
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($app) : Response {
    if($logger instanceof LoggerStorage && $exception instanceof PDKException && $logErrors){
        $REMOTE_ADDR = $request->getAttribute('ip');
        try{
            $logger->addLogItem(
                new LogEntity(
                    ActionID::PSRLog,
                    APPSystemConstants::INTERACTIVEPDK_APPUID,
                    UserSystemConstants::NO_USER_RELATED_UID,
                    time(),
                    LogLevel::ERROR,
                    false,
                    $exception->getCode(),
                    $REMOTE_ADDR,
                    $exception->getMessage(),
                    $exception->toReponseJSON()
                )
            );
        }catch(Throwable $e){

        }
    }else if($logErrors){
        try{
            $logger->error($exception->__toString());
        }catch(Throwable $e){

        }
    }
    $response = $app->getResponseFactory()->createResponse(500);
    $response = ReturnableResponse::fromThrowable($exception,$displayErrorDetails)->toResponse($response);
    return $response;
};

$errMiddleWare->setDefaultErrorHandler($customErrorHandler);

$app->get('/captcha',SimpleCaptchaController::class . ':getSimpleCaptcha');
$app->get('/captcha/{captcha_id}/submitResult',SimpleCaptchaController::class . ':getSimpleCaptchaSubmitResult');

$app->post('/user',RegisterController::class . ':register');

$app->post('/user/token',LoginController::class . ':login');
$app->get('/user/{uid}/token/{access_token}/checkTokenResult',LoginController::class . ':checkTokenValid');
$app->get('/user/{uid}/token/refreshResult',LoginController::class . ':refreshToken');
$app->delete('/user/{uid}/token/{access_token}',LoginController::class . ':logout');

$app->get('/vericodes/verifyEmailResult/{veriCode}',VeriCodeController::class . ':verifyEmail');
$app->get('/vericodes/verifyPhoneResult/{veriCode}',VeriCodeController::class . ':verifyPhone');
$app->post('/vericodes/sendAnotherVerifyEmailRequest',VeriCodeController::class . ':requestVerificationEmailResend');
$app->post('/vericodes/sendAnotherVerifyPhoneRequest',VeriCodeController::class . ':requestVerificationPhoneResend');

$app->post('/vericodes/changeEmailAddrRequest',VeriCodeController::class . ':requestChangeEmailAddressVeriCode');
$app->post('/vericodes/changePhoneNumberRequest',VeriCodeController::class . ':requestChangePhoneNumVeriCode');
$app->patch('/user/email',LoggedInFunctionController::class . ':changeEmailAddress');
$app->patch('/user/phoneNum',LoggedInFunctionController::class . ':changePhoneNumber');

$app->post('/vericodes/changePasswordRequest',VeriCodeController::class . ':requestChangePassword');
$app->patch('/user/password',VeriCodeController::class . ':changePassword');

$app->patch('/user/userInfo',LoggedInFunctionController::class . ':changeUserInfo');

$app->post('/apps/{display_name}',APPControlFunctionController::class . ':createNewAPP');
$app->get('/user/{uid}/apps',APPControlFunctionController::class . ':listOwnedAPPs');
$app->post('/vericodes/deleteAPPRequest',VeriCodeController::class . ':requestDeleteAPPVeriCode');
$app->delete('/apps/{appuid}',APPControlFunctionController::class . ':deleteOwnedAPP');
$app->post('/vericodes/appImportantInformationRequest',VeriCodeController::class . ':requestAPPImportantActionVeriCode');
$app->patch('/apps/{appuid}',APPControlFunctionController::class . ':changeAPPInfo');

$app->get('/masks/{client_id}',MaskIDFunctionController::class . ':listOwnedMaskIDs');

$app->run();