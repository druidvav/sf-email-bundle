<?php
namespace Druidvav\DvEmailBundle;

use Druidvav\DvEmailBundle\Message\Message;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

trait DvEmailAwareTrait
{
    use ContainerAwareTrait;

    protected function getDefaultMessage(): string { return 'default'; }

    /** @noinspection PhpIncompatibleReturnTypeInspection */
    protected function createMessage(): Message
    {
        return $this->container->get('rage_email.' . $this->getDefaultMessage() . '.message');
    }

    protected function createMessageForUser(UserInterface $user, $template = null, array $vars = [ ]): Message
    {
        return $this->createMessage()->createForUser($user, $template, $vars);
    }
}
