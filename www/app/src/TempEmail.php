<?php

declare(strict_types=1);

final class TempEmail
{
    public string $address;
    public string $login;
    public string $domain;
    public string $provider = 'mailtm';
    private string $token = '';
    private string $password = '';

    public function __construct(?string $address = null)
    {
        if ($address) {
            $this->address = $address;
            [$this->login, $this->domain] = explode('@', $address, 2);
        } else {
            $this->createMailbox();
        }
    }

    private function createMailbox(): void
    {
        $errors = [];
        foreach (['mailtm', '1secmail'] as $provider) {
            try {
                if ($provider === 'mailtm') {
                    $this->createMailtm();
                } else {
                    $this->create1SecMail();
                }
                $this->provider = $provider;
                return;
            } catch (Throwable $e) {
                $errors[] = "$provider: " . $e->getMessage();
            }
        }
        throw new RuntimeException('All temp email providers failed — ' . implode('; ', $errors));
    }

    private function createMailtm(): void
    {
        $domains = $this->httpJson('GET', 'https://api.mail.tm/domains');
        $list = $domains['hydra:member'] ?? (array_is_list($domains) ? $domains : []);
        $active = array_values(array_filter($list, fn($d) => !empty($d['isActive'])));
        if (!$active) {
            throw new RuntimeException('No mail.tm domains');
        }
        $pick = $active[array_rand($active)];
        $this->domain = $pick['domain'];
        $this->login = 'ig' . bin2hex(random_bytes(5));
        $this->address = $this->login . '@' . $this->domain;
        $this->password = bin2hex(random_bytes(8));
        $this->httpJson('POST', 'https://api.mail.tm/accounts', [
            'address' => $this->address,
            'password' => $this->password,
        ]);
        $token = $this->httpJson('POST', 'https://api.mail.tm/token', [
            'address' => $this->address,
            'password' => $this->password,
        ]);
        $this->token = $token['token'] ?? '';
    }

    private function create1SecMail(): void
    {
        $data = $this->httpJson(
            'GET',
            'https://www.1secmail.com/api/v1/?action=genRandomMailbox&count=1',
            null,
            ['User-Agent: Mozilla/5.0']
        );
        if (empty($data[0])) {
            throw new RuntimeException('1secmail failed');
        }
        $this->address = $data[0];
        [$this->login, $this->domain] = explode('@', $this->address, 2);
    }

    public function waitForCode(int $timeout = 120, int $poll = 5): string
    {
        $seen = [];
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            foreach ($this->getMessages() as $msg) {
                $id = (string) ($msg['id'] ?? '');
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $body = $this->readMessageBody($msg);
                if (preg_match('/\b(\d{6})\b/', $body, $m)) {
                    return $m[1];
                }
            }
            sleep($poll);
        }
        throw new RuntimeException("Verification code not received within {$timeout}s");
    }

    private function getMessages(): array
    {
        if ($this->provider === 'mailtm') {
            $data = $this->httpJson('GET', 'https://api.mail.tm/messages', null, [
                'Authorization: Bearer ' . $this->token,
            ]);
            return $data['hydra:member'] ?? (array_is_list($data) ? $data : []);
        }
        return $this->httpJson(
            'GET',
            "https://www.1secmail.com/api/v1/?action=getMessages&login={$this->login}&domain={$this->domain}",
            null,
            ['User-Agent: Mozilla/5.0']
        );
    }

    private function readMessageBody(array $msg): string
    {
        if ($this->provider === 'mailtm') {
            $id = $msg['id'] ?? '';
            $full = $this->httpJson('GET', "https://api.mail.tm/messages/$id", null, [
                'Authorization: Bearer ' . $this->token,
            ]);
            return ($msg['subject'] ?? '') . ' ' . ($msg['intro'] ?? '') . ' ' . ($full['text'] ?? '') . ' ' . ($full['html'] ?? '');
        }
        $id = $msg['id'] ?? '';
        $full = $this->httpJson(
            'GET',
            "https://www.1secmail.com/api/v1/?action=readMessage&login={$this->login}&domain={$this->domain}&id=$id",
            null,
            ['User-Agent: Mozilla/5.0']
        );
        return ($full['subject'] ?? '') . ' ' . ($full['textBody'] ?? '') . ' ' . ($full['htmlBody'] ?? '');
    }

    private function httpJson(string $method, string $url, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? []);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            throw new RuntimeException('HTTP error: ' . curl_error($ch));
        }
        curl_close($ch);
        if ($code >= 400) {
            throw new RuntimeException("HTTP $code for $url");
        }
        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
    }
}
