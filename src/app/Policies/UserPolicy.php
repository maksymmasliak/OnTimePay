<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'owner';
    }

    public function view(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->role === 'owner' || $user->id === $model->id);
    }

    public function create(User $user): bool
    {
        // Запрошення нового user (manager) до компанії — owner-only
        return $user->role === 'owner';
    }

    public function update(User $user, User $model): bool
    {
        if ($user->company_id !== $model->company_id) {
            return false;
        }

        // Owner може редагувати будь-кого у своїй компанії
        // Manager може редагувати лише власний профіль
        return $user->role === 'owner' || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        // Owner видаляє інших users, але не сам себе
        // (щоб компанія не залишилась без жодного owner)
        return $user->role === 'owner'
            && $user->company_id === $model->company_id
            && $user->id !== $model->id;
    }
}
