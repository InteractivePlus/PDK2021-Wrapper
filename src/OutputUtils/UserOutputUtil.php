<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use InteractivePlus\PDK2021Core\User\UserInfo\UserEntity;

class UserOutputUtil{
    public static function getUserEntityAsAssocArray(UserEntity $userEntity) : array{
        return array(
            "uid" => $userEntity->getUID(),
            "nickname" => $userEntity->getNickName(),
            "signature" => $userEntity->getSignature(),
            "email" => $userEntity->getEmail(),
            "phone" => $userEntity->getPhoneNumber() === null ? null : UserPhoneUtil::outputPhoneNumberE164($userEntity->getPhoneNumber()),
            "emailVerified" => $userEntity->isEmailVerified(),
            "phoneVerified" => $userEntity->isPhoneVerified(),
            "accountFrozen" => $userEntity->isAccountFrozen()
        );
    }
}