<?php

namespace App\Http\Requests\Users;

use App\Models\OrganizationMember;

class UpdateUserRequest extends UserRequest
{
    protected $errorBag = 'editUser';

    protected function ignoredMember(): ?OrganizationMember
    {
        return $this->route('member');
    }
}
