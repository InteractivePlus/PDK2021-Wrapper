<?php
namespace InteractivePlus\PDK2021\Controllers\OAuthSystem;

use InteractivePlus\PDK2021\Controllers\ReturnableResponse;
use InteractivePlus\PDK2021\GatewayFunctions\CommonFunction;
use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenScopes;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKPermissionDeniedError;
use InteractivePlus\PDK2021Core\EXT_Storage\StorageRecord\OAuthStorageRecordEntity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EXT_StorageAbilityController{
    public function isDataPresent(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();
        $RequiredScope = APPTokenScopes::SCOPE_STORE_DATA()->getScopeName();

        $StorageRecordStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthStorageRecordStorage();
        if($StorageRecordStorage === null){
            return ReturnableResponse::fromPermissionDeniedError('Extension not enabled')->toResponse($response);
        }

        $checkAPPTokenResult = CommonFunction::checkAPPTokenValidAndScopeSatisfiedResponse($REQ_ACCESS_TOKEN,$RequiredScope,$ctime);
        if(!$checkAPPTokenResult->succeed){
            return $checkAPPTokenResult->returnableResponse->toResponse($response);
        }

        $APPTokenEntity = $checkAPPTokenResult->tokenEntity;
        $exists = $StorageRecordStorage->checkOAuthStorageRecordExist($APPTokenEntity->getMaskID(),$APPTokenEntity->getClientID());
        
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['is_record'] = $exists;
        return $returnResponse->toResponse($response);
    }
    public function getData(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = $request->getQueryParams();
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();
        $RequiredScope = APPTokenScopes::SCOPE_STORE_DATA()->getScopeName();

        $StorageRecordStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthStorageRecordStorage();
        if($StorageRecordStorage === null){
            return ReturnableResponse::fromPermissionDeniedError('Extension not enabled')->toResponse($response);
        }

        $checkAPPTokenResult = CommonFunction::checkAPPTokenValidAndScopeSatisfiedResponse($REQ_ACCESS_TOKEN,$RequiredScope,$ctime);
        if(!$checkAPPTokenResult->succeed){
            return $checkAPPTokenResult->returnableResponse->toResponse($response);
        }

        $APPTokenEntity = $checkAPPTokenResult->tokenEntity;

        $RecordEntity = $StorageRecordStorage->getOAuthStorageRecord($APPTokenEntity->getMaskID(),$APPTokenEntity->getClientID());
        if($RecordEntity === null){
            return ReturnableResponse::fromItemNotFound('stored_data')->toResponse($response);
        }

        $fetchedData = $RecordEntity->getData();
        $returnResponse = new ReturnableResponse(200,0);
        $returnResponse->returnDataLevelEntries['stored_data'] = $fetchedData;
        return $returnResponse->toResponse($response);
    }
    public function putData(ServerRequestInterface $request, ResponseInterface $response, array $args) : ResponseInterface{
        $REQ_PARAMS = json_decode($request->getBody(),true);
        $REQ_ACCESS_TOKEN = $REQ_PARAMS['access_token'];
        $REQ_DATA = $REQ_PARAMS['data'];
        $REMOTE_ADDR = $request->getAttribute('ip');
        $ctime = time();
        $RequiredScope = APPTokenScopes::SCOPE_STORE_DATA()->getScopeName();

        $StorageRecordStorage = PDK2021Wrapper::$pdkCore->getEXTOAuthStorageRecordStorage();
        if($StorageRecordStorage === null){
            return ReturnableResponse::fromPermissionDeniedError('Extension not enabled')->toResponse($response);
        }

        $checkAPPTokenResult = CommonFunction::checkAPPTokenValidAndScopeSatisfiedResponse($REQ_ACCESS_TOKEN,$RequiredScope,$ctime);
        if(!$checkAPPTokenResult->succeed){
            return $checkAPPTokenResult->returnableResponse->toResponse($response);
        }

        $APPTokenEntity = $checkAPPTokenResult->tokenEntity;

        if(!empty($REQ_DATA) && !is_array($REQ_DATA)){
            return ReturnableResponse::fromIncorrectFormattedParam('data')->toResponse($response);
        }
        
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $APPEntity = $APPEntityStorage->getAPPEntityByAPPUID($APPTokenEntity->appuid);
        if($APPEntity === null){
            return ReturnableResponse::fromInnerError('Cannot fetch APPEntity of a exisitng and verified APPToken')->toResponse($response);
        }

        $recordEntity = $StorageRecordStorage->getOAuthStorageRecord($APPTokenEntity->getMaskID(),$APPTokenEntity->getClientID());
        $successResponseCode = 0;
        if($recordEntity === null){
            //Record DNE
            $successResponseCode = 201;
            //Make sure that the APP is not exceeding its record # limit
            $existingRecordNum = $StorageRecordStorage->getOAuthStorageRecordEntityCount(-1,-1,-1,-1,null,null,$APPTokenEntity->appuid);
            if($existingRecordNum >= $APPEntity->getPermission()->maxDataRecordNumber){
                return ReturnableResponse::fromPermissionDeniedError('Your APP has reached its data record limit')->toResponse($response);
            }

            try{
                $recordEntity = OAuthStorageRecordEntity::create(
                    $APPTokenEntity->getMaskID(),
                    $APPTokenEntity->getClientID(),
                    $APPTokenEntity->appuid,
                    $REQ_DATA,
                    $APPEntity->getPermission(),
                    $ctime
                );
            }catch(PDKPermissionDeniedError $e){
                return ReturnableResponse::fromPermissionDeniedError('Your APP doesn\'t have privilege to store this type of data. It might be too big or too deep in depth')->toResponse($response);
            }
            $StorageRecordStorage->addOAuthStorageRecord($recordEntity);
        }else{
            $successResponseCode = 200;
            try{
                $recordEntity->setData($REQ_DATA,$APPEntity->getPermission());
            }catch(PDKPermissionDeniedError $e){
                return ReturnableResponse::fromPermissionDeniedError('Your APP doesn\'t have privilege to store this type of data. It might be too big or too deep in depth')->toResponse($response);
            }
            $StorageRecordStorage->updateOAuthStorageRecord($recordEntity);
        }

        //Finished Updating
        $returnResponse = new ReturnableResponse($successResponseCode,0);
        $returnResponse->returnDataLevelEntries['new_record'] = $successResponseCode === 201;
        return $returnResponse->toResponse($response);
    }
}