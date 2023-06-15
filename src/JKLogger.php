<?php

declare(strict_types=1);

namespace johnykvsky\Utils;

use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * Light, easy to use logger
 */
class JKLogger extends AbstractLogger
{
    protected array $options = [
        'extension' => 'txt',
        'dateFormat' => 'Y-m-d G:i:s.u',
        'filename' => false,
        'flushFrequency' => false,
        'prefix' => 'log_',
        'logFormat' => false,
        'appendContext' => true,
        'json' => false,
    ];

    protected string $logLevelThreshold = LogLevel::DEBUG;

    protected array $logLevels = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    private string $logFilePath;
    private int $logLineCount = 0;
    private string $lastLine = '';
    private int $defaultPermissions = 0777;


    public function __construct(string $logDirectory, string $logLevelThreshold = LogLevel::DEBUG, array $options = [])
    {
        $this->logLevelThreshold = $logLevelThreshold;
        $this->options = array_merge($this->options, $options);

        $logDirectory = rtrim($logDirectory, DIRECTORY_SEPARATOR);

        if (!file_exists($logDirectory) && !mkdir($logDirectory, $this->defaultPermissions, true) && !is_dir($logDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $logDirectory));
        }

        if (strpos($logDirectory, 'php://') === 0) {
            $this->setLogToStdOut($logDirectory);
        } else {
            $this->setLogFilePath($logDirectory);
            $this->checkLogFilePath();
        }
    }

    private function checkLogFilePath(): void
    {
        if (file_exists($this->logFilePath) && !is_writable($this->logFilePath)) {
            throw new RuntimeException(
                'The file could not be written to. Check that appropriate permissions have been set.'
            );
        }
    }

    public function setLogToStdOut(string $stdOutPath): void
    {
        $this->logFilePath = $stdOutPath;
    }

    public function setDateFormat(string $dateFormat): void
    {
        $this->options['dateFormat'] = $dateFormat;
    }

    public function getLogLevelThreshold(): string
    {
        return $this->logLevelThreshold;
    }

    public function setLogLevelThreshold(string $logLevelThreshold): void
    {
        $this->logLevelThreshold = $logLevelThreshold;
    }

    public function log($level, mixed $message, array $context = []): void
    {
        if ($this->logLevels[$this->logLevelThreshold] < $this->logLevels[$level]) {
            return;
        }

        $message = $this->formatMessage($level, $message, $context);
        $this->write($message);
    }

    protected function formatMessage(string $level, mixed $message, array $context): string
    {
        if (!empty($this->options['json'])) {
            $message = json_encode($message);
        }

        if ($this->options['logFormat']) {
            $message = $this->getFormattedMessage($level, $message, $context);
        } else {
            $message = "[{$this->getTimestamp()}] [{$level}] {$message}";
        }

        if ($this->options['appendContext'] && !empty($context)) {
            $message .= PHP_EOL . $this->indent($this->contextToString($context));
        }

        return $message . PHP_EOL;
    }

    private function getFormattedMessage(string $level, mixed $rawMessage, array $context): string
    {
        $parts = [
            'date' => $this->getTimestamp(),
            'level' => strtoupper($level),
            'level-padding' => str_repeat(' ', 9 - strlen($level)),
            'priority' => $this->logLevels[$level],
            'context' => json_encode($context),
        ];

        $message = $this->options['logFormat'];

        foreach ($parts as $part => $value) {
            $message = str_replace('{' . $part . '}', $value, $message);
        }

        return $this->replaceMessage($message, $rawMessage);
    }

    /**
     * Gets the correctly formatted Date/Time for the log entry.
     *
     * PHP \DateTime is dump, and you have to resort to trickery to get microseconds
     * to work correctly, so here it is.
     *
     * @return string
     */
    private function getTimestamp(): string
    {
        $originalTime = microtime(true);
        $micro = sprintf("%06d", ($originalTime - floor($originalTime)) * 1000000);
        $date = new \DateTime(date('Y-m-d H:i:s.' . $micro, (int)$originalTime));

        return $date->format($this->options['dateFormat']);
    }

    private function replaceMessage(string $message, string $value): string
    {
        if (!empty($this->options['json'])) {
            return str_replace('"{message}"', $value, $message);
        }

        return str_replace('{message}', $value, $message);
    }

    protected function indent(string $string, string $indent = '    '): string
    {
        return $indent . str_replace("\n", "\n" . $indent, $string);
    }

    protected function contextToString(array $context): string
    {
        $export = '';
        foreach ($context as $key => $value) {
            $export .= "{$key}: ";
            $export .= preg_replace(
                [
                    '/=>\s+([a-zA-Z])/im',
                    '/array\(\s+\)/im',
                    '/^  |\G  /m',
                ],
                [
                    '=> $1',
                    'array()',
                    '    ',
                ],
                str_replace('array (', 'array(', var_export($value, true))
            );
            $export .= PHP_EOL;
        }
        return str_replace(['\\\\', '\\\''], ['\\', '\''], rtrim($export));
    }

    public function write(string $message): void
    {
        if (file_put_contents($this->getLogFilePath(), $message, FILE_APPEND) === false) {
            throw new RuntimeException(
                'The file could not be written to. Check that appropriate permissions have been set.'
            );
        }

        $this->lastLine = trim($message);
        $this->logLineCount++;
    }

    /**
     * @return string
     */
    public function getLogFilePath(): string
    {
        return $this->logFilePath;
    }

    public function setLogFilePath(string $logDirectory): void
    {
        if ($this->options['filename']) {
            if (strpos($this->options['filename'], '.log') !== false || strpos(
                    $this->options['filename'],
                    '.txt'
                ) !== false) {
                $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $this->options['filename'];
            } else {
                $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $this->options['filename'] . '.' . $this->options['extension'];
            }
        } else {
            $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $this->options['prefix'] . date(
                    'Y-m-d'
                ) . '.' . $this->options['extension'];
        }
    }

    public function getLastLogLine(): string
    {
        return $this->lastLine;
    }

    public function getLogLineCount(): int
    {
        return $this->logLineCount;
    }
}
