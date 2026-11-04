<?php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $u, Order $o): bool
    {
        return $u->id === $o->user_id || $u->role === UserRole::Admin;
    }
}
