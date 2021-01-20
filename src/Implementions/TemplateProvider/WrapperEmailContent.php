<?php
namespace InteractivePlus\PDK2021\Implementions\TemplateProvider;

use InteractivePlus\PDK2021Core\Communication\CommunicationContents\Implementions\EmailTemplateContent\EmailTemplateProvider;

class WrapperEmailContent implements EmailTemplateProvider{
    const BASIC_DIR = __DIR__ . '/../../../emailTemplates/';
    public function getNormalTemplate(?string $locale = null) : string{
        $path = self::BASIC_DIR . $locale . '/EmailVericode.html';
        $content = '';
        if(is_file($path)){
            $content = file_get_contents($path);
        }else{
            $content = file_get_contents(self::BASIC_DIR . '/zh_CN/EmailVericode.html');
        }
        return $content;
    }
    public function getNormalSafeTemplate(?string $locale = null) : string{
        $path = self::BASIC_DIR . $locale . '/EmailVericodeSafe.html';
        $content = '';
        if(is_file($path)){
            $content = file_get_contents($path);
        }else{
            $content = file_get_contents(self::BASIC_DIR . '/zh_CN/EmailVericodeSafe.html');
        }
        return $content;
    }
    public function getURLTemplate(?string $locale = null) : string{
        $path = self::BASIC_DIR . $locale . '/EmailVericodeWithLink.html';
        $content = '';
        if(is_file($path)){
            $content = file_get_contents($path);
        }else{
            $content = file_get_contents(self::BASIC_DIR . '/zh_CN/EmailVericodeWithLink.html');
        }
        return $content;
    }
    public function getURLSafeTemplate(?string $locale = null) : string{
        $path = self::BASIC_DIR . $locale . '/EmailVericodeWithLinkSafe.html';
        $content = '';
        if(is_file($path)){
            $content = file_get_contents($path);
        }else{
            $content = file_get_contents(self::BASIC_DIR . '/zh_CN/EmailVericodeWithLinkSafe.html');
        }
        return $content;
    }
}