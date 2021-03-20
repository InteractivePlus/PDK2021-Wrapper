<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021Core\APP\MaskID\MaskIDEntity;

class MaskIDOutputUtil{
    public static function getMaskIDAsAssocArray(MaskIDEntity $maskID) : array{
        return array(
            'mask_id' => $maskID->getMaskID(),
            'appuid' => $maskID->appuid,
            'uid' => $maskID->uid,
            'display_name' => $maskID->getDisplayName(),
            'createTime' => $maskID->createTime,
            'settings' => UserSettingOutputUtil::getUserSettingAsAssocArray($maskID->getSettings())
        );
    }
}