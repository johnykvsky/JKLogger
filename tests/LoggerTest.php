<?php

declare(strict_types=1);

use johnykvsky\Utils\JKLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LoggerTest extends TestCase
{
    private $logPath;

    private $logger;
    private $errLogger;

    public function testImplementsPsr3LoggerInterface()
    {
        $this->assertInstanceOf('Psr\Log\LoggerInterface', $this->logger);
    }

    public function testAcceptsExtension()
    {
        $this->assertStringEndsWith('.log', $this->errLogger->getLogFilePath());
    }

    public function testSetLogPath()
    {
        $this->logger->getLogFilePath($this->logPath);
        $this->assertStringEndsWith($this->logPath . '/log_' . date('Y-m-d') . '.txt', $this->logger->getLogFilePath());
    }

    public function testAcceptsPrefix()
    {
        $filename = basename($this->errLogger->getLogFilePath());
        $this->assertStringStartsWith('error_', $filename);
    }

    public function testWritesBasicLogs()
    {
        $this->logger->log(LogLevel::DEBUG, 'This is a test');
        $this->errLogger->log(LogLevel::ERROR, 'This is a test');

        $this->assertFileExists($this->errLogger->getLogFilePath());
        $this->assertFileExists($this->logger->getLogFilePath());

        $this->assertLastLineEquals($this->logger);
        $this->assertLastLineEquals($this->errLogger);
    }

    public function assertLastLineEquals(JKLogger $logr)
    {
        $this->assertEquals($logr->getLastLogLine(), $this->getLastLine($logr->getLogFilePath()));
    }

    private function getLastLine($filename)
    {
        $size = filesize($filename);
        $fp = fopen($filename, 'r');
        $pos = -2; // start from second to last char
        $t = ' ';

        while ($t != "\n") {
            fseek($fp, $pos, SEEK_END);
            $t = fgetc($fp);
            $pos = $pos - 1;
            if ($size + $pos < -1) {
                rewind($fp);
                break;
            }
        }

        $t = fgets($fp);
        fclose($fp);

        return trim($t);
    }

    public function testLogLevelThreshold()
    {
        $this->logger->setLogLevelThreshold(LogLevel::ERROR);
        $this->assertEquals(LogLevel::ERROR, $this->logger->getLogLevelThreshold());
    }

    public function assertLastLineNotEquals(JKLogger $logr)
    {
        $this->assertNotEquals($logr->getLastLogLine(), $this->getLastLine($logr->getLogFilePath()));
    }

    protected function setUp(): void
    {
        $this->logPath = __DIR__ . '/logs';
        $this->logger = new JKLogger($this->logPath, LogLevel::DEBUG, array('flushFrequency' => 1));
        $this->errLogger = new JKLogger(
            $this->logPath, LogLevel::ERROR, array(
            'extension' => 'log',
            'prefix' => 'error_',
            'flushFrequency' => 1,
        )
        );
    }

    protected function tearDown(): void
    {
        #@unlink($this->logger->getLogFilePath());
        #@unlink($this->errLogger->getLogFilePath());
    }
}
