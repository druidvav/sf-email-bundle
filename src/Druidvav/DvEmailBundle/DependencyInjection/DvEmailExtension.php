<?php
namespace Druidvav\DvEmailBundle\DependencyInjection;

use Druidvav\DvEmailBundle\Event\AfterRenderHtmlEvent;
use Druidvav\DvEmailBundle\Event\BeforeRenderHtmlEvent;
use Druidvav\DvEmailBundle\EventListener\EmailListener;
use Druidvav\DvEmailBundle\Message\Config;
use Druidvav\DvEmailBundle\Message\Message;
use Druidvav\DvEmailBundle\Message\Sender;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DvEmailExtension extends Extension
{
    public function getAlias(): string
    {
        return 'dv_email';
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = array();
        foreach ($configs as $subConfig) {
            $config = array_merge($config, $subConfig);
        }
        foreach ($config['sender'] as $alias => $options) {
            $this->registerSender($container, $alias, $options);
        }
        $messageAliases = array_keys($config['message']);
        foreach ($config['message'] as $alias => $options) {
            $this->registerConfig($container, $alias, $options);
            $this->registerMessage($container, $alias, array_keys($config['sender']));
        }
        $this->registerRegistry($container, $messageAliases);
        if (!empty($config['locale'])) {
            $this->registerLocaleListener($container, $config['locale']);
        }
    }

    protected function registerSender(ContainerBuilder $container, $alias, $options)
    {
        $optionId = sprintf('dv_email.%s.sender', $alias);
        $optionDef = new Definition(Sender::class);
        $optionDef->addMethodCall('setEventDispatcher', [ new Reference('event_dispatcher') ]);
        $optionDef->addMethodCall('setMailer', [ new Reference(!empty($options['mailer']) ? $options['mailer'] : 'mailer') ]);
        $container->setDefinition($optionId, $optionDef);
    }

    protected function registerConfig(ContainerBuilder $container, $alias, $options)
    {
        $optionId = sprintf('dv_email.%s.config', $alias);
        $optionDef = new Definition(Config::class);
        // Dependency references
        $optionDef->addMethodCall('setTwig', [ new Reference('twig') ]);
        $optionDef->addMethodCall('setCachePath', [ $container->getParameter('kernel.cache_dir') ]);
        // Options
        $optionDef->addMethodCall('setTemplatePath', [ $options['template_path'] ]);
        if (!empty($options['cache_inlined_css'])) {
            $optionDef->addMethodCall('setCacheInlinedCSS', [ $options['cache_inlined_css'] ]);
        }
        if (!empty($options['css_file'])) {
            $optionDef->addMethodCall('setCssFile', [ $options['css_file'] ]);
        }
        if (!empty($options['from'])) {
            $optionDef->addMethodCall('setFrom', [ $options['from'] ]);
        }
        if (!empty($options['reply_to'])) {
            $optionDef->addMethodCall('setReplyTo', [ $options['reply_to'] ]);
        }
        if (!empty($options['domain'])) {
            $optionDef->addMethodCall('setDomain', [ $options['domain'] ]);
        }
        if (!empty($options['embed_images'])) {
            $optionDef->addMethodCall('setEmbedImages', [ $options['embed_images']['url'], $options['embed_images']['path'] ]);
        }
        $container->setDefinition($optionId, $optionDef);
    }

    protected function registerMessage(ContainerBuilder $container, $alias, $senders)
    {
        $optionId = sprintf('dv_email.%s.message', $alias);
        $optionDef = new Definition(Message::class);
        $optionDef->setShared(false);
        $optionDef->addMethodCall('setEventDispatcher', [ new Reference('event_dispatcher') ]);
        $optionDef->addMethodCall('setConfig', [ new Reference(sprintf('dv_email.%s.config', $alias)) ]);
        foreach ($senders as $sender) {
            $optionDef->addMethodCall('addSender', [ $sender, new Reference(sprintf('dv_email.%s.sender', $sender)) ]);
        }
        $container->setDefinition($optionId, $optionDef);
        if ($alias === 'default') {
            $container->setAlias('dv_email.message', $optionId);
        }
    }

    protected function registerRegistry(ContainerBuilder $container, array $messageAliases)
    {
        $locatorServices = [];
        foreach ($messageAliases as $alias) {
            $locatorServices[$alias] = new ServiceClosureArgument(new Reference(sprintf('dv_email.%s.message', $alias)));
        }
        $locatorDef = new Definition(ServiceLocator::class, [$locatorServices]);
        $locatorDef->addTag('container.service_locator');
        $locatorDef->setPublic(true);
        $container->setDefinition('dv_email.locator', $locatorDef);
        $container->registerAliasForArgument('dv_email.locator', PsrContainerInterface::class, 'dvEmailLocator');
    }

    protected function registerLocaleListener(ContainerBuilder $container, $config)
    {
        $container->setParameter('dv_email.locale_config', $config);
        $optionDef = new Definition(EmailListener::class);
        $optionDef->addArgument(new Reference('router.request_context'));
        $optionDef->addArgument(new Reference('request_stack'));
        $optionDef->addArgument(new Reference('translator'));
        $optionDef->addArgument(new Reference('stof_doctrine_extensions.listener.translatable', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        $optionDef->addMethodCall('setLocaleConfig', [ $container->getParameter('dv_email.locale_config') ]);
        $optionDef->addTag('kernel.event_listener', [ 'event' => BeforeRenderHtmlEvent::class, 'method' => 'onBeforeRenderHTML', 'priority' => 10 ]);
        $optionDef->addTag('kernel.event_listener', [ 'event' => AfterRenderHtmlEvent::class, 'method' => 'onAfterRenderHTML', 'priority' => -10 ]);
        $container->setDefinition('dv_email.locale.listener', $optionDef);
    }
}
