<?php
namespace Druidvav\DvEmailBundle\EventListener;

use Gedmo\Translatable\TranslatableListener;
use Druidvav\DvEmailBundle\Event\RenderEvent;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\TranslatorInterface;

class EmailListener implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected ?array $oldLocaleState = null;
    protected array $localeConfig = [ ];

    protected RequestContext $requestContext;
    protected ?RequestStack $requestStack;
    protected TranslatorInterface $translator;
    protected ?TranslatableListener $gedmoTranslatable;

    public function __construct(RequestContext $requestContext, ?RequestStack $requestStack, TranslatorInterface $translator, TranslatableListener $gedmoTranslatable = null)
    {
        $this->requestContext = $requestContext;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->gedmoTranslatable = $gedmoTranslatable;
    }

    /** @noinspection PhpUnused */
    public function setLocaleConfig($config)
    {
        $this->localeConfig = $config;
    }

    /** @noinspection PhpUnused */
    public function onBeforeRenderHTML(RenderEvent $event)
    {
        $locale = $event->getMessage()->getLocale();
        $this->oldLocaleState = [
            'locale' => $this->translator->getLocale(),
            'scheme' => $this->requestContext->getScheme(),
            'host' => $this->requestContext->getHost(),
            'httpPort' => $this->requestContext->getHttpPort(),
            'httpsPort' => $this->requestContext->getHttpsPort(),
        ];
        $this->applyConfig($this->localeConfig[$locale]);
    }

    /** @noinspection PhpUnused */
    public function onAfterRenderHTML(RenderEvent $event)
    {
        $old = $this->oldLocaleState;
        if (!empty($old)) $this->applyConfig($old);
        $this->oldLocaleState = null;
    }

    protected function applyConfig($config)
    {
        $this->requestContext
            ->setScheme($config['scheme'])->setHost($config['host'])
            ->setHttpPort($config['httpPort'])->setHttpsPort($config['httpsPort'])
            ->setParameter('_locale', $config['locale']);
        $this->translator->setLocale($config['locale']);
        if ($this->requestStack && $this->requestStack->getCurrentRequest()) {
            $this->requestStack->getCurrentRequest()->setLocale($config['locale']);
        }
        if (!empty($this->gedmoTranslatable)) {
            $this->gedmoTranslatable->setTranslatableLocale($config['locale']);
        }
    }
}
