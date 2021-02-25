<?php
namespace InteractivePlus\PDK2021\Controllers\APPSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\OutputUtils\APPOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\APP\APPInfo\PDKAPPType;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class APPControlFunctionController{
    public function createNewAPP(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_DISPLAYNAME = $args['display_name'];
        $REQ_CLIENT_TYPE = $REQ_PARAMS['client_type'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        if(!empty($REQ_CLIENT_TYPE)){
            $REQ_CLIENT_TYPE = intval($REQ_CLIENT_TYPE);
        }else{
            return ReturnableResponse::fromIncorrectFormattedParam('client_type')->toResponse($response);
        }
        if(!PDKAPPType::isValidAppType($REQ_CLIENT_TYPE)){
            return ReturnableResponse::fromIncorrectFormattedParam('client_type')->toResponse($response);
        }
        
        
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $APPFormat = $APPEntityStorage->getFormatSetting();

        if(empty($REQ_DISPLAYNAME) || !is_string($REQ_DISPLAYNAME) || !$APPFormat->checkAPPDisplayName($REQ_DISPLAYNAME)){
            return ReturnableResponse::fromIncorrectFormattedParam('display_name')->toResponse($response);
        }

        $checkTokenRes = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($checkTokenRes !== null){
            return $checkTokenRes->toResponse($response);
        }else{
            $REQ_UID = (int) $REQ_UID;
        }

        if($APPEntityStorage->checkDisplayNameExist($REQ_DISPLAYNAME) !== -1){
            return ReturnableResponse::fromItemAlreadyExist('display_name')->toResponse($response);
        }
        $appEntity = APPEntity::create(
            $REQ_DISPLAYNAME,
            $REQ_CLIENT_TYPE,
            '',
            $ctime,
            $REQ_UID,
            $APPFormat
        );

        try{
            $appEntity = $APPEntityStorage->addAPPEntity($appEntity,true);
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($appEntity == null){
            return ReturnableResponse::fromItemAlreadyExist('display_name')->toResponse($response);
        }
        $returnResponse = new ReturnableResponse(201,0);
        $returnResponse->returnDataLevelEntries['app'] = APPOutputUtil::getAPPEntityAsAssocArray($appEntity);
        return $returnResponse->toResponse($response);
    }
    public function listOwnedAPPs(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_UID = $args['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();

        $checkLoginCredentialResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($checkLoginCredentialResponse !== null){
            return $checkLoginCredentialResponse->toResponse($response);
        }
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $allSearchedResults = $APPEntityStorage->searchAPPEntity(null,-1,-1,intval($REQ_UID),0,-1);
        $result = new ReturnableResponse(200,0);
        $result->returnDataLevelEntries['apps'] = [];
        $searchResultArr = $allSearchedResults->getResultArray();
        if(!empty($searchResultArr)){
            foreach($searchResultArr as $singleApp){
                $result->returnDataLevelEntries['apps'][] = APPOutputUtil::getAPPEntityAsAssocArray($singleApp);
            }
        }
        return $result->toResponse($response);
    }
}