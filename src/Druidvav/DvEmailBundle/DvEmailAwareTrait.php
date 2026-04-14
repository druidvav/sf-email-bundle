<?php
namespace Druidvav\DvEmailBundle;

use Druidvav\DvEmailBundle\Message\Message;
use Psr\Container\ContainerInterface;

trait DvEmailAwareTrait
{
    protected ContainerInterface $dvEmailLocator;

    public function setDvEmailLocator(ContainerInterface $locator): void
    {
        $this->dvEmailLocator = $locator;
    }

    protected function getDefaultMessage(): string { return 'default'; }

    protected function createMessage(): Message
    {
        return $this->dvEmailLocator->get($this->getDefaultMessage());
    }

    protected function createMessageForUser(UserInterface $user, $template = null, array $vars = []): Message
    {
        return $this->createMessage()->createForUser($user, $template, $vars);
    }
}
