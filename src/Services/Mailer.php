<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Native-PHP SMTP mailer.
 *
 * Supports plain-text, STARTTLS (encryption = 'tls'), and direct SSL/TLS
 * (encryption = 'ssl') connections, with AUTH LOGIN authentication.
 *
 * When SMTP logging is enabled every line sent to and received from the
 * server is written to storage/logs/smtp.log for troubleshooting.
 */
class Mailer
{
    private string $host;
    private int    $port;
    /** @var 'none'|'tls'|'ssl' */
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private bool   $loggingEnabled;
    private string $logFile;

    /** @var resource|null */
    private $socket = null;

    /**
     * @param array{
     *   host?:         string,
     *   port?:         int|string,
     *   encryption?:   string,
     *   username?:     string,
     *   password?:     string,
     *   from_address?: string,
     *   from_name?:    string,
     *   logging?:      bool|int|string,
     * } $config
     */
    public function __construct(array $config)
    {
        $this->host           = trim((string)($config['host']         ?? 'localhost'));
        $this->port           = (int)($config['port']                 ?? 587);
        $this->encryption     = strtolower(trim((string)($config['encryption'] ?? 'tls')));
        $this->username       = (string)($config['username']          ?? '');
        $this->password       = (string)($config['password']          ?? '');
        $this->fromAddress    = trim((string)($config['from_address'] ?? ''));
        $this->fromName       = trim((string)($config['from_name']    ?? ''));
        $this->loggingEnabled = filter_var($config['logging'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->logFile        = defined('ROOT_PATH')
            ? ROOT_PATH . '/storage/logs/smtp.log'
            : sys_get_temp_dir() . '/smtp.log';
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Send a plain-text e-mail.
     *
     * @param string   $to      Recipient address (or "Name <addr>")
     * @param string   $subject Subject line
     * @param string   $body    Plain-text body
     * @param string[] $headers Additional RFC 5322 headers (key: value)
     *
     * @throws \RuntimeException on any SMTP error
     */
    public function send(string $to, string $subject, string $body, array $headers = []): void
    {
        $this->connect();

        try {
            $this->authenticate();
            $this->sendMail($to, $subject, $body, $headers);
        } finally {
            $this->quit();
        }
    }

    /**
     * Send a pre-defined test message to verify SMTP settings.
     *
     * @throws \RuntimeException on any SMTP error
     */
    public function sendTest(string $to): void
    {
        $subject = 'Test Email – URL Shortener';
        $body    = "This is a test e-mail sent from your URL Shortener application.\r\n\r\n"
                 . "If you received this message your SMTP settings are configured correctly.\r\n\r\n"
                 . 'Sent at: ' . date('Y-m-d H:i:s T');

        $this->send($to, $subject, $body);
    }

    // -------------------------------------------------------------------------
    // SMTP connection & handshake
    // -------------------------------------------------------------------------

    /** @throws \RuntimeException */
    private function connect(): void
    {
        $address = $this->buildAddress();
        $timeout = 15;
        $errCode = 0;
        $errMsg  = '';

        $this->log("Connecting to {$address} …");

        $socket = stream_socket_client($address, $errCode, $errMsg, $timeout);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connection failed: [{$errCode}] {$errMsg}");
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $timeout);

        // Read server greeting
        $this->expect(220);

        // Send EHLO
        $capabilities = $this->ehlo();

        // Upgrade to TLS with STARTTLS if requested
        if ($this->encryption === 'tls') {
            if (!in_array('STARTTLS', $capabilities, true)) {
                throw new \RuntimeException('SMTP server does not advertise STARTTLS.');
            }
            $this->sendCommand('STARTTLS');
            $this->expect(220);

            // Upgrade the stream to TLS
            $upgraded = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($upgraded !== true) {
                throw new \RuntimeException('STARTTLS negotiation failed.');
            }

            // Re-send EHLO after upgrade (RFC 3207)
            $this->ehlo();
        }
    }

    /**
     * Send EHLO and return the server's advertised capability list.
     *
     * @return string[]
     */
    private function ehlo(): array
    {
        $hostname = gethostname() ?: 'localhost';
        $this->sendCommand("EHLO {$hostname}");
        $lines = $this->readMultiLine(250);

        // Parse capability keywords (second word on each line after "250-"/"250 ")
        $capabilities = [];
        foreach ($lines as $line) {
            $parts = explode(' ', trim(substr($line, 4)), 2);
            if (isset($parts[0]) && $parts[0] !== '') {
                $capabilities[] = strtoupper($parts[0]);
            }
        }
        return $capabilities;
    }

    /** @throws \RuntimeException */
    private function authenticate(): void
    {
        if ($this->username === '') {
            return;
        }

        $this->sendCommand('AUTH LOGIN');
        $this->expect(334);

        // Send credentials via a dedicated helper so they are always redacted in logs
        $this->sendSensitive(base64_encode($this->username));
        $this->expect(334);

        $this->sendSensitive(base64_encode($this->password));
        $this->expect(235);
    }

    // -------------------------------------------------------------------------
    // SMTP message transmission
    // -------------------------------------------------------------------------

    /** @throws \RuntimeException */
    private function sendMail(string $to, string $subject, string $body, array $headers): void
    {
        $fromAddr = $this->fromAddress;
        $toAddr   = $this->extractAddress($to);

        $this->sendCommand("MAIL FROM:<{$fromAddr}>");
        $this->expect(250);

        $this->sendCommand("RCPT TO:<{$toAddr}>");
        $this->expect(250);

        $this->sendCommand('DATA');
        $this->expect(354);

        $message = $this->buildMessage($to, $subject, $body, $headers);
        $this->writeLine($message);
        $this->writeLine('.');
        $this->expect(250);
    }

    /**
     * Build the full RFC 5322 message (headers + body).
     */
    public function buildMessage(string $to, string $subject, string $body, array $extraHeaders = []): string
    {
        $from = $this->fromName !== ''
            ? $this->encodeHeader($this->fromName) . " <{$this->fromAddress}>"
            : $this->fromAddress;

        $date    = date('r');
        $msgId   = '<' . uniqid('', true) . '@' . (gethostname() ?: 'localhost') . '>';

        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$from}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: " . $this->encodeHeader($subject) . "\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";

        foreach ($extraHeaders as $key => $value) {
            $headers .= "{$key}: {$value}\r\n";
        }

        // Encode body first, then apply dot-stuffing so that any '.' introduced
        // by quoted-printable encoding at the start of a line is also escaped.
        $encoded  = quoted_printable_encode($body);
        $safeBody = preg_replace('/^\./', '..', $encoded);
        $safeBody = preg_replace('/\r\n\./', "\r\n..", (string)$safeBody);

        return $headers . "\r\n" . (string)$safeBody;
    }

    private function quit(): void
    {
        if ($this->socket === null) {
            return;
        }
        try {
            $this->sendCommand('QUIT');
        } catch (\Throwable) {
            // Ignore errors on QUIT
        }
        fclose($this->socket);
        $this->socket = null;
    }

    // -------------------------------------------------------------------------
    // Low-level socket helpers
    // -------------------------------------------------------------------------

    private function buildAddress(): string
    {
        return match ($this->encryption) {
            'ssl'   => "ssl://{$this->host}:{$this->port}",
            default => "tcp://{$this->host}:{$this->port}",
        };
    }

    /** @throws \RuntimeException */
    private function sendCommand(string $command): void
    {
        $this->log("C: {$command}");
        $this->writeLine($command);
    }

    /**
     * Send a credential value (base64-encoded username or password) and always
     * redact it in the SMTP log regardless of content.
     *
     * @throws \RuntimeException
     */
    private function sendSensitive(string $value): void
    {
        $this->log('C: ***REDACTED***');
        $this->writeLine($value);
    }

    private function writeLine(string $data): void
    {
        if ($this->socket === null) {
            throw new \RuntimeException('SMTP socket is not connected.');
        }
        fwrite($this->socket, $data . "\r\n");
    }

    /**
     * Read a single response line and validate the expected code.
     *
     * @throws \RuntimeException
     */
    private function expect(int $expectedCode): string
    {
        $response = $this->readLine();
        $code     = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException(
                "SMTP error: expected {$expectedCode}, got {$code}. Response: {$response}"
            );
        }
        return $response;
    }

    /**
     * Read a potentially multi-line SMTP response (e.g. EHLO capabilities).
     * Returns all lines.
     *
     * @return string[]
     * @throws \RuntimeException
     */
    private function readMultiLine(int $expectedCode): array
    {
        $lines = [];
        do {
            $line    = $this->readLine();
            $lines[] = $line;
            $code    = (int)substr($line, 0, 3);
            if ($code !== $expectedCode) {
                throw new \RuntimeException(
                    "SMTP error: expected {$expectedCode}, got {$code}. Response: {$line}"
                );
            }
            // A space after the code means this is the final line (RFC 5321)
            $isFinal = isset($line[3]) && $line[3] === ' ';
        } while (!$isFinal);

        return $lines;
    }

    private function readLine(): string
    {
        if ($this->socket === null) {
            throw new \RuntimeException('SMTP socket is not connected.');
        }
        $line = (string)fgets($this->socket, 512);
        $this->log("S: " . rtrim($line));
        return rtrim($line, "\r\n");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Extract a bare e-mail address from a "Name <addr>" or plain "addr" string.
     */
    public function extractAddress(string $address): string
    {
        if (preg_match('/<([^>]+)>/', $address, $m)) {
            return trim($m[1]);
        }
        return trim($address);
    }

    /**
     * RFC 2047 Q-encoding for non-ASCII header values.
     */
    public function encodeHeader(string $value): string
    {
        if (mb_detect_encoding($value, 'ASCII', true) !== false) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    private function log(string $message): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    // -------------------------------------------------------------------------
    // Factory / static helper
    // -------------------------------------------------------------------------

    /**
     * Create a Mailer instance from DB settings (via Setting model).
     */
    public static function fromSettings(\App\Models\Setting $settings): self
    {
        return new self([
            'host'         => $settings->get('smtp_host',         'localhost'),
            'port'         => (int)$settings->get('smtp_port',    587),
            'encryption'   => $settings->get('smtp_encryption',   'tls'),
            'username'     => $settings->get('smtp_username',     ''),
            'password'     => $settings->get('smtp_password',     ''),
            'from_address' => $settings->get('smtp_from_address', ''),
            'from_name'    => $settings->get('smtp_from_name',    ''),
            'logging'      => (bool)(int)$settings->get('smtp_logging', '0'),
        ]);
    }
}
