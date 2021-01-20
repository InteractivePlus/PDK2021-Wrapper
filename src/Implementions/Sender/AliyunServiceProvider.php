<?php
namespace InteractivePlus\PDK2021\Implementions\Sender;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Dm\Dm;
use InteractivePlus\PDK2021Core\Communication\CommunicationMethods\EmailServiceProvider;

class AliyunServiceProvider extends EmailServiceProvider{
    private ?string $_toClientAddr = null;
    private ?string $_subject = null;
    private ?string $_body = null;
    private ?string $_fromAddr = null;
    private ?string $_fromName = null;
    
    public function __construct(string $accessKeyID, string $accessKeySecret){
        AlibabaCloud::accessKeyClient($accessKeyID,$accessKeySecret)->regionId('cn-hangzhou')->name('pdkAliyunEmailServiceProvider');
    }

    public function addToAccount(string $address, ?string $name = null) : void{
        $this->_toClientAddr = $address;
    }
    public function addCCAccount(string $address, ?string $name = null) : void{
        return;
    }
    public function addBccAccount(string $address, ?string $name = null) : void{
        return;
    }
    public function clearToAccount() : void{
        $this->_toClientAddr = null;
    }
    public function clearCCAcount() : void{
        return;
    }
    public function clearBccAccount() : void{
        return;
    }
    public function setSubject(?string $subject = null) : void{
        $this->_subject = $subject;
    }
    public function setBody(?string $body = null) : void{
        $this->_body = $body;
    }
    public function addEmbeddedImageAsAttachment(string $string, string $cid, ?string $fileName = null, ?string $mimeType = null) : void{
        return;
    }
    public function addAttachment(string $string, string $fileName, ?string $mimeType = null) : void{
        return;
    }
    public function clearAttachments() : void{
        return;
    }
    public function setFromName(?string $fromName = null) : void{
        $this->_fromName = $fromName;
    }
    public function setFromEmail(?string $fromEmail = null) : void{
        $this->_fromAddr = $fromEmail;
    }
    public function setCharset(string $charset = 'UTF-8') : void{
        return;
    }
    public function send() : bool{
        if(empty($this->_fromAddr) || empty($this->_toClientAddr) || empty($this->_subject)){
            return false;
        }
        try{
            $request = Dm::v20151123()->singleSendMail()
                                            ->client('pdkAliyunEmailServiceProvider')
                                            ->withAccountName($this->_fromAddr)
                                            ->withAddressType('1')
                                            ->withReplyToAddress('false')
                                            ->withToAddress($this->_toClientAddr)
                                            ->withClickTrace('1')
                                            ->withSubject($this->_subject)
                                            ->withHtmlBody($this->_body);
            if(!empty($this->_fromName)){
                $request = $request->withFromAlias($this->_fromName);
            }
            $request->request();
        }catch(ClientException $e){
            var_dump($e);
            return false;
        }catch(ServerException $e){
            var_dump($e);
            return false;
        }
        return true;
    }
}