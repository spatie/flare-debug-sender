<?php

namespace Spatie\FlareDebugSender;

use Closure;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;

class FlareDebugSender implements Sender
{
    private bool $passthroughErrors;

    private bool $passthroughTraces;

    private bool $passthroughZipkin;

    private bool $printFullPayload;

    private bool $replaceTracingIds;

    private bool $replaceTracingTimes;

    public function __construct(
        protected array $config = [
            'passthrough_errors' => false,
            'passthrough_traces' => false,
            'passthrough_zipkin' => false,
            'replace_tracing_ids' => true,
            'replace_tracing_times' => true,
            'print_full_payload' => false,
        ]
    ) {
        $this->passthroughErrors = $this->config['passthrough_errors'] ?? false;
        $this->passthroughTraces = $this->config['passthrough_traces'] ?? false;
        $this->passthroughZipkin = $this->config['passthrough_zipkin'] ?? false;
        $this->replaceTracingIds = $this->config['replace_tracing_ids'] ?? true;
        $this->replaceTracingTimes = $this->config['replace_tracing_times'] ?? true;
        $this->printFullPayload = $this->config['print_full_payload'] ?? false;
    }

    public function post(string $endpoint, string $apiToken, array $payload, Closure $callback): void
    {
        if ($this->printFullPayload) {
            ray($payload)->label($endpoint);
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
                    ray($response->body)->label("Zipkin error")->red();

                    return;
                }
            });
        }

        if (count($payload['resourceSpans']) !== 1) {
            ray($payload['resourceSpans'])->label("MORE OR LESS THAN 1 RESOURCE SPANS")->red();

            return;
        }

        if (count($payload['resourceSpans'][0]['scopeSpans']) !== 1) {
            ray($payload['resourceSpans'][0]['scopeSpans'])->label("MORE OR LESS THAN 1 SCOPE SPANS")->red();

            return;
        }

        $resource = $payload['resourceSpans'][0]['resource'];
        $spans = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'];

        $mapper = new OpenTelemetryAttributeMapper();

        $resource['attributes'] = $mapper->attributesToPHP($resource['attributes']);

        ray($resource)->label('resource');

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
            }
        }

        ray($spans)->label('spans');

        if (count($missingEnd) > 0) {
            ray($missingEnd)->label("MISSING END SPANS")->red();
        }

        if (count($missingParent) !== 1) {
            ray($missingParent)->label("MORE OR LESS THAN 1 ROOT SPANS")->red();
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
                    $cleaned[$spanId] = 'type_'.($i + 1);
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
        (new CurlSender())->post($endpoint, $apiToken, $payload, function (Response $response) use ($callback) {
            $callback($response);
        });
    }
}
