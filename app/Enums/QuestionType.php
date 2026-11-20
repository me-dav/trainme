<?php

namespace App\Enums;

enum QuestionType: string
{
    case Command = 'command';
    case Text = 'text';
    case Scenario = 'scenario';

    public function label(): string
    {
        return match ($this) {
            self::Command => 'Ketik perintah',
            self::Text => 'Isian singkat',
            self::Scenario => 'Studi kasus',
        };
    }
}
