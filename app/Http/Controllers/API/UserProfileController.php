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

    public function updateProfile(UserProfileStoreRequest $request, $id)
    {
        $data = $this->userProfile->update($request->validated(), $id);
        return ApiResponse::success($data, 'User profile updated successfully', 201);
    }

    public function profile($id)
    {
        dd($id);
        return $this->userProfile->profile($id);
    }
}
