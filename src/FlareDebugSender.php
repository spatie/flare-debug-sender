<?php

namespace Spatie\FlareDebugSender;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareDebugSender\Channels\FlareDebugChannel;
use Spatie\FlareDebugSender\Channels\RayDebugChannel;
use Spatie\FlareDebugSender\Enums\MessageType;

class FlareDebugSender implements Sender
{
    private bool $passthroughErrors;

    private bool $passthroughTraces;

    private bool $passthroughLogs;

    private bool $passthroughZipkin;

    private bool $printFullPayload;

    private bool $printEndpoint;

    private bool $printResource;

    private bool $replaceTracingIds;

    private bool $replaceTracingTimes;

    private FlareDebugChannel $channel;

    private Sender $sender;

    public function __construct(
        protected array $config = [
            'passthrough_errors' => false,
            'passthrough_traces' => false,
            'passthrough_zipkin' => false,
            'replace_tracing_ids' => true,
            'replace_tracing_times' => true,
            'print_full_payload' => false,
            'print_endpoint' => false,
            'print_resource' => false,
            'channel' => RayDebugChannel::class,
            'channel_config' => [],
            'sender' => CurlSender::class,
            'sender_config' => [
                'curl_options' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ],
            ],
        ]
    ) {
        $this->passthroughErrors = $this->config['passthrough_errors'] ?? false;
        $this->passthroughTraces = $this->config['passthrough_traces'] ?? false;
        $this->passthroughLogs = $this->config['passthrough_logs'] ?? false;
        $this->passthroughZipkin = $this->config['passthrough_zipkin'] ?? false;
        $this->replaceTracingIds = $this->config['replace_tracing_ids'] ?? true;
        $this->replaceTracingTimes = $this->config['replace_tracing_times'] ?? true;
        $this->printFullPayload = $this->config['print_full_payload'] ?? false;
        $this->printEndpoint = $this->config['print_endpoint'] ?? false;
        $this->printResource = $this->config['print_resource'] ?? false;
        $this->channel = new ($this->config['channel'] ?? RayDebugChannel::class)(...($this->config['channel_config'] ?? []));
        $this->sender = new (($this->config['sender'] ?? CurlSender::class))(($this->config['sender_config'] ?? [
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]));
    }

    public function post(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        bool $test,
        Closure $callback,
    ): void {
        if ($this->printEndpoint) {
            $this->channel->message($endpoint, MessageType::Other, 'endpoint');
        }

        if ($this->printFullPayload) {
            $this->channel->message($payload, MessageType::Other, 'payload');
        }

        match ($type) {
            FlareEntityType::Errors, => $this->handleError($endpoint, $apiToken, $payload, $type, $test, $callback),
            FlareEntityType::Traces => $this->handleTrace($endpoint, $apiToken, $payload, $type, $test, $callback),
            FlareEntityType::Logs => $this->handleLogs($endpoint, $apiToken, $payload, $type, $test, $callback),
        };
    }

    protected function handleError(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        bool $test,
        Closure $callback
    ): void {
        if ($this->passthroughErrors) {
            $this->passThrough($endpoint, $apiToken, $payload, $type, $test, $callback);
        }

        uksort($payload['attributes'], fn ($a, $b) => strnatcmp($a, $b));

        $this->channel->message($payload, MessageType::Reports, 'error');
    }

    protected function handleTrace(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        bool $test,
        Closure $callback
    ): void {
        if ($this->passthroughTraces) {
            $this->passThrough($endpoint, $apiToken, $payload, $type, $test, $callback);
        }

        if ($this->passthroughZipkin) {
            $this->passThrough('127.0.0.1:4318/v1/traces', '', $payload, $type, $test, function (Response $response) {
                if ($response->code !== 200) {
                    $this->channel->message($response->body, MessageType::Failure, 'Zipkin error');

                    return;
                }
            });
        }

        if (count($payload['resourceSpans']) !== 1) {
            $this->channel->message($payload['resourceSpans'], MessageType::Failure, 'More or less than 1 resource spans');

            return;
        }

        if (count($payload['resourceSpans'][0]['scopeSpans']) !== 1) {
            $this->channel->message($payload['resourceSpans'][0]['scopeSpans'], MessageType::Failure, 'More or less than 1 scope spans');

            return;
        }

        $resource = $payload['resourceSpans'][0]['resource'];
        $spans = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'];

        $mapper = new OpenTelemetryAttributeMapper();

        $resource['attributes'] = $this->mapAttributes($mapper, $resource['attributes']);

        if ($this->printResource) {
            $this->channel->message($resource, MessageType::Traces, 'resource');
        }

        $missingEnd = [];
        $missingParent = [];

        $minimalTime = PHP_INT_MAX;

        $mappedSpanIds = [];

        foreach ($spans as $i => $span) {
            $spans[$i]['attributes'] = $this->mapAttributes($mapper, $span['attributes']);

            if ($span['startTimeUnixNano'] < $minimalTime) {
                $minimalTime = $span['startTimeUnixNano'];
            }

            if ($span['endTimeUnixNano'] < $minimalTime) {
                $minimalTime = $span['endTimeUnixNano'];
            }

            foreach ($span['events'] as $eventIndex => $event) {
                $spans[$i]['events'][$eventIndex]['attributes'] = $this->mapAttributes($mapper, $event['attributes']);

                if ($event['timeUnixNano'] < $minimalTime) {
                    $minimalTime = $event['timeUnixNano'];
                }
            }

            $type = $spans[$i]['attributes']['flare.span_type'] ?? 'unknown';

            $mappedSpanIds[$type][] = $span['spanId'];
        }

        $mappedSpanIds = $this->cleanupMappedSpanIds($mappedSpanIds);

        foreach ($spans as $span) {
            if ($span['endTimeUnixNano'] === null || $span['endTimeUnixNano'] === 0) {
                $missingEnd[] = $mappedSpanIds[$span['spanId']];
            }

            if ($span['parentSpanId'] === null || $span['parentSpanId'] === 0) {
                $missingParent[] = $mappedSpanIds[$span['spanId']];
                ;
            }
        }

        foreach ($spans as $i => $span) {
            if ($this->replaceTracingTimes) {
                $spans[$i]['startTimeUnixNano'] = round(($span['startTimeUnixNano'] - $minimalTime) / 1000_000, 0)." ({$span['startTimeUnixNano']})";
                $spans[$i]['endTimeUnixNano'] = round(($span['endTimeUnixNano'] - $minimalTime) / 1000_000, 0)." ({$span['endTimeUnixNano']})";

                foreach ($span['events'] as $eventIndex => $event) {
                    $spans[$i]['events'][$eventIndex]['timeUnixNano'] = round(($event['timeUnixNano'] - $minimalTime) / 1000_000, 0)." ({$event['timeUnixNano']})";
                }
            }

            if ($this->replaceTracingIds) {
                $spans[$i]['spanId'] = $mappedSpanIds[$span['spanId']] ?? $spans[$i]['spanId'];
                $spans[$i]['parentSpanId'] = $mappedSpanIds[$span['parentSpanId']] ?? $spans[$i]['parentSpanId'];

                $spans[$spans[$i]['spanId']] = $spans[$i];
                unset($spans[$i]);
            }
        }

        $this->channel->message($spans, MessageType::Traces, 'spans');

        if (count($missingEnd) > 0) {
            $this->channel->message($missingEnd, MessageType::Failure, 'Missing end spans');
        }

        if (count($missingParent) !== 1) {
            $this->channel->message($missingParent, MessageType::Failure, 'More or less than 1 root span');
        }
    }

    protected function handleLogs(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        bool $test,
        Closure $callback
    ): void {
        ray($payload);

        if ($this->passthroughLogs) {
            $this->passThrough($endpoint, $apiToken, $payload, $type, $test, $callback);
        }

        if (count($payload['resourceLogs']) !== 1) {
            $this->channel->message($payload['resourceLogs'], MessageType::Failure, 'More or less than 1 resource logs');

            return;
        }

        if (count($payload['resourceLogs'][0]['scopeLogs']) !== 1) {
            $this->channel->message($payload['resourceLogs'][0]['scopeLogs'], MessageType::Failure, 'More or less than 1 scope logs');

            return;
        }

        $resource = $payload['resourceLogs'][0]['resource'];
        $logs = $payload['resourceLogs'][0]['scopeLogs'][0]['logRecords'];

        $mapper = new OpenTelemetryAttributeMapper();

        $resource['attributes'] = $this->mapAttributes($mapper, $resource['attributes']);

        if ($this->printResource) {
            $this->channel->message($resource, MessageType::Logs, 'resource');
        }

        foreach ($logs as $log) {
            if (array_key_exists('attributes', $log)) {
                $log['attributes'] = $this->mapAttributes($mapper, $log['attributes']);
            }

            $this->channel->message($log, MessageType::Logs, 'log');
        }
    }

    protected function mapAttributes(
        OpenTelemetryAttributeMapper $mapper,
        array $attributes
    ): array {

        $attributes = $mapper->attributesToPHP($attributes);

        uksort($attributes, fn ($a, $b) => strnatcmp($a, $b));

        return $attributes;
    }

    protected function cleanupMappedSpanIds($mappedSpanIds): array
    {
        $cleaned = [];

        foreach ($mappedSpanIds as $type => $spans) {
            if (count($spans) === 1) {
                $cleaned[$spans[0]] = $type.' ('.substr($spans[0], 0, 4).')';
            } else {
                foreach ($spans as $i => $spanId) {
                    $cleaned[$spanId] = $type.'_'.($i + 1).' ('.substr($spanId, 0, 4).')';
                }
            }
        }

        return $cleaned;
    }

    protected function passThrough(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        bool $test,
        Closure $callback
    ): void {
        try {
            $this->sender->post($endpoint, $apiToken, $payload, $type, $test, function (Response $response) use ($callback) {
                $callback($response);
            });
        } catch (\Throwable $throwable) {
            $this->channel->message("Was unable to send to {$endpoint} because: {$throwable->getMessage()}", MessageType::Failure);
        }
    }
}
