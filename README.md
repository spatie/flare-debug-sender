# Debugging tool for Flare payloads

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/flare-debug-sender.svg?style=flat-square)](https://packagist.org/packages/spatie/flare-debug-sender)
[![Tests](https://img.shields.io/github/actions/workflow/status/spatie/flare-debug-sender/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/flare-debug-sender/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/flare-debug-sender.svg?style=flat-square)](https://packagist.org/packages/spatie/flare-debug-sender)

This is a debug sender for Flare payloads, mostly used for internal testing purposes.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/flare-debug-sender.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/flare-debug-sender)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/flare-debug-sender
```

## Usage

Within your Flare config:

```php
    'sender' => [
        'class' => \Spatie\FlareDebugSender\FlareDebugSender::class,
        'config' => ['passthrough_errors' => false,
            'passthrough_traces' => false,
            'passthrough_zipkin' => false,
            'replace_tracing_ids' => true,
            'replace_tracing_times' => true,
            'print_full_payload' => false,
            'print_endpoint' => false,
            'channel' => RayDebugChannel::class,
            'channel_config' => [],
            'sender' => CurlSender::class,
            'sender_config' => [
                'curl_options' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ],
            ],
        ],
    ],
```

Open up Ray and start debugging!

### Options

- `passthrough_errors`: If set to `true`, errors will be passed through to the default sender.
- `passthrough_traces`: If set to `true`, traces will be passed through to the default sender.
- `passthrough_zipkin`: If set to `true`, traces will be sent to a local Zipkin instance.
- `replace_tracing_ids`: If set to `true`, the span ids will be replaced with a more readable version.
- `replace_tracing_times`: If set to `true`, the start and end times of the spans will be replaced with a more readable version.
- `print_full_payload`: If set to `true`, the full payload will be printed.
- `print_endpoint`: If set to `true`, the endpoint will be printed.
- `channel`: The channel to use for debugging. Defaults to `RayDebugChannel`.
- `channel_config`: The configuration for the channel. Defaults to an empty array.
- `sender`: The sender to use for sending the payload. Defaults to `CurlSender`.
- `sender_config`: The configuration for the sender.

### Channels

By default, the `RayDebugChannel` is used. We also provide a few other channels that you can use:

#### LaravelLogDebugChannel

Will write messages to the Laravel log. This is useful for debugging in a Laravel application.

#### FileDebugChannel

Will write messages to a file. This is useful for debugging in a non-Laravel application.

By default, the file will be written to `flare-debug-sender.log` in the root of your project. You can change this by setting the `file` option in the channel configuration:

```php
    'channel' => FileDebugChannel::class,
    'channel_config' => [
        'file' => 'path/to/your/file.log',
    ],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ruben Van Assche](https://github.com/rubenvanassche)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
