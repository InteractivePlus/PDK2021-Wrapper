<?php
namespace InteractivePlus\PDK2021\GatewayFunctions;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;

class CheckAPPTokenResponse{
    public bool $succeed = true;
    public ?ReturnableResponse $returnableResponse = null;
    public ?APPTokenEntity $tokenEntity = null;
    public function __construct(bool $succeed, ?ReturnableResponse $returnableResponse, ?APPTokenEntity $tokenEntity)
    {
        $this->succeed = $succeed;
        $this->returnableResponse = $returnableResponse;
        $this->tokenEntity = $tokenEntity;
    }
}