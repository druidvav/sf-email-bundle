# DvEmailBundle

Бандл для Symfony 4: транзакционные письма с рендером Twig, инлайном CSS (Emogrifier), опциональным встраиванием картинок и событиями жизненного цикла. Отправка через **Symfony Mailer** (`symfony/mailer`).

## Требования

- PHP ≥ 7.4  
- Symfony 4.4 (`symfony/framework-bundle`, `symfony/twig-bundle`, `symfony/event-dispatcher`, `symfony/translation`)  
- `symfony/mailer` (подтягивает `symfony/mime`)

## Установка

```bash
composer require druidvav/email-bundle
```

Подключите бандл в `config/bundles.php`:

```php
Druidvav\DvEmailBundle\DvEmailBundle::class => ['all' => true],
```

В приложении должны быть включены **FrameworkBundle**, **TwigBundle**, настроен **Symfony Mailer** (`framework.mailer`) и объявлен хотя бы один сервис `MailerInterface` (обычно `mailer`).

## Возможности

- **Шаблоны**: subject, plain text и HTML из Twig; путь к шаблонам задаётся в конфиге (`template_path`).
- **CSS в HTML**: внешний файл стилей, инлайн через Pelago Emogrifier; опциональное кеширование предобработанного HTML в `kernel.cache_dir`.
- **Встраивание изображений**: подмена URL на `cid:` при включённой секции `embed_images`.
- **События** — отдельные классы в `Druidvav\DvEmailBundle\Event\`:  
  `BeforeRenderHtmlEvent`, `AfterRenderHtmlEvent`, `BeforeSendEvent`, `AfterSendEvent`.  
  Подписка по FQCN (или константы в `DvEmailEvent`, равные этим классам). Диспатч: один аргумент `dispatch($event)`.
- **Локали**: при наличии секции `locale:` слушатель подменяет `RequestContext`, локаль переводчика и (опционально) Gedmo Translatable на время рендера письма, затем восстанавливает состояние.
- **Несколько профилей `sender`**: разные алиасы (например `default` / `subscribe`) — разные сервисы `MailerInterface`, если в приложении заведено несколько mailer’ов. **Запасной SMTP** настраивается в Symfony Mailer (цепочка транспортов, `failover()` в DSN и т.д.), а не отдельным `mailer_fallback` в бандле.
- **Реестр сообщений**: сервис `dv_email.locator` (`Symfony\Component\DependencyInjection\ServiceLocator`) по алиасам из `message`.

## Конфигурация

Корневой ключ: `dv_email`. Структура: `message`, `sender`, опционально `locale`.

Минимальный пример см. в [`example.yml`](example.yml). В `sender` укажите ключ **`mailer`**: id сервиса `Symfony\Component\Mailer\MailerInterface` (часто `mailer`). Несколько SMTP с отказоустойчивостью задайте в **`MAILER_DSN`** / `framework.mailer` (например `failover(...)`), см. [документацию Mailer](https://symfony.com/doc/4.4/mailer.html).

## Использование в коде

Через контейнер получите `dv_email.message` (алиас на сообщение с алиасом `default`) или `dv_email.locator` для выбора по имени. Сообщение: `Druidvav\DvEmailBundle\Message\Message` — вызовите `setTo()`, `setTemplate()`, при необходимости `setLocale()`, затем `render()` и `send()`, либо сразу `send()` (рендер выполнится сам).

Трейт `DvEmailAwareTrait` ожидает внедрения локатора сообщений (например, `dv_email.locator` как `Psr\Container\ContainerInterface`) — настройте вызов `setDvEmailLocator` в вашем `services.yaml`, если используете трейт.

## Документация по обновлению

Переход с версии на SwiftMailer описан в [`MIGRATION.md`](MIGRATION.md).
