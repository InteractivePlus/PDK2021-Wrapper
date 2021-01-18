<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use libphonenumber\PhoneNumber;

class LocalFileSMSServiceProvider extends ConsoleSMSServiceProvider{
    private string $_filename;
    public function __construct(string $filename)
    {
        $this->_filename = $filename;
    }
    public function sendSMS(PhoneNumber $numberToReceive, string $content, bool $enableSMSSplit) : bool{
        $assocArr = parent::toAssocArr($numberToReceive,$content);
        $previousContent = file_exists($this->_filename) ? file_get_contents($this->_filename) . '\r\n' : '';
        file_put_contents($this->_filename,$previousContent . json_encode($assocArr));
        return true;
    }
}