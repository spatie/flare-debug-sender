<?php

use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareDebugSender\Channels\FileDebugChannel;
use Spatie\FlareDebugSender\FlareDebugSender;

it('writes error payloads with naturally sorted attributes to the configured file channel', function () {
    $file = tmpfile();
    $path = stream_get_meta_data($file)['uri'];

    try {
        $sender = new FlareDebugSender([
            'channel' => FileDebugChannel::class,
            'channel_config' => ['file' => $path],
        ]);

        $sender->post('https://example.com/errors', 'token', [
            'message' => 'Example error',
            'attributes' => ['item10' => 'ten', 'item2' => 'two', 'item1' => 'one'],
        ], FlareEntityType::Errors, false, function () {
        });

        $output = file_get_contents($path);

        expect($output)->toContain('REPORT [error]:');
        expect(substr($output, strpos($output, "\n") + 1))->toBe(json_encode([
            'message' => 'Example error',
            'attributes' => ['item1' => 'one', 'item2' => 'two', 'item10' => 'ten'],
        ], JSON_PRETTY_PRINT)."\n\n");
    } finally {
        fclose($file);
    }
});
