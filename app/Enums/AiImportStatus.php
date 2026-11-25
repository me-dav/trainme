<?php

namespace App\Enums;

enum AiImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isRunning(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
