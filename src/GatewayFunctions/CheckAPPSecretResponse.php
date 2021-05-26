<?php
namespace InteractivePlus\PDK2021\GatewayFunctions;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;

class CheckAPPSecretResponse{
    public bool $succeed = true;
    public ?ReturnableResponse $returnableResponse = null;
    public ?APPEntity $appEntity = null;
    public function __construct(bool $succeed, ?ReturnableResponse $returnableResponse, ?APPEntity $appEntity)
    {
        $this->succeed = $succeed;
        $this->returnableResponse = $returnableResponse;
        $this->appEntity = $appEntity;
    }
}