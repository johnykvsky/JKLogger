# JKLogger

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-github-actions]][link-github-actions]

Simple Logging for PHP. Credits goes to [Kenny Katzgrau](http://twitter.com/katzgrau) and [Dan Horrigan](http://twitter.com/dhrrgn)

JKLogger is an easy-to-use [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) compliant logging class for PHP. It isn't naive about file permissions (which is expected). It was meant to be a class that you could quickly include into a project and have working right away.

## Install

Via Composer

``` bash
$ composer require johnykvsky/jklogger
```

Should work fine on PHP 5.6, but I didn't check that. Just change required PHP version in composer.json and maybe remove dev packages and type hinting.

## Usage

``` php
<?php

require 'vendor/autoload.php';

$users = [
    [
        'name' => 'Kenny Katzgrau',
        'username' => 'katzgrau',
    ],
    [
        'name' => 'Dan Horrigan',
        'username' => 'dhrrgn',
    ],
];

$logger = new johnykvsky\Utils\JKLogger(__DIR__.'/logs');
$logger->info('Returned a million search results');
$logger->error('Oh dear.');
$logger->debug('Got these users from the Database.', $users);
```
### Output

```
[2014-03-20 3:35:43.762437] [INFO] Returned a million search results
[2014-03-20 3:35:43.762578] [ERROR] Oh dear.
[2014-03-20 3:35:43.762795] [DEBUG] Got these users from the Database.
    0: array(
        'name' => 'Kenny Katzgrau',
        'username' => 'katzgrau',
    )
    1: array(
        'name' => 'Dan Horrigan',
        'username' => 'dhrrgn',
    )
```

## Setting the Log Level Threshold

You can use the `Psr\Log\LogLevel` constants to set Log Level Threshold, so that any messages below that level, will not be logged.

### Default Level

The default level is `DEBUG`, which means everything will be logged.

### Available Levels

``` php
<?php
use Psr\Log\LogLevel;

// These are in order of highest priority to lowest.
LogLevel::EMERGENCY;
LogLevel::ALERT;
LogLevel::CRITICAL;
LogLevel::ERROR;
LogLevel::WARNING;
LogLevel::NOTICE;
LogLevel::INFO;
LogLevel::DEBUG;
```

### Example

``` php
<?php
// The 
$logger = new johnykvsky\Utils\JKLogger('/var/log/', Psr\Log\LogLevel::WARNING);
$logger->error('Uh Oh!'); // Will be logged
$logger->info('Something Happened Here'); // Will be NOT logged
```

### Additional Options

JKLogger supports additional options via third parameter in the constructor:

``` php
<?php
// Example
$logger = new johnykvsky\Utils\JKLogger('/var/log/', Psr\Log\LogLevel::WARNING, array (
    'extension' => 'log', // changes the log file extension
));
```

Here's the full list:

| Option | Default | Description |
| ------ | ------- | ----------- |
| dateFormat | 'Y-m-d G:i:s.u' | The format of the date in the start of the log lone (php formatted) |
| extension | 'txt' | The log file extension |
| filename | [prefix][date].[extension] | Set the filename for the log file. **This overrides the prefix and extention options.** |
| flushFrequency | `false` (disabled) | How many lines to flush the output buffer after |
| prefix  | 'log_' | The log file prefix |
| logFormat | `false` | Format of log entries |
| appendContext | `true` | When `false`, don't append context to log entries |

### Log Formatting

The `logFormat` option lets you define what each line should look like and can contain parameters representing the date, message, etc.

When a string is provided, it will be parsed for variables wrapped in braces (`{` and `}`) and replace them with the appropriate value:

| Parameter | Description |
| --------- | ----------- |
| date | Current date (uses `dateFormat` option) |
| level | The PSR log level |
| level-padding | The whitespace needed to make this log level line up visually with other log levels in the log file |
| priority | Integer value for log level (see `$logLevels`) |
| message | The message being logged |
| context | JSON-encoded context |

#### Tab-separated

Same as default format but separates parts with tabs rather than spaces:

    $logFormat = "[{date}]\t[{level}]\t{message}";

#### Custom variables and static text

Inject custom content into log messages:

    $logFormat = "[{date}] [$var] StaticText {message}";

#### JSON

To output pure JSON, set `appendContext` to `false` and provide something like the below as the value of the `logFormat` option:

```
$logFormat = json_encode([
    'datetime' => '{date}',
    'logLevel' => '{level}',
    'message'  => '{message}',
    'context'  => '{context}',
]);
```

The output will look like:

    {"datetime":"2015-04-16 10:28:41.186728","logLevel":"INFO","message":"Message content","context":"{"1":"foo","2":"bar"}"}
    
#### Pretty Formatting with Level Padding

For the obsessive compulsive

    $logFormat = "[{date}] [{level}]{level-padding} {message}";

... or ...

    $logFormat = "[{date}] [{level}{level-padding}] {message}";

## Testing

``` bash
$ composer test
```

## Code checking

``` bash
$ composer phpstan
$ composer phpstan-max
```


## Security

If you discover any security related issues, please email johnykvsky@protonmail.com instead of using the issue tracker.

## Credits

- [johnykvsky][link-author]
- [Kenny Katzgrau](http://twitter.com/katzgrau)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/johnykvsky/JKLogger.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/johnykvsky/JKLogger.svg?style=flat-square
[ico-github-actions]: https://github.com/johnykvsky/JKLogger/actions/workflows/php.yml/badge.svg

[link-packagist]: https://packagist.org/packages/johnykvsky/JKLogger
[link-downloads]: https://packagist.org/packages/johnykvsky/JKLogger
[link-author]: https://github.com/johnykvsky
[link-github-actions]: https://github.com/johnykvsky/JKLogger/actions
