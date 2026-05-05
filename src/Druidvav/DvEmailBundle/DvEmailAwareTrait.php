<?php
namespace Druidvav\DvEmailBundle;

use Druidvav\DvEmailBundle\Message\Message;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait DvEmailAwareTrait
{
    protected ContainerInterface $dvEmailLocator;

    /**
     * @Required
     */
    #[Required]
    public function setDvEmailLocator(ContainerInterface $dvEmailLocator): void
    {
        $this->dvEmailLocator = $dvEmailLocator;
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
