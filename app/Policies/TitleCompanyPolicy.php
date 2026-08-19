<?php

namespace App\Policies;

use App\Models\TitleCompany;
use App\Models\User;

class TitleCompanyPolicy
{
    public function viewAny(User $user): bool { return $user->canManageLenders(); }
    public function view(User $user, TitleCompany $titleCompany): bool { return $user->canManageLenders(); }
    public function create(User $user): bool { return $user->canManageLenders(); }
    public function update(User $user, TitleCompany $titleCompany): bool { return $user->canManageLenders(); }
    public function delete(User $user, TitleCompany $titleCompany): bool { return $user->canManageLenders(); }
}
