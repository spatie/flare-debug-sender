<?php

namespace Spatie\FlareDebugSender\Channels;

use Illuminate\Support\Facades\Log;

class LaravelLogDebugChannel implements FlareDebugChannel
{

    public function message(mixed $content, ?string $label = null): void
    {
        Log::info(json_encode($content, JSON_PRETTY_PRINT), array_filter(['label' => $label]));
    }

    public function error(mixed $content, ?string $label = null): void
    {
        Log::error(json_encode($content, JSON_PRETTY_PRINT), array_filter(['label' => $label]));
    }
}
