<?php

namespace App\Policies;

use App\Models\Instance;
use App\Models\User;

class InstancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Instance $instance): bool
    {
        return $user->hasAccessTo($instance);
    }

    public function deploy(User $user, Instance $instance): bool
    {
        return $user->hasAccessTo($instance) && $instance->is_active;
    }

    /**
     * Дампы инстанса и восстановление из них — только админу: это перезапись базы стенда
     * целиком, и список дампов заодно показывает имя её базы.
     */
    public function restore(User $user, Instance $instance): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Instance $instance): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Instance $instance): bool
    {
        return $user->isAdmin();
    }

    public function manageAccess(User $user, Instance $instance): bool
    {
        return $user->isAdmin();
    }
}
