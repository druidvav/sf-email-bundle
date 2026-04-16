<?php
namespace Druidvav\DvEmailBundle\Event;

use Druidvav\DvEmailBundle\Message\Message;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Диспатчится после успешной отправки через Mailer.
 */
class AfterSendEvent extends Event
{
    protected Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
