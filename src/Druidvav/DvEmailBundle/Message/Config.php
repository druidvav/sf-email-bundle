<?php /** @noinspection PhpUnused */
namespace Druidvav\DvEmailBundle\Message;

use InvalidArgumentException;
use Druidvav\DvEmailBundle\Exception\TemplateNotFoundException;
use Twig\Environment;

class Config
{
    protected Environment $twig;

    protected $cachePath;
    protected $templatePath;
    protected $cssFile;
    protected $cacheInlinedCSS = false;
    protected $domain;
    protected $from;
    protected $replyTo;
    protected $embedImages = false;

    public function setTwig(Environment $twig) { $this->twig = $twig; }
    public function setTemplatePath($templatePath) { $this->templatePath = $templatePath; }
    public function setCacheInlinedCSS($value) { $this->cacheInlinedCSS = $value; }
    public function setCachePath($cachePath) { $this->cachePath = $cachePath; }
    public function setCssFile($value) { $this->cssFile = $value; }
    public function getCssFile() { return $this->cssFile; }
    public function setFrom($val) { $this->from = $val; }
    public function getFrom() { return $this->from; }
    public function setReplyTo($val) { $this->replyTo = $val; }
    public function getReplyTo() { return $this->replyTo; }
    public function setDomain($val) { $this->domain = $val; }
    public function getDomain() { return $this->domain; }
    public function setEmbedImages($urlPrefix, $path)
    {
        $this->embedImages = [ 'urlPrefix' => $urlPrefix, 'path' => $path ];
    }

    public function getEmbedImages() { return $this->embedImages; }

    public function getSubjectTemplatePath(Message $message): string
    {
        return $this->templatePath . '/' . $message->getTemplate() . '/subject.txt.twig';
    }

    public function getPlainTextBodyTemplatePath(Message $message): ?string
    {
        $template = $this->templatePath . '/' . $message->getTemplate() . '/email.txt.twig';
        return $this->twig->getLoader()->exists($template) ? $template : null;
    }

    public function getHtmlBodyTemplatePath(Message $message): string
    {
        return $this->templatePath . '/' . $message->getTemplate() . '/email.html.twig';
    }

    public function getCachedHtmlBodyTemplatePath(Message $message): ?string
    {
        if ($this->cacheInlinedCSS && $this->cssFile) {
            $realPath = realpath($this->cachePath);
            return $this->cachePath . '/' . md5($realPath . ':' . $message->getTemplate()) . '.html.twig';
        } else {
            return null;
        }
    }

    public function getTemplate($template): string
    {
        return $this->twig->getLoader()->getSourceContext($template)->getCode();
    }

    /**
     * @throws TemplateNotFoundException
     */
    public function render($template, $vars): string
    {
        try {
            if (file_exists($template)) {
                return $this->twig->createTemplate(file_get_contents($template))->render($vars);
            }
            return $this->twig->render($template, $vars);
        } catch (InvalidArgumentException $e) {
            throw new TemplateNotFoundException($template, 0, $e);
        }
    }
}