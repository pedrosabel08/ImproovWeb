<?php

declare(strict_types=1);

namespace FlowConnect\Channels;

interface ChannelAdapter
{
    public function send(array $delivery): array;
}
