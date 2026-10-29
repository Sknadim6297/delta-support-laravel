<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Event;

class ScheduleLockSupport
{
    public function sharedCache(): bool
    {
        return in_array((string) config('cache.default'), ['redis', 'memcached', 'dynamodb', 'database'], true);
    }

    public function apply(Event $event, int $overlapMinutes): Event
    {
        $event->withoutOverlapping($overlapMinutes);
        if ($this->sharedCache()) {
            $event->onOneServer();
        }

        return $event;
    }
};
