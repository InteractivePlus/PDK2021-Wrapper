<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use libphonenumber\PhoneNumber;

class LocalFileEmailServiceProvider extends ConsoleEmailServiceProvider{
    private string $_filename;
    public function __construct(string $filename)
    {
        $this->_filename = $filename;
    }
    public function send() : bool{
        $previousContent = file_exists($this->_filename) ? file_get_contents($this->_filename) . '\r\n' : '';
        file_put_contents($this->_filename,$previousContent . parent::toJSONString());
        return true;
    }
}