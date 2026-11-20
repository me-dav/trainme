<?php

namespace App\Enums;

enum Tier: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Tier A · Basic',
            self::B => 'Tier B · Advanced',
            self::C => 'Tier C · Studi kasus',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::A => 'Recall syntax dan konsep dasar.',
            self::B => 'Kombinasi opsi, audit, dan debugging.',
            self::C => 'Skenario troubleshooting multi-langkah.',
        };
    }

    public function previous(): ?self
    {
        return match ($this) {
            self::A => null,
            self::B => self::A,
            self::C => self::B,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::A => self::B,
            self::B => self::C,
            self::C => null,
        };
    }
}
