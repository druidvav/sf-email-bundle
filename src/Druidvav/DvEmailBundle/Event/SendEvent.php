<?php
namespace Druidvav\DvEmailBundle\Event;

use Druidvav\DvEmailBundle\Message\Message;
use Symfony\Component\EventDispatcher\Event;

class SendEvent extends Event
{
    protected Message $message;
    protected ?string $server = null;
    protected ?string $eximId = null;

    public function __construct(Message $message, $server = null, $eximId = null)
    {
        $this->message = $message;
        $this->server = $server;
        $this->eximId = $eximId;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getServer(): ?string
    {
        return $this->server;
    }

    public function getEximId(): ?string
    {
        return $this->eximId;
    }
}