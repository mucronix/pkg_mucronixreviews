<?php

/**
 * A browser of the stand: cookies of its own, forms with their token.
 */
final class Client
{
    private array $cookies = [];
    public string $token = '';
    public int $status = 0;
    public string $body = '';
    public string $location = '';

    public function __construct(private string $base = 'http://127.0.0.1:8097/')
    {
    }

    public function handle(string $method, string $path, array|string $fields = [], array $headers = []): CurlHandle
    {
        $curl = curl_init(str_starts_with($path, 'http') ? $path : $this->base . $path);
        $cookie = implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge(['Expect:'], $headers), CURLOPT_COOKIE => $cookie,
        ]);

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, \is_array($fields) ? http_build_query($fields) : $fields);
        }

        return $curl;
    }

    public function take(CurlHandle $curl, string $raw): string
    {
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $size);
        $this->status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $this->body = substr($raw, $size);
        $this->location = preg_match('~^Location: (.*)$~mi', $head, $m) ? trim($m[1]) : '';
        preg_match_all('~^Set-Cookie: ([^=]+)=([^;\r\n]*)~mi', $head, $all, PREG_SET_ORDER);

        foreach ($all as [, $name, $value]) {
            $this->cookies[$name] = $value;
        }

        return $this->body;
    }

    public function request(string $method, string $path, array|string $fields = [], array $headers = []): string
    {
        $curl = $this->handle($method, $path, $fields, $headers);

        return $this->take($curl, (string) curl_exec($curl));
    }

    /** Sets a cookie, as if a page had set it */
    public function cookie(string $name, string $value): self
    {
        $this->cookies[$name] = $value;

        return $this;
    }

    public function get(string $path): string
    {
        $body = $this->request('GET', $path);

        // Joomla leads from index.php?option=... to the short address
        for ($i = 0; $i < 3 && \in_array($this->status, [301, 302, 303], true); $i++) {
            $body = $this->request('GET', $this->location);
        }

        if (preg_match('~"csrf\.token":"([a-f0-9]{32})"~', $body, $m)) {
            $this->token = $m[1];
        }

        return $body;
    }

    public function login(string $username, string $password = 'stand-only-pass-1'): self
    {
        $this->get('index.php?option=com_users&view=login');
        $this->request('POST', 'index.php?option=com_users&task=user.login', ['username' => $username, 'password' => $password, $this->token => 1]);
        $this->get('index.php?option=com_users&view=profile');

        if ($this->status !== 200) {
            throw new RuntimeException("$username could not log in");
        }

        return $this;
    }

    public function loginAdmin(string $username = 'standadmin'): self
    {
        $this->get('administrator/index.php');
        $this->request('POST', 'administrator/index.php', ['username' => $username, 'passwd' => 'stand-only-pass-1', 'option' => 'com_login', 'task' => 'login', 'return' => base64_encode('index.php'), $this->token => 1]);
        $body = $this->get('administrator/index.php');

        if (!str_contains($body, 'com_cpanel') && !str_contains($body, 'logout')) {
            throw new RuntimeException('administrator could not log in');
        }

        return $this;
    }

    /** Sends a review as a script does */
    public function review(array $fields, ?array $headers = null): array
    {
        $this->request('POST', 'index.php?option=com_mucronixreviews&task=review.save&format=json', $fields + ['context' => 'com_test.item', 'item_id' => 1, 'rating' => 5], $headers ?? ['X-CSRF-Token: ' . $this->token]);

        return [$this->status, json_decode($this->body, true)];
    }
}
