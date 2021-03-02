<?php
namespace InteractivePlus\PDK2021\Controllers\APPSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\OutputUtils\APPOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\APP\APPInfo\PDKAPPType;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\Base\Constants\APPSystemConstants;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKStorageEngineError;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeFormat;
use InteractivePlus\PDK2021Core\Communication\VerificationCode\VeriCodeIDs;
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
    public function deleteOwnedAPP(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_APPUID = $args['appuid'];
        $REQ_VERICODE = $REQ_PARAMS['veriCode'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();
        if(empty($REQ_APPUID) || intval($REQ_APPUID) < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('appuid')->toResponse($response);
        }else{
            $REQ_APPUID = intval($REQ_APPUID);
        }
        $checkTokenResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        $REQ_UID = intval($REQ_UID);
        if($checkTokenResponse !== null){
            return $checkTokenResponse->toResponse($response);
        }

        
        if(empty($REQ_VERICODE) || !is_string($REQ_VERICODE) || (!VeriCodeFormat::isValidVerificationCode($REQ_VERICODE) && !VeriCodeFormat::isValidPartialPhoneVerificationCode($REQ_VERICODE))){
            return ReturnableResponse::fromIncorrectFormattedParam('veriCode')->toResponse($response);
        }
        $checkVeriCodeResponse = CommonFunction::getCheckAnyVeriCodeResponse($REQ_VERICODE,$REQ_UID,VeriCodeIDs::VERICODE_THIRD_APP_DELETE_ACTION()->getVeriCodeID(),$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
        if(!$checkVeriCodeResponse->succeed){
            return $checkVeriCodeResponse->returnableResponse->toResponse($response);
        }
        //check if APPUID is owned by the user
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $APPEntity = null;
        try{
            $APPEntity = $APPEntityStorage->getAPPEntityByAPPUID($REQ_APPUID);
            if($APPEntity === null){
                return ReturnableResponse::fromPermissionDeniedError('You don\'t own this APP!')->toResponse($response);
            }
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($APPEntity->ownerUID !== $REQ_UID){
            return ReturnableResponse::fromPermissionDeniedError('You don\'t own this APP!')->toResponse($response);
        }
        try{
            $APPEntityStorage->deleteAPPEntity($APPEntity->getAPPUID());
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        $deleteRst = new ReturnableResponse(204,0);
        return $deleteRst->toResponse($response);
    }
    public function changeAPPInfo(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_APPUID = $args['appuid'];
        $REQ_VERICODE = $REQ_PARAMS['veriCode'];
        $REMOTE_ADDR = $request->getAttribute('ip');

        $REQ_PATCH_PARAMS = json_decode($request->getBody(),true);
        $REQ_PATCH_DISPLAY_NAME = $REQ_PATCH_PARAMS['display_name'];
        $REQ_PATCH_CLIENT_SECRET = $REQ_PATCH_PARAMS['client_secret'];
        $REQ_PATCH_CLIENT_TYPE = $REQ_PATCH_PARAMS['client_type'];
        $REQ_PATCH_REDIRECT_URI = $REQ_PATCH_PARAMS['redirectURI'];


        $ctime = time();
        if(empty($REQ_APPUID) || intval($REQ_APPUID) < 0){
            return ReturnableResponse::fromIncorrectFormattedParam('appuid')->toResponse($response);
        }else{
            $REQ_APPUID = intval($REQ_APPUID);
        }
        $checkTokenResponse = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        $REQ_UID = intval($REQ_UID);
        if($checkTokenResponse !== null){
            return $checkTokenResponse->toResponse($response);
        }

        
        if(empty($REQ_VERICODE) || !is_string($REQ_VERICODE) || (!VeriCodeFormat::isValidVerificationCode($REQ_VERICODE) && !VeriCodeFormat::isValidPartialPhoneVerificationCode($REQ_VERICODE))){
            return ReturnableResponse::fromIncorrectFormattedParam('veriCode')->toResponse($response);
        }
        $checkVeriCodeResponse = CommonFunction::getCheckAnyVeriCodeResponse($REQ_VERICODE,$REQ_UID,VeriCodeIDs::VERICODE_THIRD_APP_IMPORTANT_ACTION()->getVeriCodeID(),$ctime,APPSystemConstants::INTERACTIVEPDK_APPUID);
        if(!$checkVeriCodeResponse->succeed){
            return $checkVeriCodeResponse->returnableResponse->toResponse($response);
        }
        //check if APPUID is owned by the user
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $APPEntity = null;
        try{
            $APPEntity = $APPEntityStorage->getAPPEntityByAPPUID($REQ_APPUID);
            if($APPEntity === null){
                return ReturnableResponse::fromPermissionDeniedError('You don\'t own this APP!')->toResponse($response);
            }
        }catch(PDKStorageEngineError $e){
            return ReturnableResponse::fromPDKException($e)->toResponse($response);
        }
        if($APPEntity->ownerUID !== $REQ_UID){
            return ReturnableResponse::fromPermissionDeniedError('You don\'t own this APP!')->toResponse($response);
        }
        
        /*
        $REQ_PATCH_DISPLAY_NAME = $REQ_PATCH_PARAMS['display_name'];
        $REQ_PATCH_CLIENT_SECRET = $REQ_PATCH_PARAMS['client_secret'];
        $REQ_PATCH_CLIENT_TYPE = $REQ_PATCH_PARAMS['client_type'];
        $REQ_PATCH_REDIRECT_URI = $REQ_PATCH_PARAMS['redirectURI'];
        */
        $appEntityChanged = false;
        $appFormatSetting = $APPEntityStorage->getFormatSetting();
        if(!empty($REQ_PATCH_DISPLAY_NAME)){
            if(!is_string($REQ_PATCH_DISPLAY_NAME) || !$appFormatSetting->checkAPPDisplayName($REQ_PATCH_DISPLAY_NAME)){
                return ReturnableResponse::fromIncorrectFormattedParam('display_name')->toResponse($response);
            }
            if($REQ_PATCH_DISPLAY_NAME !== $APPEntity->getDisplayName()){
                $appEntityChanged = true;
                $APPEntity->setDisplayName($REQ_PATCH_DISPLAY_NAME);
            }
        }
        if($REQ_PATCH_CLIENT_SECRET === 'reroll'){
            $appEntityChanged = true;
            $APPEntity->doClientSecretReroll();
        }
        if($REQ_PATCH_CLIENT_TYPE !== null){
            $REQ_PATCH_CLIENT_TYPE = intval($REQ_PATCH_CLIENT_TYPE);
            if(!PDKAPPType::isValidAppType($REQ_PATCH_CLIENT_TYPE)){
                return ReturnableResponse::fromIncorrectFormattedParam('client_type')->toResponse($response);
            }
            $appEntityChanged = true;
            $APPEntity->setClientType($REQ_PATCH_CLIENT_TYPE);
        }
        if(isset($REQ_PATCH_PARAMS['redirectURI'])){
            $appEntityChanged = true;
            if(empty($REQ_PATCH_REDIRECT_URI)){
                $APPEntity->redirectURI = null;
            }else{
                $APPEntity->redirectURI = $REQ_PATCH_REDIRECT_URI;
            }
        }
        if($appEntityChanged){
            try{
                $APPEntityStorage->updateAPPEntity($APPEntity);
            }catch(PDKStorageEngineError $e){
                return ReturnableResponse::fromPDKException($e)->toResponse($response);
            }
        }

        $finishRst = new ReturnableResponse(200,0);
        $finishRst->returnDataLevelEntries['app'] = APPOutputUtil::getAPPEntityAsAssocArray($APPEntity);
        return $finishRst->toResponse($response);
    }
}