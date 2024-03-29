<?php

use InteractivePlus\PDK2021\Controllers\APPSystem\APPControlFunctionController;
use InteractivePlus\PDK2021\Controllers\Captcha\SimpleCaptchaController;
use InteractivePlus\PDK2021\Controllers\OAuthSystem\AccessCodeController;
use InteractivePlus\PDK2021\Controllers\OAuthSystem\APPAbilityController;
use InteractivePlus\PDK2021\Controllers\OAuthSystem\AuthCodeController;
use InteractivePlus\PDK2021\Controllers\OAuthSystem\EXT_StorageAbilityController;
use InteractivePlus\PDK2021\Controllers\OAuthSystem\EXT_TicketAbilityController;
use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\Controllers\UserSystem\LoggedInFunctionController;
use InteractivePlus\PDK2021\Controllers\UserSystem\LoginController;
use InteractivePlus\PDK2021\Controllers\UserSystem\MaskIDFunctionController;
use InteractivePlus\PDK2021\Controllers\UserSystem\RegisterController;
use InteractivePlus\PDK2021\Controllers\VeriCode\VeriCodeController;
use InteractivePlus\PDK2021\Middleware\PDKCORSMiddleware;
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
$app->get('/masks',MaskIDFunctionController::class . ':listOwnedMaskIDs');
$app->post('/masks/{client_id}',MaskIDFunctionController::class . ':createMaskID');
$app->patch('/masks/{mask_id}',MaskIDFunctionController::class . ':modifyMaskID');
$app->delete('/masks/{mask_id}',MaskIDFunctionController::class . ':deleteMaskID');

$app->get('/authcode',AuthCodeController::class . ':getAuthCode');

$app->post('/oauth_token',AccessCodeController::class . ':createAccessCode');
$app->get('/oauth_token/verified_status',AccessCodeController::class . ':verifyAccessCode');
$app->get('/oauth_token/refresh_result', AccessCodeController::class . ':refreshAPPToken');

$app->get('/oauth_ability/user_info',APPAbilityController::class . ':getBasicInfo');
$app->post('/oauth_ability/notifications',APPAbilityController::class . ':sendNotification');

if(PDK2021Wrapper::$pdkCore->getEXTOAuthStorageRecordStorage() !== null){
    $app->get('/oauth_ability/storage/is_record',EXT_StorageAbilityController::class . ':isDataPresent');
    $app->get('/oauth_ability/storage/data',EXT_StorageAbilityController::class . ':getData');
    $app->put('/oauth_ability/storage/data',EXT_StorageAbilityController::class . ':putData');
}

if(PDK2021Wrapper::$pdkCore->getEXTOAuthTicketRecordStorage() !== null){
    $app->post('/tickets',EXT_TicketAbilityController::class . ':createTicket');
    $app->get('/tickets',EXT_TicketAbilityController::class . ':listOwnedTickets');
    $app->post('/tickets/{ticket_id}/responses',EXT_TicketAbilityController::class . ':respondToTicket');
    $app->get('/tickets/{ticket_id}',EXT_TicketAbilityController::class . ':getTicketInfo');
    $app->get('/apps/{client_id}/tickets/count',EXT_TicketAbilityController::class . ':getAPPOwnedTicketCounts');
    $app->get('/apps/{client_id}/tickets',EXT_TicketAbilityController::class . ':getAPPOwnedTickets');
    $app->patch('/tickets/{ticket_id}',EXT_TicketAbilityController::class . ':changeTicketStatus');
}

(new PDKCORSMiddleware())->addThisMiddleware($app);

$app->run();