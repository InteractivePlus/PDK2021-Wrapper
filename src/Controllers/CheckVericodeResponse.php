<?php
namespace InteractivePlus\PDK2021\Controllers;

use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeEntity;

class CheckVericodeResponse{
    public bool $succeed = true;
    public ?ReturnableResponse $returnableResponse = null;
    public ?VeriCodeEntity $veriCode = null;
    public function __construct(bool $succeed, ?ReturnableResponse $returnableResponse, ?VeriCodeEntity $veriCodeEntity)
    {
        $this->succeed = $succeed;
        $this->returnableResponse = $returnableResponse;
        $this->veriCode = $veriCodeEntity;
    }
}