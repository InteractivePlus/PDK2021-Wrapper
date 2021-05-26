<?php
namespace InteractivePlus\PDK2021\OutputUtils;

use InteractivePlus\PDK2021\PDK2021Wrapper;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketRecordEntity;
use InteractivePlus\PDK2021Core\EXT_Ticket\TicketRecord\OAuthTicketSingleContent;

class TicketOutputUtil{
    public static function getTicketSingleContentAsAssoc(OAuthTicketSingleContent $singleContent) : array{
        return array(
            'is_creator_content' => $singleContent->isTicketCreatorContent,
            'responder_name' => $singleContent->getResponderName(),
            'responded' => $singleContent->respondTime,
            'last_edited' => $singleContent->lastEditTime,
            'content' => $singleContent->getContentStr()
        );
    }
    public static function getTicketAsAssoc(OAuthTicketRecordEntity $ticket, bool $hideImportantInfo = true) : array{
        $contentListings = [];
        foreach($ticket->contentListing as $singleContent){
            $contentListings[] = self::getTicketSingleContentAsAssoc($singleContent);
        }
        $result = array(
            'title' => $ticket->getTicketTitle(),
            'content_listing' => $contentListings,
            'ticket_id' => $ticket->getTicketID(),
            'appuid' => $ticket->appuid,
            'client_id' => $ticket->getClientID(),
            'uid' => $ticket->uid,
            'mask_id' => $ticket->getMaskID(),
            'access_token' => $ticket->getAccessToken(),
            'is_urgent' => $ticket->isUrgent,
            'created' => $ticket->createTime,
            'last_updated' => $ticket->lastUpdateTime,
            'is_resolved' => $ticket->isResolved,
            'is_closed' => $ticket->isClosed
        );
        if($hideImportantInfo){
            if(!empty($result['mask_id'])){
                unset($result['uid']);
            }
            if(!empty($result['client_id'])){
                unset($result['appuid']);
            }
        }
        return $result;
    }
}