<?php

namespace Spatie\FlareDebugSender\Channels;

class RayDebugChannel implements FlareDebugChannel
{
    public function message(
        mixed $content,
        ?string $label = null
    ): void {
        if ($label) {
            ray($content)->label($label);

            return;
        }

        ray($content);
    }

    public function error(
        mixed $content,
        ?string $label = null
    ): void {
        if( $label) {
            ray($content)->label($label)->red();

            return;
        }

        ray($content)->red();
    }
}
