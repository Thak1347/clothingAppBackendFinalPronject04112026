<?php
namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function view(User $u, Address $a)
    {
        return $u->id === $a->user_id;
    }
    public function update(User $u, Address $a)
    {
        return $u->id === $a->user_id;
    }
    public function delete(User $u, Address $a)
    {
        return $u->id === $a->user_id;
    }
}
