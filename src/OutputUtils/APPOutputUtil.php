<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021Core\APP\APPInfo\APPEntity;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class APPOutputUtil{
    public static function getAPPEntityAsAssocArray(APPEntity $appEntity) : array{
        return array(
            'appuid' => $appEntity->getAPPUID(),
            'display_name' => $appEntity->getDisplayName(),
            'client_id' => $appEntity->getClientID(),
            'client_secret' => $appEntity->getClientSecret(),
            'client_type' => $appEntity->getClientType(),
            'redirectURI' => $appEntity->redirectURI,
            'create_time' => $appEntity->createTime,
            'owner_uid' => $appEntity->ownerUID
        );
    }
}