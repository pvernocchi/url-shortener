<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Mailer;
use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction / configuration
    // -------------------------------------------------------------------------

    public function testDefaultConfigValues(): void
    {
        $mailer = new Mailer([]);
        // Access via reflection to verify defaults are set
        $r = new \ReflectionObject($mailer);

        $this->assertSame('localhost', $r->getProperty('host')->getValue($mailer));
        $this->assertSame(587,         $r->getProperty('port')->getValue($mailer));
        $this->assertSame('tls',       $r->getProperty('encryption')->getValue($mailer));
        $this->assertSame('',          $r->getProperty('username')->getValue($mailer));
        $this->assertSame('',          $r->getProperty('password')->getValue($mailer));
        $this->assertSame('',          $r->getProperty('fromAddress')->getValue($mailer));
        $this->assertSame('',          $r->getProperty('fromName')->getValue($mailer));
        $this->assertFalse(            $r->getProperty('loggingEnabled')->getValue($mailer));
    }

    public function testEncryptionNormalisedToLowercase(): void
    {
        $mailer = new Mailer(['encryption' => 'TLS']);
        $r      = new \ReflectionObject($mailer);
        $this->assertSame('tls', $r->getProperty('encryption')->getValue($mailer));
    }

    public function testEncryptionSsl(): void
    {
        $mailer = new Mailer(['encryption' => 'SSL']);
        $r      = new \ReflectionObject($mailer);
        $this->assertSame('ssl', $r->getProperty('encryption')->getValue($mailer));
    }

    public function testPortCastToInt(): void
    {
        $mailer = new Mailer(['port' => '465']);
        $r      = new \ReflectionObject($mailer);
        $this->assertSame(465, $r->getProperty('port')->getValue($mailer));
    }

    public function testLoggingEnabledWithTrueString(): void
    {
        $mailer = new Mailer(['logging' => 'true']);
        $r      = new \ReflectionObject($mailer);
        $this->assertTrue($r->getProperty('loggingEnabled')->getValue($mailer));
    }

    public function testLoggingEnabledWithOne(): void
    {
        $mailer = new Mailer(['logging' => 1]);
        $r      = new \ReflectionObject($mailer);
        $this->assertTrue($r->getProperty('loggingEnabled')->getValue($mailer));
    }

    public function testLoggingDisabledWithZero(): void
    {
        $mailer = new Mailer(['logging' => 0]);
        $r      = new \ReflectionObject($mailer);
        $this->assertFalse($r->getProperty('loggingEnabled')->getValue($mailer));
    }

    // -------------------------------------------------------------------------
    // extractAddress
    // -------------------------------------------------------------------------

    public function testExtractAddressFromAngleBracket(): void
    {
        $mailer = new Mailer([]);
        $this->assertSame('user@example.com', $mailer->extractAddress('Some Name <user@example.com>'));
    }

    public function testExtractAddressPlain(): void
    {
        $mailer = new Mailer([]);
        $this->assertSame('user@example.com', $mailer->extractAddress('user@example.com'));
    }

    public function testExtractAddressTrimsWhitespace(): void
    {
        $mailer = new Mailer([]);
        $this->assertSame('user@example.com', $mailer->extractAddress('  user@example.com  '));
    }

    // -------------------------------------------------------------------------
    // encodeHeader
    // -------------------------------------------------------------------------

    public function testEncodeHeaderPureAsciiUnchanged(): void
    {
        $mailer = new Mailer([]);
        $this->assertSame('Hello World', $mailer->encodeHeader('Hello World'));
    }

    public function testEncodeHeaderNonAsciiUsesBase64(): void
    {
        $mailer = new Mailer([]);
        $result = $mailer->encodeHeader('Héllo');
        // Must start with RFC 2047 encoding marker
        $this->assertStringStartsWith('=?UTF-8?B?', $result);
        $this->assertStringEndsWith('?=', $result);
    }

    // -------------------------------------------------------------------------
    // buildMessage
    // -------------------------------------------------------------------------

    public function testBuildMessageContainsFromHeader(): void
    {
        $mailer = new Mailer([
            'from_address' => 'sender@example.com',
            'from_name'    => 'Sender Name',
        ]);

        $msg = $mailer->buildMessage('to@example.com', 'Test', 'Body');
        $this->assertStringContainsString('From: Sender Name <sender@example.com>', $msg);
    }

    public function testBuildMessageContainsToHeader(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        $msg    = $mailer->buildMessage('to@example.com', 'Test', 'Body');
        $this->assertStringContainsString('To: to@example.com', $msg);
    }

    public function testBuildMessageContainsSubjectHeader(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        $msg    = $mailer->buildMessage('to@example.com', 'Hello Subject', 'Body');
        $this->assertStringContainsString('Subject: Hello Subject', $msg);
    }

    public function testBuildMessageContainsMimeHeaders(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        $msg    = $mailer->buildMessage('to@example.com', 'Test', 'Body');
        $this->assertStringContainsString('MIME-Version: 1.0', $msg);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $msg);
    }

    public function testBuildMessageContainsBody(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        // quoted_printable_encode of 'Hello body' should still contain the original text
        $msg = $mailer->buildMessage('to@example.com', 'Test', 'Hello body');
        $this->assertStringContainsString('Hello body', $msg);
    }

    public function testBuildMessageContainsDateHeader(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        $msg    = $mailer->buildMessage('to@example.com', 'Test', 'Body');
        $this->assertStringContainsString('Date: ', $msg);
    }

    public function testBuildMessageContainsMessageId(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        $msg    = $mailer->buildMessage('to@example.com', 'Test', 'Body');
        $this->assertStringContainsString('Message-ID: <', $msg);
    }

    public function testBuildMessageIncludesExtraHeaders(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com']);
        $msg    = $mailer->buildMessage('to@example.com', 'Test', 'Body', ['X-Custom' => 'foo']);
        $this->assertStringContainsString('X-Custom: foo', $msg);
    }

    public function testBuildMessageFromNameOmittedWhenEmpty(): void
    {
        $mailer = new Mailer(['from_address' => 'sender@example.com', 'from_name' => '']);
        $msg    = $mailer->buildMessage('to@example.com', 'Test', 'Body');
        $this->assertStringContainsString('From: sender@example.com', $msg);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    public function testLoggingWritesToFile(): void
    {
        $logFile = sys_get_temp_dir() . '/smtp_test_' . uniqid() . '.log';

        $mailer = new Mailer(['logging' => true]);

        // Override the log file path via reflection
        $r = new \ReflectionObject($mailer);
        $r->getProperty('logFile')->setValue($mailer, $logFile);

        // Trigger a log entry through the private log() method
        $logMethod = $r->getMethod('log');
        $logMethod->setAccessible(true);
        $logMethod->invoke($mailer, 'TEST LOG ENTRY');

        $this->assertFileExists($logFile);
        $this->assertStringContainsString('TEST LOG ENTRY', (string)file_get_contents($logFile));

        @unlink($logFile);
    }

    public function testLoggingDisabledDoesNotWriteToFile(): void
    {
        $logFile = sys_get_temp_dir() . '/smtp_test_' . uniqid() . '.log';

        $mailer = new Mailer(['logging' => false]);

        $r = new \ReflectionObject($mailer);
        $r->getProperty('logFile')->setValue($mailer, $logFile);

        $logMethod = $r->getMethod('log');
        $logMethod->setAccessible(true);
        $logMethod->invoke($mailer, 'SHOULD NOT APPEAR');

        $this->assertFileDoesNotExist($logFile);
    }
}
