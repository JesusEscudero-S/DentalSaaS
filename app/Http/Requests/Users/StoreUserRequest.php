<?php

namespace App\Http\Requests\Users;

use App\Models\OrganizationMember;

class StoreUserRequest extends UserRequest
{
    protected $errorBag = 'createUser';

    protected function ignoredMember(): ?OrganizationMember
    {
        return null;
    }
}
