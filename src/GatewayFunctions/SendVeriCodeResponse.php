<?php
namespace InteractivePlus\PDK2021\GatewayFunctions;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SentMethod;

class SendVeriCodeResponse{
    public bool $succeed = true;
    public ?ReturnableResponse $returnableResponse = null;
    public int $sentMethod = SentMethod::NOT_SENT;
    public function __construct(bool $succeed, ?ReturnableResponse $returnableResponse, int $sentMethod = SentMethod::NOT_SENT)
    {
        $this->succeed = $succeed;
        $this->returnableResponse = $returnableResponse;
        $this->sentMethod = $sentMethod;
    }
}