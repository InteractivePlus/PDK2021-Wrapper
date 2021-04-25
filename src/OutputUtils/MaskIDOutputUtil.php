<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;

class MaskIDOutputUtil{
    public static function getMaskIDAsAssocArray(MaskIDEntity $maskID) : array{
        $APPEntityStorage = PDK2021Wrapper::$pdkCore->getAPPEntityStorage();
        $APPEntity = $APPEntityStorage->getAPPEntityByAPPUID($maskID->appuid);
        return array(
            'mask_id' => $maskID->getMaskID(),
            'client_id' => $APPEntity->getClientID(),
            'uid' => $maskID->uid,
            'display_name' => $maskID->getDisplayName(),
            'createTime' => $maskID->createTime,
            'settings' => UserSettingOutputUtil::getUserSettingAsAssocArray($maskID->getSettings())
        );
    }
    public static function getMaskIDAsOAuthInfoAssocArray(MaskIDEntity $maskID) : array{
        return array(
            'mask_id' => $maskID->getMaskID(),
            'display_name' => $maskID->getDisplayName(),
            'settings' => UserSettingOutputUtil::getUserSettingAsAssocArray($maskID->getSettings())
        );
    }
}