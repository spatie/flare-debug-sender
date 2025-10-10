<?php

namespace Spatie\FlareDebugSender\Channels;

use Spatie\FlareDebugSender\Enums\MessageType;

class RayDebugChannel implements FlareDebugChannel
{
    public function message(
        mixed $content,
        MessageType $type,
        ?string $label = null
    ): void {
        ray($content)
            ->color(match ($type) {
                MessageType::Traces => 'blue',
                MessageType::Reports => 'yellow',
                MessageType::Failure => 'red',
                MessageType::Other => 'gray',
            })
            ->label($label);
    }
}
