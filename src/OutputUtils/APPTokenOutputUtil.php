<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\APP\APPToken\APPTokenEntity;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class APPTokenOutputUtil{
    public static function getAPPTokenAsAssocArray(APPTokenEntity $token) : array{
        return array(
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken(),
            'client_id' => $token->getClientID(),
            'obtained_method' => $token->getObtainedMethod(),
            'issued' => $token->issueTime,
            'expires' => $token->expireTime,
            'last_renewed' => $token->lastRefreshTime,
            'refresh_expires' => $token->refreshExpireTime,
            'mask_id' => $token->getMaskID(),
            'scope' => $token->scopes
        );
    }
}