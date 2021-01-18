<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\EmailServiceProvider;

class ConsoleEmailServiceProvider extends EmailServiceProvider{
    private array $toAddr = array();
    private array $ccAccount = array();
    private array $bccAccount = array();
    private string $subject = '';
    private string $body = '';
    private array $embedImages = array();
    private array $attachment = array();
    private ?string $fromName = null;
    private ?string $fromEmail = null;
    private ?string $charset = null;
    
    public function addToAccount(string $address, ?string $name = null) : void{
        $this->toAddr[] = [$address, $name];
    }
    public function addCCAccount(string $address, ?string $name = null) : void{
        $this->ccAccount[] = [$address,$name];
    }
    public function addBccAccount(string $address, ?string $name = null) : void{
        $this->bccAccount[] = [$address, $name];
    }
    public function clearToAccount() : void{
        $this->toAddr = array();
    }
    public function clearCCAcount() : void{
        $this->ccAccount = array();
    }
    public function clearBccAccount() : void{
        $this->bccAccount = array();
    }
    public function setSubject(?string $subject = null) : void{
        $this->subject = $subject;
    }
    public function setBody(?string $body = null) : void{
        $this->body = $body;
    }
    public function addEmbeddedImageAsAttachment(string $string, string $cid, ?string $fileName = null, ?string $mimeType = null) : void{
        $this->embedImages[] = array(
            'data' => $string,
            'cid' => $cid,
            'fileName' => $fileName,
            'mimeType' => $mimeType
        );
    }
    public function addAttachment(string $string, string $fileName, ?string $mimeType = null) : void{
        $this->attachment[] = array(
            'data' => $string,
            'fileName' => $fileName,
            'mimeType' => $mimeType
        );
    }
    public function clearAttachments() : void{
        $this->attachment = array();
        $this->embedImages = array();
    }
    public function setFromName(?string $fromName = null) : void{
        $this->fromName = $fromName;
    }
    public function setFromEmail(?string $fromEmail = null) : void{
        $this->fromEmail = $fromEmail;
    }
    public function setCharset(string $charset = 'UTF-8') : void{
        $this->charset = $charset;
    }
    public function send() : bool{
        print $this->toJSONString() . "\n";
        return true;
    }
    public function toAssocArr() : array{
        return array(
            'fromName' => $this->fromName,
            'fromEmail' => $this->fromEmail,
            'Subject' => $this->subject,
            'Body' => $this->body,
            'attachments' => $this->attachment,
            'embeddedImages' => $this->embedImages,
            'to' => $this->toAddr,
            'cc' => $this->ccAccount,
            'bcc' => $this->bccAccount
        );
    }
    public function toJSONString() : string{
        return json_encode($this->toAssocArr());
    }
}