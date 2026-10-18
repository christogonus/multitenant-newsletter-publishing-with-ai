<?php

namespace App\Enums;

enum SocialChannel: string
{
    case XThread = 'x_thread';
    case LinkedIn = 'linkedin';
    case VideoScript = 'video_script';

    public function label(): string
    {
        return match ($this) {
            self::XThread => 'X thread',
            self::LinkedIn => 'LinkedIn post',
            self::VideoScript => 'Short video script',
        };
    }
}
