<?php
namespace InteractivePlus\PDK2021;

use InteractivePlus\PDK2021Core\Base\FrontendIntegration\UserSystemLinkProvider;

class LinkProvider implements UserSystemLinkProvider{
    public function verifyEmailLink(?string $locale = null) : string{
        return 'http://localhost/?veriCode={{veriCode}}';
    }
    public function forgotPasswordLink(?string $locale = null) : string{
        return 'http://localhost/?veriCode={{veriCode}}';
    }
    public function changeEmailLink(?string $locale = null) : string{
        return 'http://localhost/?veriCode={{veriCode}}';
    }
    public function changePhoneLink(?string $locale = null) : string{
        return 'http://localhost/?veriCode={{veriCode}}';
    }
}