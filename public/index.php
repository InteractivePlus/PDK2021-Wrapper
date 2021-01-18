<?php

use InteractivePlus\PDK2021\Controllers\UserSystem\RegisterController;
use InteractivePlus\PDK2021\Controllers\VeriCode\VeriCodeController;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\PDKCore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
require __DIR__ . '/../vendor/autoload.php';


//Initiate PDK Core
PDK2021Wrapper::initiatePDKCore();

//Initiate Slim APP
$app = AppFactory::create();

$app->addRoutingMiddleware();

$errMiddleWare = $app->addErrorMiddleware(true,true,true,PDK2021Wrapper::$pdkCore->getLogger());

$app->addMiddleware(new RKA\Middleware\IpAddress(PDK2021Wrapper::$config->SLIM_CHECK_PROXY,PDK2021Wrapper::$config->SLIM_PROXY_IPS,'ip'));


$app->post('/user',RegisterController::class . ':register');
$app->get('/vericodes/verifyEmailResult/{veriCode}',VeriCodeController::class . ':verifyEmail');

$app->run();