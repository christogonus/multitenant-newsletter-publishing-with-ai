<?php

namespace App\Enums;

enum BlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case Bullets = 'bullets';
    case Link = 'link';
    case Image = 'image';
    case Quote = 'quote';
    case Divider = 'divider';

    public function label(): string
    {
        return match ($this) {
            self::Heading => 'Heading',
            self::Paragraph => 'Text',
            self::Bullets => 'List',
            self::Link => 'Link',
            self::Image => 'Picture',
            self::Quote => 'Quote',
            self::Divider => 'Divider',
        };
    }
}
