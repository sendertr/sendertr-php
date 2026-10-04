# sendertr-php

Resmî SenderTR PHP istemcisi. Bağımlılık yok.

```php
require 'vendor/autoload.php';
$st = new SenderTR\SenderTR('str_...');

$r = $st->send([
    'to' => 'ali@ornek.com', 'subject' => 'Siparişiniz alındı', 'html' => '<p>Teşekkürler</p>',
    'priority' => 'critical', 'expires_in' => 900,
], idempotencyKey: 'order-48215');          // aynı anahtarla tekrar = aynı yanıt, ikinci ileti yok
echo $r['id'];                               // msg_01J…

$st->getMessage($r['id']);                   // durum + olay zaman çizelgesi
$st->sendBatch([...], ['from_email' => 'bildirim@ornek.com']);
$st->createTemplate(['key' => 'order-created', 'name' => 'Sipariş', 'subject' => 'Sipariş {{no}}', 'html' => '<p>{{no}}</p>']);
$st->send(['to' => 'ali@ornek.com', 'template' => 'order-created', 'variables' => ['no' => '48215']]);
```

Hatalar `SenderTR\SenderTRException` ile gelir: `->errorCode` (ör. `insufficient_credit`), `->getCode()` HTTP durumu, `->requestId`.
Laravel'de `Mail` sürücüsü olarak kullanmak için SMTP relay (smtp.sendertr.com:587) yeterlidir; API için bu sınıfı bir servis olarak bağlayın.
Yayın: Packagist'e `sendertr/sendertr-php` adıyla (hesap sahibi yayınlar).
