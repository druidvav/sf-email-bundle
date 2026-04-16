<?php
namespace Druidvav\DvEmailBundle;

use Druidvav\DvEmailBundle\Event\AfterRenderHtmlEvent;
use Druidvav\DvEmailBundle\Event\AfterSendEvent;
use Druidvav\DvEmailBundle\Event\BeforeRenderHtmlEvent;
use Druidvav\DvEmailBundle\Event\BeforeSendEvent;

/**
 * Имена событий = FQCN классов событий (подписка в YAML/PHP по этим строкам или через ::class).
 */
final class DvEmailEvent
{
    public const BEFORE_RENDER_HTML = BeforeRenderHtmlEvent::class;

    public const AFTER_RENDER_HTML = AfterRenderHtmlEvent::class;

    public const BEFORE_SEND = BeforeSendEvent::class;

    public const AFTER_SEND = AfterSendEvent::class;
}
