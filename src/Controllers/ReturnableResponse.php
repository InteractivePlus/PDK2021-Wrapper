<?php
namespace InteractivePlus\PDK2021\Controllers;

use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKCredentialDismatchError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKInnerArgumentError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKUnknownInnerError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemAlreadyExistError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemExpiredOrUsedError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKItemNotFoundError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKPermissionDeniedError;
use InteractivePlus\PDK2021Core\Base\Exception\ExceptionTypes\PDKRequestParamFormatError;
use InteractivePlus\PDK2021Core\Base\Exception\PDKErrCode;
use InteractivePlus\PDK2021Core\Base\Exception\PDKException;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use Throwable;

class ReturnableResponse implements Stringable{
    public int $returnHTTPCode = 200;
    public int $returnJSONErrCode = 0;
    public ?string $errorDescription = null;
    public ?string $errorFile = null;
    public ?int $errorFileLineNo = null;
    public array $returnFirstLevelEntries = array();
    public array $returnDataLevelEntries = array();
    public function __construct(int $returnHTTPCode, int $returnJSONErrCode)
    {
        $this->returnHTTPCode = $returnHTTPCode;
        $this->returnJSONErrCode = $returnJSONErrCode;
    }
    public function toAssocArray() : array{
        $constructedArr = array(
            'errorCode' => $this->returnJSONErrCode,
            'data' => $this->returnDataLevelEntries
        );
        if(!empty($this->errorDescription)){
            $constructedArr['errorDescription'] = $this->errorDescription;
        }
        if(!empty($this->errorFile)){
            $constructedArr['errorFile'] = $this->errorFile;
        }
        if(!empty($this->errorFileLineNo)){
            $constructedArr['errorLine'] = $this->errorFileLineNo;
        }
        foreach($this->returnFirstLevelEntries as $key => $value){
            if(!isset($constructedArr[$key])){
                $constructedArr[$key] = $value;
            }
        }
        return $constructedArr;
    }
    public function toJSONResponseBody() : string{
        return json_encode($this->toAssocArray());
    }
    public function __toString(){
        return $this->toJSONResponseBody();
    }
    public function toResponse(ResponseInterface $response) : ResponseInterface{
        $response->getBody()->write($this->toJSONResponseBody());
        return $response->withHeader('Content-type','application/json')->withStatus($this->returnHTTPCode);
    }
    public static function fromPDKException(PDKException $e, bool $displayErrorDetail = true) : ReturnableResponse{
        $htmlReturnCode = 0;
        
        if($e->getCode() === 0){
            $htmlReturnCode = 200;
        }else if($e->getCode() < 10){
            //Inner Error
            $htmlReturnCode = 500;
        }else if($e->getCode() < 20){
            //Format Error
            $htmlReturnCode = 409;
        }else if($e->getCode() === PDKErrCode::REQUEST_PARAM_FORMAT_ERROR){ //20
            $htmlReturnCode = 400;
        }
        $response = new ReturnableResponse(
            $htmlReturnCode,
            $e->getCode()
        );
        if($displayErrorDetail){
            $response->errorDescription = $e->getMessage();
            $response->errorFile = $e->getFile();
            $response->errorFileLineNo = $e->getLine();
            $response->returnFirstLevelEntries = $e->toReponseJSON();
        }
        return $response;
    }
    public static function fromThrowable(Throwable $e, bool $displayErrorDetail = true) : ReturnableResponse{
        $htmlReturnCode = 500;
        $response = new ReturnableResponse(
            $htmlReturnCode,
            PDKErrCode::UNKNOWN_INNER_ERROR
        );
        if($displayErrorDetail){
            $response->errorDescription = $e->getMessage();
            $response->errorFile = $e->getFile();
            $response->errorFileLineNo = $e->getLine();
        }
        return $response;
    }
    public static function fromIncorrectFormattedParam(string $paramName) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKRequestParamFormatError(
                $paramName
            )
        );
        return $response;
    }
    public static function fromItemNotFound(string $item) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKItemNotFoundError(
                $item
            )
        );
        return $response;
    }
    public static function fromItemAlreadyExist(string $item) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKItemAlreadyExistError(
                $item
            )
        );
        return $response;
    }
    public static function fromInnerError(?string $message = null) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKUnknownInnerError(
                empty($message) ? '' : $message
            )
        );
        return $response;
    }
    public static function fromItemExpiredOrUsedError(string $item) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKItemExpiredOrUsedError(
                $item
            )
        );
        return $response;
    }
    public static function fromPermissionDeniedError(?string $message = null) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKPermissionDeniedError(
                $message
            )
        );
        return $response;
    }
    public static function fromCredentialMismatchError(string $credential) : ReturnableResponse{
        $response = self::fromPDKException(
            new PDKCredentialDismatchError(
                $credential
            )
        );
        return $response;
    }
}