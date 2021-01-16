<?php

use InteractivePlus\PDK2021Core\PDKCore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
require __DIR__ . '/../vendor/autoload.php';

//Initiate Slim APP
$app = AppFactory::create();

//Initiate PDK Core
