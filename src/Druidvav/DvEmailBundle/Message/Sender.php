<?php
namespace Druidvav\DvEmailBundle\Message;

use Druidvav\DvEmailBundle\Event\AfterSendEvent;
use Druidvav\DvEmailBundle\Event\BeforeSendEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class Sender
{
    protected EventDispatcherInterface $eventDispatcher;
    protected MailerInterface $mailer;

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher) { $this->eventDispatcher = $eventDispatcher; }
    public function setMailer(MailerInterface $mailer) { $this->mailer = $mailer; }

    /**
     * @return $this
     * @throws TransportException
     */
    public function send(Message $message): Sender
    {
        $this->eventDispatcher->dispatch(new BeforeSendEvent($message));
        $this->mailer->send($message->getEmail());
        $this->eventDispatcher->dispatch(new AfterSendEvent($message));
        return $this;
    }
}
