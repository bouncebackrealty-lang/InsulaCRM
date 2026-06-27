<?php

namespace App\Policies;

use App\Models\Lender;
use App\Models\User;

class LenderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageLenders();
    }

    public function view(User $user, Lender $lender): bool
    {
        return $user->canManageLenders();
    }

    public function create(User $user): bool
    {
        return $user->canManageLenders();
    }

    public function update(User $user, Lender $lender): bool
    {
        return $user->canManageLenders();
    }

    public function delete(User $user, Lender $lender): bool
    {
        return $user->canManageLenders();
    }
}
