<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\OAuth\OAuthScopes;
use InteractivePlus\PDK2021\OutputUtils\MaskIDOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class APPAbilityController{
    public function getBasicInfo(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ScopeRequired = OAuthScopes::SCOPE_BASIC_INFO()->getScopeName();

        $VerifyScopeAndTokenResult = CommonFunction::checkAPPTokenValidAndScopeSatisfiedResponse($REQ_ACCESS_TOKEN,$ScopeRequired,$ctime,null,null,null);
        if(!$VerifyScopeAndTokenResult->succeed){
            return $VerifyScopeAndTokenResult->returnableResponse->toResponse($response);
        }

        $APPTokenEntity = $VerifyScopeAndTokenResult->tokenEntity;
        $MaskIDEntityStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();
        $MaskIDEntity = $MaskIDEntityStorage->getMaskIDEntityByMaskID($APPTokenEntity->getMaskID());
        if($MaskIDEntity === null){
            return ReturnableResponse::fromInnerError('Cannot find mask_id of a existing and verified access_token')->toResponse($response);
        }
        
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['info'] = MaskIDOutputUtil::getMaskIDAsOAuthInfoAssocArray($MaskIDEntity);
        return $returnResponse->toResponse($response);
    }
}