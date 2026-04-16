# Миграция с SwiftMailer на Symfony Mailer

Этот документ описывает переход приложения с предыдущей версии бандла (SwiftMailer + `symfony/swiftmailer-bundle`) на текущую (Symfony Mailer).

## 1. Зависимости Composer

**Удалить:**

- `symfony/swiftmailer-bundle` (и транзитивно `swiftmailer/swiftmailer`).

**Добавить:**

- `symfony/mailer` `^4.4` (вместе с ним обычно ставится `symfony/mime`).

Выполните `composer update` и убедитесь, что конфликтов версий с остальными пакетами Symfony 4.4 нет.

## 2. Конфигурация приложения

- Отключите или удалите конфигурацию **SwiftMailer** (`swiftmailer` в `config/packages`).
- Включите **Symfony Mailer** согласно [документации Symfony 4.4](https://symfony.com/doc/4.4/mailer.html): `framework.mailer`, переменные окружения `MAILER_DSN` (или несколько транспортов).

Сервис **`mailer`** (`MailerInterface`) обычно появляется после настройки `framework.mailer`. Резервные SMTP **не выносятся во второй сервис бандла**: используйте цепочку транспортов Symfony Mailer (`failover` и др. в DSN).

## 3. Конфигурация `dv_email`

Корневой ключ YAML и префикс сервисов: **`dv_email`** (если у вас было **`rage_email`** — переименуйте ключ и все ссылки на сервисы: `dv_email.message`, `dv_email.locator`, `dv_email.<alias>.*`, …).

В секции `sender` замените ссылки на старые сервисы SwiftMailer и **уберите `mailer_fallback`** — один ключ **`mailer`** на профиль отправителя:

| Было (пример)                 | Стало (пример) |
|------------------------------|----------------|
| `swiftmailer.mailer.primary` | `mailer`       |
| `mailer_fallback: ...`       | удалить; failover в `MAILER_DSN` / Mailer |

Имя сервиса в `mailer` должно совпадать с объявленным в приложении `MailerInterface`.

## 4. Изменения в коде (breaking)

- **`Sender`**: вместо `setPrimaryMailer` / `setFallbackMailer` используется только **`setMailer(MailerInterface)`**; опция конфига **`mailer_fallback`** удалена — перенесите отказоустойчивость в DSN Symfony Mailer.
- **`Message::getSwiftMessage()`** удалён. Используйте **`Message::getEmail()`**, возвращающий `Symfony\Component\Mime\Email`.
- Отправка идёт через **`Symfony\Component\Mailer\MailerInterface::send()`**; исключения транспорта — **`Symfony\Component\Mailer\Exception\TransportException`** (и связанные классы из компонента Mailer), а не `Swift_TransportException`.
- События: вместо **`RenderEvent`** / **`SendEvent`** и строк вида `rage_email.before_send` — четыре класса **`BeforeRenderHtmlEvent`**, **`AfterRenderHtmlEvent`**, **`BeforeSendEvent`**, **`AfterSendEvent`** (в каждом только **`Message`**). Подписки: FQCN класса или `DvEmailEvent::BEFORE_SEND` и т.д. (константы = FQCN).

## 5. SMTP: round-robin и балансировка

Встроенный в старый бандл кастомный Swift-транспорт (случайный хост, Exim id из ответа) **не переносится**. Распределение нагрузки между несколькими SMTP рекомендуется настраивать в приложении: несколько транспортов Mailer, round-robin/failover на уровне DSN или балансировщик перед SMTP.

## 6. Проверка после апгрейда

- Отправка plain + HTML, заголовки (в т.ч. bulk / List-Unsubscribe при использовании).
- Встраивание картинок (`embed_images`); при необходимости — отказоустойчивость SMTP через DSN Mailer (`failover` и т.д.).
- События рендера и отправки (подписка по FQCN классов событий).
- **Локали**: для разных `Message::setLocale()` и ключей в `dv_email.locale` — корректные переводы и URL в шаблонах; после рендера глобальная локаль переводчика восстанавливается.
