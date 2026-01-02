<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Services\UserProfile;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
     public function __construct(
        private UserProfile $userProfile
    ) {}

    public function profile($id)
    {
        return $this->userProfile->profile($id);
    }
}
