<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RegisterController{
    private ContainerInterface $container;
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    public function register(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        return $response;
        //TODO: Finish this function
    }
}