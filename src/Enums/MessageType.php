<?php

namespace Spatie\FlareDebugSender\Enums;

enum MessageType
{
    case Reports;
    case Traces;
    case Logs;
    case Failure;
    case Other;

    public function label(): string
    {
        return match ($this) {
            self::Reports => 'report',
            self::Traces => 'trace',
            self::Logs => 'log',
            self::Failure => 'failure',
            self::Other => 'other',
        };
    }
}
