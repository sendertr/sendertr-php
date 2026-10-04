<?php

namespace SenderTR;

/**
 * SenderTR resmî PHP istemcisi (4 Eki 2026). Bağımlılık yok (curl + json).
 *
 *   $st = new SenderTR('str_...');
 *   $r  = $st->send(['to' => 'ali@ornek.com', 'subject' => 'Sipariş', 'html' => '<p>…</p>', 'priority' => 'critical']);
 *   $r['id'] // msg_…
 */
class SenderTR
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.sendertr.com/v1',
        private readonly int $timeout = 30,
    ) {}

    /** @param array<string,mixed> $message  to, subject, html|text|template, variables, priority, expires_in, send_at, tags, metadata … */
    public function send(array $message, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/send', $message, $idempotencyKey);
    }

    /** @param list<array<string,mixed>> $messages  en çok 500 */
    public function sendBatch(array $messages, array $defaults = [], ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/send/batch', ['messages' => $messages, 'defaults' => $defaults], $idempotencyKey);
    }

    public function getMessage(string $idOrMessageId): array
    {
        return $this->request('GET', '/messages/'.rawurlencode($idOrMessageId));
    }

    public function cancelMessage(string $idOrMessageId): array
    {
        return $this->request('DELETE', '/messages/'.rawurlencode($idOrMessageId));
    }

    public function listMessages(array $filters = []): array
    {
        return $this->request('GET', '/messages'.($filters ? '?'.http_build_query($filters) : ''));
    }

    public function verify(string $email): array
    {
        return $this->request('POST', '/verify', ['email' => $email]);
    }

    public function templates(): array
    {
        return $this->request('GET', '/templates');
    }

    public function createTemplate(array $template): array
    {
        return $this->request('POST', '/templates', $template);
    }

    public function createContact(array $contact): array
    {
        return $this->request('POST', '/contacts', $contact);
    }

    public function me(): array
    {
        return $this->request('GET', '/me');
    }

    /** @return array<string,mixed> yanıt gövdesi + '_status' (HTTP) + '_request_id' */
    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        $ch = curl_init($this->baseUrl.$path);
        $headers = ['X-Api-Key: '.$this->apiKey, 'Accept: application/json', 'Content-Type: application/json', 'User-Agent: sendertr-php/'.self::VERSION];
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: '.$idempotencyKey;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POSTFIELDS => $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new SenderTRException('Bağlantı hatası: '.$err, 0, 'connection_error');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $headerSize);
        $data = json_decode(substr($raw, $headerSize), true) ?: [];
        $reqId = preg_match('/^X-Request-Id:\s*(\S+)/mi', $head, $m) ? $m[1] : null;
        if ($status >= 400) {
            throw new SenderTRException((string) ($data['message'] ?? 'HTTP '.$status), $status, (string) ($data['error'] ?? 'http_error'), $data, $reqId);
        }

        return $data + ['_status' => $status, '_request_id' => $reqId];
    }
}

class SenderTRException extends \RuntimeException
{
    public function __construct(string $message, int $status, public readonly string $errorCode, public readonly array $body = [], public readonly ?string $requestId = null)
    {
        parent::__construct($message, $status);
    }
}
