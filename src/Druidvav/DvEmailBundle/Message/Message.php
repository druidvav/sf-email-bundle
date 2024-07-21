<?php
namespace Druidvav\DvEmailBundle\Message;

use Exception;
use Druidvav\DvEmailBundle\Event\RenderEvent;
use Druidvav\DvEmailBundle\DvEmailEvent;
use Druidvav\DvEmailBundle\UserInterface;
use Pelago\Emogrifier\CssInliner;
use Ramsey\Uuid\Uuid;
use Swift_DependencyContainer;
use Swift_Image;
use Swift_Message;
use Swift_RfcComplianceException;
use Swift_TransportException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Message
{
    protected Config $config;
    protected EventDispatcherInterface $eventDispatcher;
    /** @var Sender[] */
    protected array $senders = [ ];

    // Message-specific raw fields
    protected string $id;
    protected $to;
    protected $tpl;
    protected $vars = [ ];
    protected $locale = null;

    protected bool $isBulk = false;
    protected ?string $unsubscribeUrl = null;

    // Rendered fields
    protected bool $rendered = false;
    protected $subject;
    protected $txtMessage;
    protected $htmlMessage;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function createForUser(UserInterface $user, $template = null, $vars = null): Message
    {
        $this->setTo($user->getEmail());
        $this->setLocale($user->getLocale());
        $this->setVars([ 'user' => $user ]);
        if (!empty($template)) $this->setTemplate($template);
        if (!empty($vars)) $this->setVars($vars);
        return $this;
    }

    public function setConfig(Config $config) { $this->config = $config; }
    public function getConfig(): Config { return $this->config; }
    public function setEventDispatcher(EventDispatcherInterface $dispatcher) { $this->eventDispatcher = $dispatcher; }
    public function addSender($alias, Sender $sender) { $this->senders[$alias] = $sender; }

    public function getId(): string { return $this->id; }
    public function getLocale(): ?string { return $this->locale; }
    public function setLocale($locale): Message { $this->locale = $locale; return $this; }
    public function getTemplate(): string { return $this->tpl . ($this->locale ? '/' . $this->locale : ''); }
    public function setTemplate($tpl): Message { $this->tpl = $tpl; return $this; }

    /** @noinspection PhpUnused */
    public function setBulk(bool $value): Message
    {
        $this->isBulk = $value;
        return $this;
    }

    /** @noinspection PhpUnused */
    public function setUnsubscribeUrl(?string $url): Message
    {
        $this->unsubscribeUrl = $url;
        return $this;
    }

    public function getVars()
    {
        $this->vars['msg_id'] = $this->getId();
        $this->vars['template'] = $this->getTemplate();
        $this->vars['utm_params'] = 'utm_source=email&utm_medium=transaction&utm_campaign=' . $this->getTemplate();
        return $this->vars;
    }

    public function setVars($vars): Message
    {
        foreach ($vars as $key => $value) {
            $this->vars[$key] = $value;
        }
        return $this;
    }

    public function getValue($var, $def = null)
    {
        return !empty($this->vars[$var]) ? $this->vars[$var] : $def;
    }

    public function setTo($to): Message { $this->to = $to; return $this; }
    public function getTo() { return $this->to; }
    public function setSubject($subject) { $this->subject = $subject; }
    public function getSubject() { return $this->subject; }
    public function setPlainTextBody($plainBody) { $this->txtMessage = $plainBody; }
    public function getPlainTextBody() { return $this->txtMessage; }
    public function setHtmlBody($htmlBody) { $this->htmlMessage = $htmlBody; }
    public function getHtmlBody() { return $this->htmlMessage; }

    public function render(): Message
    {
        if (empty($this->rendered)) {
            $this->eventDispatcher->dispatch(DvEmailEvent::BEFORE_RENDER_HTML, new RenderEvent($this));
            $this->renderSubject();
            $this->renderPlainTextBody();
            $this->renderHtmlBody();
            $this->eventDispatcher->dispatch(DvEmailEvent::AFTER_RENDER_HTML, new RenderEvent($this));
            $this->rendered = true;
        } else {
            throw new Exception('Message is already rendered');
        }
        return $this;
    }

    /**
     * @param string $alias
     * @return Message
     * @throws Swift_RfcComplianceException
     * @throws Swift_TransportException
     */
    public function send(string $alias = 'default'): Message
    {
        if (!$this->rendered) $this->render();
        $this->senders[$alias]->send($this);
        return $this;
    }

    protected function renderSubject()
    {
        $template = $this->getConfig()->getSubjectTemplatePath($this);
        $this->setSubject($this->getConfig()->render($template, $this->getVars()));
    }

    protected function renderPlainTextBody()
    {
        $template = $this->getConfig()->getPlainTextBodyTemplatePath($this);
        $this->setPlainTextBody($template ? $this->getConfig()->render($template, $this->getVars()) : '');
    }

    protected function renderHtmlBody()
    {
        $template = $this->getConfig()->getHtmlBodyTemplatePath($this);
        $cachedTemplate = $this->getConfig()->getCachedHtmlBodyTemplatePath($this);

        if ($cachedTemplate) {
            if (!file_exists($cachedTemplate)) {
                $content = $this->getConfig()->getTemplate($template);
                preg_match('#{% extends \"(.+)\" %}#', $content, $parentMatch);
                preg_match('#{% block body %}(.+){% endblock %}#ms', $content, $bodyMatch);
                if (!empty($parentMatch[1])) {
                    $parentContent = $this->getConfig()->getTemplate($parentMatch[1]);
                    $content = str_replace('{% block body %}{% endblock %}', $bodyMatch[1], $parentContent);
                }
                $content = $this->inlineCSS($content);
                file_put_contents($cachedTemplate, $content);
            }
            $template = $cachedTemplate;
        }
        $this->setHtmlBody($this->getConfig()->render($template, $this->getVars()));
        if (!$cachedTemplate) {
            $this->setHtmlBody($this->inlineCSS($this->getHtmlBody()));
        }
    }

    protected function inlineCSS($html)
    {
        $css = file_get_contents($this->getConfig()->getCssFile());
        $visualHtml = CssInliner::fromHtml($html)
            ->disableStyleBlocksParsing()
            ->inlineCss($css)
            ->render();
        return preg_replace_callback('#%7B%7B%20(.+?)%20%7D%7D#', function ($match) {
            return urldecode($match[0]);
        }, $visualHtml);
    }

    public function getSwiftMessage(): Swift_Message
    {
        /* @var Swift_Message $message */
        $message = Swift_DependencyContainer::getInstance()->lookup('message.message');
        $message->setId($this->getId() . '@' . $this->getConfig()->getDomain());
        $message->setTo($this->getTo());
        $message->setFrom($this->getConfig()->getFrom());
        $message->setReplyTo($this->getConfig()->getReplyTo());
        $message->setSubject($this->getSubject());
        $message->getHeaders()->addTextHeader('List-id', $this->getTemplate());
        if ($this->isBulk) {
            $message->getHeaders()->addTextHeader('Precedence', 'bulk');
        }
        if ($this->unsubscribeUrl) {
            $message->getHeaders()->addTextHeader('List-Unsubscribe', $this->unsubscribeUrl);
        }
        if ($this->getPlainTextBody()) {
            $message->addPart($this->getPlainTextBody(), 'text/plain');
        }
        if ($this->getConfig()->getEmbedImages()) {
            $this->embedImages($message);
        } else {
            $message->setBody($this->getHtmlBody(), 'text/html');
        }
        return $message;
    }

    protected function embedImages(Swift_Message $message): void
    {
        $body = $this->getHtmlBody();
        $urlPrefix = $this->getConfig()->getEmbedImages()['urlPrefix'];
        $imagesPath = $this->getConfig()->getEmbedImages()['path'];
        if (!empty($urlPrefix)) {
            $regexp = '#[\'"](' . preg_quote($urlPrefix) . '([^\'"]+\.(gif|png|jpg|jpeg)?))[\'"]#ium';
            preg_match_all($regexp, $body, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $embed = $message->embed(Swift_Image::fromPath($imagesPath . $match[2]));
                $body = str_replace($match[1], $embed, $body);
            }
        }
        $message->setBody($body, 'text/html');
    }
}