<?php

namespace Spatie\FlareDebugSender\Enums;

enum MessageType
{
    case Reports;
    case Traces;
    case Failure;
    case Other;

    public function label(): string
    {
        return match ($this) {
            self::Reports => 'report',
            self::Traces => 'trace',
            self::Failure => 'failure',
            self::Other => 'other',
        };
    }
}
