<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserProfileStoreRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\UserProfile;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(
        private UserProfile $userProfile
    ) {}

    public function create(UserProfileStoreRequest $request)
    {
        $data = $this->userProfile->create($request->validated());
        return ApiResponse::success($data, 'User  successful', 201);
    }

    public function profile($id)
    {
        return $this->userProfile->profile($id);
    }
}
