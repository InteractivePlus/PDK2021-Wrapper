<?php
namespace InteractivePlus\PDK2021Test;

use InteractivePlus\PDK2021\Config;
use InteractivePlus\PDK2021\Implementions\Sender\SendGridEmailServiceProvider;
use PHPUnit\Framework\TestCase;
final class GeneralTest extends TestCase{
    public function testCanSendEmail() : void{
        $config = new Config();
        $email = new \SendGrid\Mail\Mail(); 
        $email->setFrom($config->SENDGRID_FROM_ADDR, $config->SENDGRID_FROM_NAME);
        $email->setSubject("Sending with SendGrid is Fun");
        $email->addTo("example@example.com", "Example User");
        $email->addContent("text/plain", "and easy to do anywhere, even with PHP");
        $email->addContent(
            "text/html", "<strong>and easy to do anywhere, even with PHP</strong>"
        );
        $sendgrid = new \SendGrid($config->SENDGRID_APIKEY);
        try {
            $response = $sendgrid->send($email);
            print $response->statusCode() . "\n";
            print_r($response->headers());
            print $response->body() . "\n";
            $this->assertTrue($response->statusCode() === 202);
        } catch (\Exception $e) {
            echo 'Caught exception: '. $e->getMessage() ."\n";
        }
    }
}