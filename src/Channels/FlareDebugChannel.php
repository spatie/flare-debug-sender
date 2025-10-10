<?php

namespace Spatie\FlareDebugSender\Channels;

use Spatie\FlareDebugSender\Enums\MessageType;

interface FlareDebugChannel
{
    public function message(
        mixed $content,
        MessageType $type,
        ?string $label = null,
    ): void;
}
