<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\EmailServiceProvider;

class SendGridEmailServiceProvider extends EmailServiceProvider{
    private string $_sendgridAPIKey;
    private ?\SendGrid\Mail\Mail $_sendGridMail = null;
    private \SendGrid $_sendGridAPI;
    private ?string $_fromName = null;
    private string $_fromEmail = '';

    public function __construct(string $sendGridAPI, string $fromEmail, ?string $fromName = null)
    {
        $this->_sendgridAPIKey = $sendGridAPI;
        $this->_sendGridAPI = new \SendGrid($sendGridAPI);
        $this->_fromName = $fromName;
        $this->_fromEmail = $fromEmail;
    }
    protected function __checkMailObject() : void{
        if($this->_sendGridMail === null){
            $this->_sendGridMail = new \SendGrid\Mail\Mail();
        }
    }
    public function addToAccount(string $address, ?string $name = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->addTo($address,$name);
    }
    public function addCCAccount(string $address, ?string $name = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->addCc($address,$name);
    }
    public function addBccAccount(string $address, ?string $name = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->addBcc($address,$name);
    }
    public function clearToAccount() : void{
        return;
    }
    public function clearCCAcount() : void{
        return;
    }
    public function clearBccAccount() : void{
        return;
    }
    public function setSubject(?string $subject = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->setSubject(empty($subject) ? 'noSubject' : $subject);
    }
    public function setBody(?string $body = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->addContent('text/html',$body);
    }
    public function addEmbeddedImageAsAttachment(string $string, string $cid, ?string $fileName = null, ?string $mimeType = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->addAttachment($string,$mimeType,$fileName,'inline',$cid);
    }
    public function addAttachment(string $string, string $fileName, ?string $mimeType = null) : void{
        $this->__checkMailObject();
        $this->_sendGridMail->addAttachment($string,$mimeType,$fileName);
    }
    public function clearAttachments() : void{
        return;
    }
    public function setFromName(?string $fromName = null) : void{
        return;
    }
    public function setFromEmail(?string $fromEmail = null) : void{
        return;
    }
    public function setCharset(string $charset = 'UTF-8') : void{
        return;
    }
    public function send() : bool{
        if($this->_sendGridMail === null){
            return false;
        }
        $this->_sendGridMail->setFrom($this->_fromEmail,$this->_fromName);
        $success = true;
        $result = null;
        try{
            $result = $this->_sendGridAPI->send($this->_sendGridMail);
        }catch(\Exception $e){
            $success = false;
            echo $e->getMessage();
        }finally{
            $this->_sendGridMail = null;
        }
        if($success){
            if($result->statusCode() >= 200 && $result->statusCode() < 300){ //Should return 202 as status code, but lets accept any code 2xx
                return true;
            }else{
                return false;
            }
        }
        return false;
    }
    public function clear()
    {
        parent::clear();
        $this->_sendGridMail = null;
    }
}