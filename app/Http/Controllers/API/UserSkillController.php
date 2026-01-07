<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSkillStoreRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\UserSkillService;
use Illuminate\Http\Request;

class UserSkillController extends Controller
{
    public function __construct(
        private UserSkillService $userSkillService
    ) {}

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->userSkillService->getUserSkills($userId);
        return ApiResponse::success($data, 'User skills retrieved successfully');
    }

    public function store(UserSkillStoreRequest $request)
    {
        $userId = $request->user()->id;
        $data = $this->userSkillService->store($userId, $request->validated());
        return ApiResponse::success($data, 'User skill added successfully', 201);
    }

    public function destroy(Request $request, string $skillId)
    {
        $userId = $request->user()->id;
        $data = $this->userSkillService->delete($userId, $skillId);
        return ApiResponse::success($data, 'User skill removed successfully');
    }
}
