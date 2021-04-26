<?php
namespace InteractivePlus\PDK2021\InputUtils;

use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenScopes;

class ScopeInputUtil{
    public static function parseScopeArray(string $passedScopes) : array{
        $results = [];
        $seperatedByComma = explode(',',$passedScopes);
        foreach($seperatedByComma as $seperateCommaSingle){
            $seperatedBySpace = explode(' ',$seperateCommaSingle);
            foreach($seperatedBySpace as $seperateSpaceSingle){
                $currentScope = strtolower(trim($seperateSpaceSingle));
                if(APPTokenScopes::isValidScope($currentScope) && !in_array($currentScope,$results)){
                    $results[] = $currentScope;
                }
            }
        }
        return $results;
    }
}