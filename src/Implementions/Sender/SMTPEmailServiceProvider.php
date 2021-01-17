<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\EmailServiceProvider;
use PHPMailer\PHPMailer\PHPMailer;

class SMTPEmailServiceProvider extends EmailServiceProvider{
    private $_smtpClient;
    private ?string $_fromName = null;
    private string $_fromEmail;
    public function __construct(
        string $host,
        string $username,
        string $password,
        int $port = 465,
        string $smtpSecure = 'tls'
    ){
        $this->_smtpClient = new PHPMailer();
        $this->_smtpClient->isSMTP();
        $this->_smtpClient->Host = $host;
        $this->_smtpClient->SMTPAuth = true;
        $this->_smtpClient->Username = $username;
        $this->_smtpClient->Password = $password;
        $this->_smtpClient->Port = $port;
        $this->_smtpClient->SMTPSecure = $smtpSecure;
        $this->_fromEmail = $username;
        $this->setFromEmail($username);
    }
    public function addToAccount(string $address, ?string $name = null) : void{
        $this->_smtpClient->addAddress($address,empty($name) ? '' : $name);
    }
    public function addCCAccount(string $address, ?string $name = null) : void{
        $this->_smtpClient->addCC($address,empty($name) ? '' : $name);
    }
    public function addBccAccount(string $address, ?string $name = null) : void{
        $this->_smtpClient->addBCC($address,empty($name) ? '' : $name);
    }
    public function clearToAccount() : void{
        $this->_smtpClient->clearAddresses();
    }
    public function clearCCAcount() : void{
        $this->_smtpClient->clearCCs();
    }
    public function clearBccAccount() : void{
        $this->_smtpClient->clearBCCs();
    }
    public function setSubject(?string $subject = null) : void{
        $this->_smtpClient->Subject = empty($subject) ? '' : $subject;
    }
    public function setBody(?string $body = null) : void{
        $this->_smtpClient->Body = empty($body) ? '' : $body;
        $this->_smtpClient->isHTML(true);
    }
    public function addEmbeddedImageAsAttachment(string $string, string $cid, ?string $fileName = null, ?string $mimeType = null) : void{
        $this->_smtpClient->addStringEmbeddedImage($string,$cid,empty($fileName) ? '' : $fileName,PHPMailer::ENCODING_BASE64,empty($mimeType) ? '' : $mimeType);
    }
    public function addAttachment(string $string, string $fileName, ?string $mimeType = null) : void{
        $this->_smtpClient->addStringAttachment($string,$fileName,PHPMailer::ENCODING_BASE64,empty($mimeType) ? '' : $mimeType);
    }
    public function clearAttachments() : void{
        $this->_smtpClient->clearAttachments();
    }
    public function setFromName(?string $fromName = null) : void{
        $this->_fromName = $fromName;
        $this->_smtpClient->setFrom($this->_fromEmail,empty($this->_fromName) ? '' : $this->_fromName);
    }
    public function setFromEmail(?string $fromEmail = null) : void{
        $this->_fromEmail = empty($fromEmail) ? $this->_smtpClient->Username : $fromEmail;
        $this->_smtpClient->setFrom($fromEmail, empty($this->_fromName) ? '' : $this->_fromName);
    }
    public function setCharset(string $charset = 'UTF-8'): void
    {
        $this->_smtpClient->CharSet = $charset;
    }
    public function send() : bool{
        return $this->_smtpClient->send();
    }
}