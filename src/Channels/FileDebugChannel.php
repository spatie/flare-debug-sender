<?php

namespace Spatie\FlareDebugSender\Channels;

use Spatie\FlareDebugSender\Enums\MessageType;

class FileDebugChannel implements FlareDebugChannel
{
    public function __construct(
        protected string $file = __DIR__.'/../../../flare-debug-sender.log',
    ) {
        if (! file_exists($this->file)) {
            touch($this->file);
        }

        if (! is_writable($this->file)) {
            throw new \RuntimeException("The log file {$this->file} is not writable.");
        }
    }

    public function message(mixed $content, MessageType $type, ?string $label = null): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $formattedLabel = $label ? " [{$label}]" : '';
        $formattedType = strtoupper($type->label());

        $header = "{$timestamp} {$formattedType}{$formattedLabel}:";
        $formattedContent = json_encode($content, JSON_PRETTY_PRINT);

        $output = "{$header}\n{$formattedContent}\n\n";

        file_put_contents($this->file, $output, FILE_APPEND);
    }
}
