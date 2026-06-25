<?php

namespace App\Policies;

use App\Models\Contractor;
use App\Models\User;

class ContractorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageContractors();
    }

    public function view(User $user, Contractor $contractor): bool
    {
        return $user->canManageContractors();
    }

    public function create(User $user): bool
    {
        return $user->canManageContractors();
    }

    public function update(User $user, Contractor $contractor): bool
    {
        return $user->canManageContractors();
    }

    public function delete(User $user, Contractor $contractor): bool
    {
        return $user->canManageContractors();
    }

    public function bulkDelete(User $user): bool
    {
        return $user->canManageContractors();
    }

    public function export(User $user): bool
    {
        return $user->canManageContractors();
    }
}
