<?php

namespace Spatie\FlareDebugSender\Channels;

interface FlareDebugChannel
{
    public function message(
        mixed $content,
        ?string $label = null
    ): void;

    public function error(
        mixed $content,
        ?string $label = null
    ): void;
}
