<?php

namespace Spatie\FlareDebugSender\Channels;

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

    public function message(mixed $content, ?string $label = null): void
    {
        $this->appendToFile($content, $label, 'message');
    }

    public function error(mixed $content, ?string $label = null): void
    {
        $this->appendToFile($content, $label, 'error');
    }

    protected function appendToFile(mixed $content, ?string $label, string $type): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $formattedLabel = $label ? " [{$label}]" : '';
        $formattedType = strtoupper($type);

        $header = "{$timestamp} {$formattedType}{$formattedLabel}:";
        $formattedContent = json_encode($content, JSON_PRETTY_PRINT);

        $output = "{$header}\n{$formattedContent}\n\n";

        file_put_contents($this->file, $output, FILE_APPEND);
    }
}
