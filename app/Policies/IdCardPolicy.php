<?php

namespace App\Policies;

use App\Models\IdCard;
use App\Models\User;

class IdCardPolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'id_cards';

    protected string $createAbility = 'generate';

    public function revoke(User $user, IdCard $card): bool
    {
        return $this->owns($user, $card) && $user->hasPermission('id_cards.revoke');
    }
}
