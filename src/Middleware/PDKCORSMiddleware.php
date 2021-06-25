<?php
namespace InteractivePlus\PDK2021\Middleware;

use InteractivePlus\PDK2021\PDK2021Wrapper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;

class PDKCORSMiddleware implements MiddlewareInterface{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface{
        $response = $handler->handle($request);
        if(PDK2021Wrapper::$config->DEVELOPMENT_MODE){
            $response = $response->withHeader('Access-Control-Allow-Origin','*');
        }else{
            foreach(PDK2021Wrapper::$config->FRONTEND_ROOT_URL_FOR_CORS as $frontendURL){
                $response = $response->withHeader('Access-Control-Allow-Origin',$frontendURL);
            }
        }
        $response = $response
            ->withHeader('Access-Control-Allow-Headers','X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods','GET, POST, PUT, DELETE, PATCH, OPTIONS');
        return $response;
    }
    public function addThisMiddleware(App &$app){
        $app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });
        $app->add($this);
        $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
            throw new HttpNotFoundException($request);
        });
    }
}