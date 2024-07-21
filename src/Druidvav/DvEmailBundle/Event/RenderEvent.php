<?php
namespace Druidvav\DvEmailBundle\Event;

use Druidvav\DvEmailBundle\Message\Message;
use Symfony\Component\EventDispatcher\Event;

class RenderEvent extends Event
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