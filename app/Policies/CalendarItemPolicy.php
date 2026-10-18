<?php

namespace App\Policies;

use App\Models\CalendarItem;
use App\Models\User;

class CalendarItemPolicy
{
    public function view(User $user, CalendarItem $calendarItem): bool
    {
        return $calendarItem->newsletter()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, CalendarItem $calendarItem): bool
    {
        return $calendarItem->newsletter()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, CalendarItem $calendarItem): bool
    {
        return $calendarItem->newsletter()->where('user_id', $user->id)->exists();
    }
}
