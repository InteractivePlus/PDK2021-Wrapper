<?php
namespace InteractivePlus\PDK2021\Controllers\UserSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\InputUtils\UserSettingInputUtil;
use InteractivePlus\PDK2021\OutputUtils\MaskIDOutputUtil;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\Formats\APPFormat;
use InteractivePlus\PDK2021Core\APP\Formats\MaskIDFormat;
use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;
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
        if(!empty($REQ_CLIENT_ID)){
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
        $REQ_CLIENT_ID = isset($args['client_id']) ? $args['client_id'] : null;
        $REQ_DISPLAY_NAME = $REQ_PARAMS['display_name'];
        $REQ_SETTING_ARRAY = $REQ_PARAMS['settings'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        $userLoginStatusCheck = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($userLoginStatusCheck !== null){
            return $userLoginStatusCheck->toResponse($response);
        }

        $REQ_UID = intval($REQ_UID);

        if(!APPFormat::isValidAPPID($REQ_CLIENT_ID)){
            return ReturnableResponse::fromIncorrectFormattedParam('client_id')->toResponse($response);
        }

        if(!empty($REQ_SETTING_ARRAY) && !is_array($REQ_SETTING_ARRAY)){
            return ReturnableResponse::fromIncorrectFormattedParam('settings')->toResponse($response);
        }else if(empty($REQ_SETTING_ARRAY)){
            $REQ_SETTING_ARRAY = array();
        }

        if(!empty($REQ_DISPLAY_NAME) && !MaskIDFormat::isValidMaskIDDisplayName($REQ_DISPLAY_NAME)){
            return ReturnableResponse::fromIncorrectFormattedParam('display_name');
        }else if(empty($REQ_DISPLAY_NAME)){
            $REQ_DISPLAY_NAME = null;
        }

        $parsedSetting = UserSettingInputUtil::parseSettingArray($REQ_SETTING_ARRAY);

        //Check and fetch ClientID
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        if($APPEntityStorage->checkClientIDExist($REQ_CLIENT_ID) === APPSystemConstants::NO_APP_RELATED_APPUID){
            return ReturnableResponse::fromItemNotFound('client_id')->toResponse($response);
        }

        $APPEntity = $APPEntityStorage->getAPPEntityByClientID($REQ_CLIENT_ID);
        
        $MaskIDEntityStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();

        $CreatedMaskID = new MaskIDEntity(
            MaskIDFormat::generateMaskID(),
            $APPEntity->getAPPUID(),
            $REQ_UID,
            $REQ_DISPLAY_NAME,
            $ctime,
            $parsedSetting
        );

        $returnedMaskID = $MaskIDEntityStorage->addMaskIDEntity($CreatedMaskID,true);
        if($returnedMaskID === null){
            return ReturnableResponse::fromInnerError('Unknown error occured when trying to put maskid into database');
        }
        
        //We successfully put MaskID into database, let's return MaskID
        $outputMaskIDArray = MaskIDOutputUtil::getMaskIDAsAssocArray($returnedMaskID);
        $returnResponse = new ReturnableResponse(201,0);
        $returnResponse->returnDataLevelEntries['mask'] = $outputMaskIDArray;
        return $returnResponse->toResponse($response);
    }
    public function modifyMaskID(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_MASK_ID = $args['mask_id'];
        $REQ_DISPLAY_NAME = $REQ_PARAMS['display_name'];
        $REQ_SETTING_ARRAY = $REQ_PARAMS['settings'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

        $userLoginStatusCheck = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($userLoginStatusCheck !== null){
            return $userLoginStatusCheck->toResponse($response);
        }

        if((empty($REQ_SETTING_ARRAY) && $REQ_DISPLAY_NAME === null)){
            return ReturnableResponse::fromIncorrectFormattedParam('display_name|settings')->toResponse($response);
        }

        $REQ_UID = intval($REQ_UID);

        if(!MaskIDFormat::isValidMaskID($REQ_MASK_ID)){
            return ReturnableResponse::fromIncorrectFormattedParam('mask_id')->toResponse($response);
        }

        if(!empty($REQ_SETTING_ARRAY) && !is_array($REQ_SETTING_ARRAY)){
            return ReturnableResponse::fromIncorrectFormattedParam('settings')->toResponse($response);
        }else if(empty($REQ_SETTING_ARRAY)){
            $REQ_SETTING_ARRAY = array();
        }

        if(!empty($REQ_DISPLAY_NAME) && !MaskIDFormat::isValidMaskIDDisplayName($REQ_DISPLAY_NAME)){
            return ReturnableResponse::fromIncorrectFormattedParam('display_name')->toResponse($response);
        }
        
        $MaskIDEntityStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();
        $MaskIDEntity = $MaskIDEntityStorage->getMaskIDEntityByMaskID($REQ_MASK_ID);

        if($MaskIDEntity === null){
            return ReturnableResponse::fromItemNotFound('mask_id')->toResponse($response);
        }
        
        if(!empty($REQ_SETTING_ARRAY) && is_array($REQ_SETTING_ARRAY)){
            $oldSetting = $MaskIDEntity->getSettings();
            $parsedSetting = UserSettingInputUtil::modifyWithSettingArray($oldSetting,$REQ_SETTING_ARRAY);
            $MaskIDEntity->setSettings($parsedSetting);
        }else if(!empty($REQ_SETTING_ARRAY) && !is_array($REQ_SETTING_ARRAY)){
            return ReturnableResponse::fromIncorrectFormattedParam('settings')->toResponse($response);
        }

        if($REQ_DISPLAY_NAME !== null){
            if(empty($REQ_DISPLAY_NAME)){
                $REQ_DISPLAY_NAME = null;
            }
            $MaskIDEntity->setDisplayName($REQ_DISPLAY_NAME);
        }

        //Update Database
        $MaskIDEntityStorage->updateMaskIDEntity($MaskIDEntity);
        

        //We successfully put MaskID into database, let's return MaskID
        $outputMaskIDArray = MaskIDOutputUtil::getMaskIDAsAssocArray($MaskIDEntity);
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['mask'] = $outputMaskIDArray;
        return $returnResponse->toResponse($response);
    }
    public function deleteMaskID(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_MASKID = $args['mask_id'];
        $REQ_UID = $REQ_PARAMS['uid'];
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $ctime = time();
        $REMOTE_ADDR = $request->getAttribute('ip');

    
        if(empty($REQ_MASKID) || !is_string($REQ_MASKID) || !MaskIDFormat::isValidMaskID($REQ_MASKID)){
            return ReturnableResponse::fromIncorrectFormattedParam('mask_id')->toResponse($response);
        }

        $checkLoginStatusState = CommonFunction::checkTokenValidResponse($REQ_UID,$REQ_ACCESS_TOKEN,$ctime);
        if($checkLoginStatusState !== null){
            return $checkLoginStatusState->toResponse($response);
        }

        $MaskIDStorage = PDK2021Wrapper::$pdkCore->getMaskIDEntityStorage();
        $MaskIDEntity = $MaskIDStorage->getMaskIDEntityByMaskID($REQ_MASKID);
        if($MaskIDEntity === null){
            return ReturnableResponse::fromPermissionDeniedError('This is not your mask_id')->toResponse($response);
        }
        if($MaskIDEntity->uid !== intval($REQ_UID)){
            return ReturnableResponse::fromPermissionDeniedError('This is not your mask_id')->toResponse($response);
        }
        //Fetch AuthCode and AccessCode Lib, Delete all related tokens
        $AuthCodeStorage = PDK2021Wrapper::$pdkCore->getAPPAuthCodeStorage();
        $APPTokenStorage = PDK2021Wrapper::$pdkCore->getAPPTokenEntityStorage();
        $AuthCodeStorage->clearAuthCode(null,-1,-1,-1,-1,$MaskIDEntity->getMaskID(),APPSystemConstants::NO_APP_RELATED_APPUID);
        $APPTokenStorage->clearAPPToken(0,0,0,0,0,0,0,0,$MaskIDEntity->getMaskID(),APPSystemConstants::NO_APP_RELATED_APPUID);

        //delete MaskID from DB
        $MaskIDStorage->deleteMaskID($MaskIDEntity->getMaskID());

        $returnResponse = new ReturnableResponse(204,0);
        return $returnResponse->toResponse($response);
    }
}