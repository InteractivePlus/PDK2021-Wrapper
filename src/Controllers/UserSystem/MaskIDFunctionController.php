<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\OutputUtils\MaskIDOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MaskIDFunctionController{
    public function listOwnedMaskIDs(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_CLIENT_ID = isset($args['client_id']) ? $args['client_id'] : null;
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        if($REQ_CLIENT_ID !== null){
            if(!APPFormat::isValidAPPID($REQ_CLIENT_ID)){
                return ReturnableResponse::fromIncorrectFormattedParam('client_id');
            }
        }

        $checkTokenResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($checkTokenResponse !== null){
            return $checkTokenResponse->toResponse($response);
        }

        //Get associated APP Entity
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $APPEntity = null;
        $requestedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID;
        try{
            $APPEntity = $APPEntityStorage->getAPPEntityByClientID($REQ_CLIENT_ID);
            if($APPEntity === null){
                $requestedAPPUID = APPSystemConstants::NO_APP_RELATED_APPUID;
            }else{
                $requestedAPPUID = $APPEntity->getAPPUID();
            }
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }

        $MaskIDStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();
        $searchedResult = $MaskIDStorage->searchMaskIDEntity(null,-1,-1,intval($REQ_UID),$requestedAPPUID,0,-1);
        $resultInfoArr = [];
        if($searchedResult->getNumResultsStored() > 0){
            $searchedArr = $searchedResult->getResultArray();
            foreach($searchedArr as $maskIDEntity){
                $resultInfoArr[] = MaskIDOutputUtil::getMaskIDAsAssocArray($maskIDEntity);
            }
        }
        $finalReturnable = new ReturnableResponse(200,0);
        $finalReturnable->returnDataLevelEntries['masks'] = $resultInfoArr;
        return $finalReturnable->toResponse($response);
    }
    public function createMaskID(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_CLIENT_ID = $REQ_PARAMS['client_id'];
        $REQ_SETTING_ARRAY = $REQ_PARAMS['settings'];
        
    }
}