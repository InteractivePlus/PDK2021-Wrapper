<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;
use InteractivePlus\PDK2021Core\Base\DataOperations\MultipleResult;

class MultipleResultOutputUtil{
    public static function getMultipleResultAsAssoc(MultipleResult $result, callable $functionToOutputIndividualItem) : array{
        $resultArr = [
            'offset' => $result->getDataOffset(),
            'count' => $result->getNumResultsStored(),
            'total_count' => $result->getNumTotalQueryResults(),
            'result' => [

            ]
        ];
        foreach($result->getResultArray() as $individualResult){
            $resultArr['result'][] = $functionToOutputIndividualItem($individualResult);
        }
        return $resultArr;
    }
}