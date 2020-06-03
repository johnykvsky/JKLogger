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
    /**
     * @var mixed[] $options Core options
     */
    protected $options = [
        'extension' => 'txt',
        'dateFormat' => 'Y-m-d G:i:s.u',
        'filename' => false,
        'flushFrequency' => false,
        'prefix' => 'log_',
        'logFormat' => false,
        'appendContext' => true,
        'json' => false,
    ];
    /**
     * @var string $logLevelThreshold Current minimum logging threshold
     */
    protected $logLevelThreshold = LogLevel::DEBUG;
    /**
     * @var array Log Levels
     */
    protected $logLevels = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];
    /**
     * @var string $logFilePath Path to the log file
     */
    private $logFilePath;
    /**
     * @var int $logLineCount Number of lines logged in this instance's lifetime
     */
    private $logLineCount = 0;
    /**
     * @var string $lastLine This holds the last line logged to the logger (Used for unit tests)
     */
    private $lastLine = '';

    /**
     * @var integer $defaultPermissions Octal notation for default permissions of the log file
     */
    private $defaultPermissions = 0777;

    /**
     * @param string $logDirectory File path to the logging directory
     * @param string $logLevelThreshold The LogLevel Threshold
     * @param mixed[] $options
     * @return void
     */
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

    /**
     * @param string $stdOutPath
     * @return void
     */
    public function setLogToStdOut(string $stdOutPath): void
    {
        $this->logFilePath = $stdOutPath;
    }

    /**
     * @param string $dateFormat Valid format string for date()
     * @return void
     */
    public function setDateFormat(string $dateFormat): void
    {
        $this->options['dateFormat'] = $dateFormat;
    }

    /**
     * @return string
     */
    public function getLogLevelThreshold(): string
    {
        return $this->logLevelThreshold;
    }

    /**
     * @param string $logLevelThreshold The log level threshold
     * @return void
     */
    public function setLogLevelThreshold(string $logLevelThreshold): void
    {
        $this->logLevelThreshold = $logLevelThreshold;
    }

    /**
     * @param string $level
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if ($this->logLevels[$this->logLevelThreshold] < $this->logLevels[$level]) {
            return;
        }

        $message = $this->formatMessage($level, $message, $context);
        $this->write($message);
    }

    /**
     * Formats the message for logging.
     *
     * @param string $level The Log Level of the message
     * @param mixed $message The message to log
     * @param mixed[] $context The context
     * @return string
     */
    protected function formatMessage(string $level, $message, array $context): string
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

    /**
     * @param string $level
     * @param $rawMessage
     * @param array $context
     * @return string
     */
    private function getFormattedMessage(string $level, $rawMessage, array $context): string
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

    /**
     * @param string $message The message to log
     * @param string $value New value to replace
     * @return string
     */
    private function replaceMessage(string $message, string $value): string
    {
        if (!empty($this->options['json'])) {
            return str_replace('"{message}"', $value, $message);
        }

        return str_replace('{message}', $value, $message);
    }

    /**
     * Indents the given string with the given indent.
     *
     * @param string $string The string to indent
     * @param string $indent What to use as the indent.
     * @return string
     */
    protected function indent(string $string, string $indent = '    '): string
    {
        return $indent . str_replace("\n", "\n" . $indent, $string);
    }

    /**
     * Takes the given context and coverts it to a string.
     *
     * @param mixed[] $context The Context
     * @return string
     */
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

    /**
     * @param string $message Line to write to the log
     * @return void
     */
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

    /**
     * @param string $logDirectory
     */
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

    /**
     * @return string
     */
    public function getLastLogLine(): string
    {
        return $this->lastLine;
    }

    /**
     * @return int
     */
    public function getLogLineCount(): int
    {
        return $this->logLineCount;
    }
}
