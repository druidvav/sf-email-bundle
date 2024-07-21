<?php
namespace Druidvav\DvEmailBundle\Message;

use Druidvav\DvEmailBundle\Event\SendEvent;
use Druidvav\DvEmailBundle\DvEmailEvent;
use Druidvav\DvEmailBundle\Swift\SmtpTransport;
use Swift_Mailer;
use Swift_RfcComplianceException;
use Swift_TransportException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Sender
{
    protected EventDispatcherInterface $eventDispatcher;
    protected Swift_Mailer $mailer;
    protected ?Swift_Mailer $fallbackMailer = null;

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher) { $this->eventDispatcher = $eventDispatcher; }
    public function setPrimaryMailer(Swift_Mailer $mailer) { $this->mailer = $mailer; }
    public function setFallbackMailer(Swift_Mailer $mailer) { $this->fallbackMailer = $mailer; }

    /**
     * @param Message $message
     * @return Sender
     * @throws Swift_TransportException
     * @throws Swift_RfcComplianceException
     */
    public function send(Message $message): Sender
    {
        $this->eventDispatcher->dispatch(DvEmailEvent::BEFORE_SEND, new SendEvent($message));
        try {
            $this->internalSend($this->mailer, $message);
        } catch (Swift_TransportException $exception) {
            if ($this->fallbackMailer) {
                $this->internalSend($this->fallbackMailer, $message);
            } else {
                throw $exception;
            }
        }
        return $this;
    }

    /**
     * @param Swift_Mailer $mailer
     * @param Message $message
     * @throws Swift_TransportException
     * @throws Swift_RfcComplianceException
     */
    protected function internalSend(Swift_Mailer $mailer, Message $message)
    {
        /** @var SmtpTransport $transport */
        $transport = $mailer->getTransport();
        try {
            $mailer->send($message->getSwiftMessage());
            if ($transport instanceof SmtpTransport) {
                $server = $transport->getHost();
                $eximId = $transport->getLastEximId();
            } else {
                $server = null;
                $eximId = null;
            }
            $this->eventDispatcher->dispatch(DvEmailEvent::AFTER_SEND, new SendEvent($message, $server, $eximId));
        } catch (Swift_TransportException $exception) {
            $transport->stop();
            throw $exception;
        }
    }
}