<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\SMSServiceProvider;
use InteractivePlus\PDK2021Core\User\Formats\UserPhoneUtil;
use libphonenumber\PhoneNumber;

class ConsoleSMSServiceProvider implements SMSServiceProvider{
    public function sendSMS(PhoneNumber $numberToReceive, string $content, bool $enableSMSSplit) : bool{
        $assocArr = $this->toAssocArr($numberToReceive,$content);
        print json_encode($assocArr) . "\n";
        return true;
    }
    public function toAssocArr(PhoneNumber $numberToReceive, string $content) : array{
        return array(
            'number' => UserPhoneUtil::outputPhoneNumberIntl($numberToReceive),
            'content' => $content
        );
    }
}