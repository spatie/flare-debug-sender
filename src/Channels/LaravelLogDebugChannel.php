<?php

namespace Spatie\FlareDebugSender\Channels;

use Illuminate\Support\Facades\Log;
use Spatie\FlareDebugSender\Enums\MessageType;

class LaravelLogDebugChannel implements FlareDebugChannel
{
    public function message(mixed $content, MessageType $type, ?string $label = null): void
    {
        Log::log(
            $type === MessageType::Failure ? 'error' : 'info',
            json_encode($content, JSON_PRETTY_PRINT),
            array_filter(['label' => $label, 'type' => $type->label()])
        );
    }
}
