<?php

namespace Spatie\FlareDebugSender;

use Closure;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareDebugSender\Channels\FlareDebugChannel;
use Spatie\FlareDebugSender\Channels\RayDebugChannel;

class FlareDebugSender implements Sender
{
    private bool $passthroughErrors;

    private bool $passthroughTraces;

    private bool $passthroughZipkin;

    private bool $printFullPayload;

    private bool $printEndpoint;

    private bool $replaceTracingIds;

    private bool $replaceTracingTimes;

    private FlareDebugChannel $channel;

    private CurlSender $curlSender;

    public function __construct(
        protected array $config = [
            'passthrough_errors' => false,
            'passthrough_traces' => false,
            'passthrough_zipkin' => false,
            'replace_tracing_ids' => true,
            'replace_tracing_times' => true,
            'print_full_payload' => false,
            'print_endpoint' => false,
            'channel' => RayDebugChannel::class,
            'channel_config' => [],
        ]
    ) {
        $this->passthroughErrors = $this->config['passthrough_errors'] ?? false;
        $this->passthroughTraces = $this->config['passthrough_traces'] ?? false;
        $this->passthroughZipkin = $this->config['passthrough_zipkin'] ?? false;
        $this->replaceTracingIds = $this->config['replace_tracing_ids'] ?? true;
        $this->replaceTracingTimes = $this->config['replace_tracing_times'] ?? true;
        $this->printFullPayload = $this->config['print_full_payload'] ?? false;
        $this->printEndpoint = $this->config['print_endpoint'] ?? false;
        $this->channel = new ($this->config['channel'] ?? RayDebugChannel::class)(...($this->config['channel_config'] ?? []));
        $this->curlSender = new CurlSender([
            'curl_options' => [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ]
        ]);
    }

    public function post(string $endpoint, string $apiToken, array $payload, Closure $callback): void
    {
        if ($this->printEndpoint) {
            $this->channel->message($endpoint, 'endpoint');
        }

        if ($this->printFullPayload) {
            $this->channel->message($endpoint, 'payload');
        }

        if (! array_key_exists('resourceSpans', $payload)) {
            $this->handleError($endpoint, $apiToken, $payload, $callback);
        } else {
            $this->handleTrace($endpoint, $apiToken, $payload, $callback);
        }
    }

    protected function handleError(
        string $endpoint,
        string $apiToken,
        array $payload,
        Closure $callback
    ): void {
        if ($this->passthroughErrors) {
            $this->passThrough($endpoint, $apiToken, $payload, $callback);
        }
    }

    protected function handleTrace(
        string $endpoint,
        string $apiToken,
        array $payload,
        Closure $callback
    ): void {
        if ($this->passthroughTraces) {
            $this->passThrough($endpoint, $apiToken, $payload, $callback);
        }

        if ($this->passthroughZipkin) {
            $this->passThrough('127.0.0.1:4318/v1/traces', '', $payload, function (Response $response) {
                if ($response->code !== 200) {
                    $this->channel->error($response->body, 'Zipkin error');

                    return;
                }
            });
        }

        if (count($payload['resourceSpans']) !== 1) {
            $this->channel->error($payload['resourceSpans'], 'More or less than 1 resource spans');

            return;
        }

        if (count($payload['resourceSpans'][0]['scopeSpans']) !== 1) {
            $this->channel->error($payload['resourceSpans'][0]['scopeSpans'], 'More or less than 1 scope spans');

            return;
        }

        $resource = $payload['resourceSpans'][0]['resource'];
        $spans = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'];

        $mapper = new OpenTelemetryAttributeMapper();

        $resource['attributes'] = $mapper->attributesToPHP($resource['attributes']);

        $this->channel->message($resource, 'resource');

        $missingEnd = [];
        $missingParent = [];

        $minimalTime = PHP_INT_MAX;

        $mappedSpanIds = [];

        foreach ($spans as $i => $span) {
            $spans[$i]['attributes'] = $mapper->attributesToPHP($span['attributes']);

            if ($span['startTimeUnixNano'] < $minimalTime) {
                $minimalTime = $span['startTimeUnixNano'];
            }

            if ($span['endTimeUnixNano'] < $minimalTime) {
                $minimalTime = $span['endTimeUnixNano'];
            }

            foreach ($span['events'] as $eventIndex => $event) {
                $spans[$i]['events'][$eventIndex]['attributes'] = $mapper->attributesToPHP($event['attributes']);

                if ($event['timeUnixNano'] < $minimalTime) {
                    $minimalTime = $event['timeUnixNano'];
                }
            }

            if ($span['endTimeUnixNano'] === null || $span['endTimeUnixNano'] === 0) {
                $missingEnd[] = $i;
            }

            if ($span['parentSpanId'] === null || $span['parentSpanId'] === 0) {
                $missingParent[] = $i;
            }

            $type = $spans[$i]['attributes']['flare.span_type'] ?? 'unknown';

            $mappedSpanIds[$type][] = $span['spanId'];
        }

        $mappedSpanIds = $this->cleanupMappedSpanIds($mappedSpanIds);

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

        $this->channel->message($spans, 'spans');

        if (count($missingEnd) > 0) {
            $this->channel->error($missingEnd, 'Missing end spans');
        }

        if (count($missingParent) !== 1) {
            $this->channel->error($missingParent, 'More or less than 1 root span');
        }
    }

    protected function cleanupMappedSpanIds($mappedSpanIds): array
    {
        $cleaned = [];

        foreach ($mappedSpanIds as $type => $spans) {
            if (count($spans) === 1) {
                $cleaned[$spans[0]] = $type;
            } else {
                foreach ($spans as $i => $spanId) {
                    $cleaned[$spanId] = $type.'_'.($i + 1);
                }
            }
        }

        return $cleaned;
    }

    protected function passThrough(
        string $endpoint,
        string $apiToken,
        array $payload,
        Closure $callback
    ): void {
        try {
            $this->curlSender->post($endpoint, $apiToken, $payload, function (Response $response) use ($callback) {
                $callback($response);
            });
        } catch (\Throwable $throwable) {
            $this->channel->error("Was unable to send to {$endpoint} because: {$throwable->getMessage()}");
        }
    }
}
