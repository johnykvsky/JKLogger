<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use johnykvsky\Utils\JKLogger;
use Psr\Log\LogLevel;

class LoggerTest extends TestCase
{
    private $logPath;

    private $logger;
    private $errLogger;

    protected function setUp(): void
    {
        $this->logPath = __DIR__.'/logs';
        $this->logger = new JKLogger($this->logPath, LogLevel::DEBUG, array ('flushFrequency' => 1));
        $this->errLogger = new JKLogger($this->logPath, LogLevel::ERROR, array (
            'extension' => 'log',
            'prefix' => 'error_',
            'flushFrequency' => 1
        ));
    }

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
        $this->assertStringEndsWith($this->logPath.'/log_'.date('Y-m-d').'.txt', $this->logger->getLogFilePath());
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

        $this->assertTrue(file_exists($this->errLogger->getLogFilePath()));
        $this->assertTrue(file_exists($this->logger->getLogFilePath()));

        $this->assertLastLineEquals($this->logger);
        $this->assertLastLineEquals($this->errLogger);
    }

    public function testLogLevelThreshold()
    {
        $this->logger->setLogLevelThreshold(LogLevel::ERROR);
        $this->assertEquals($this->logger->getLogLevelThreshold(), LogLevel::ERROR);
    }


    public function assertLastLineEquals(JKLogger $logr)
    {
        $this->assertEquals($logr->getLastLogLine(), $this->getLastLine($logr->getLogFilePath()));
    }

    public function assertLastLineNotEquals(JKLogger $logr)
    {
        $this->assertNotEquals($logr->getLastLogLine(), $this->getLastLine($logr->getLogFilePath()));
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

    protected function tearDown(): void
    {
        #@unlink($this->logger->getLogFilePath());
        #@unlink($this->errLogger->getLogFilePath());
    }
}
