<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkillStoreRequest;
use App\Http\Requests\SkillUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\SkillService;
use App\Models\UserSkill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function __construct(
        private SkillService $skillService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = $this->skillService->index();
        return ApiResponse::success($data, 'Skills retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SkillStoreRequest $request)
    {
        $data = $this->skillService->store($request->validated());
        return ApiResponse::success($data, 'Skill created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->skillService->show($id);
        return ApiResponse::success($data, 'Skill retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SkillUpdateRequest $request, string $id)
    {
        $data = $this->skillService->update($request->validated(), $id);
        return ApiResponse::success($data, 'Skill updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = $this->skillService->delete($id);
        return ApiResponse::success($data, 'Skill deleted successfully');
    }

    // ======= USER SKILLS MANAGEMENT (Separate from master skills) =======

    /**
     * Get current user's skills with proficiency levels
     */
    public function getUserSkills()
    {
        $userId = Auth::id();
        $userSkills = UserSkill::with('skill')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($userSkill) {
                return [
                    'id' => $userSkill->id,
                    'skill_id' => $userSkill->skill_id,
                    'skill_name' => $userSkill->skill->name,
                    'proficiency_level' => $userSkill->proficiency_level,
                ];
            });

        return ApiResponse::success($userSkills, 'User skills retrieved successfully');
    }

    /**
     * Add skill(s) to current user
     */
    public function storeUserSkill(Request $request)
    {
        $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'proficiency_level' => [
                'required',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ],
            // Alternative: multiple skills at once
            'skills' => 'sometimes|array',
            'skills.*.skill_id' => 'required_with:skills|exists:skills,id',
            'skills.*.proficiency_level' => [
                'required_with:skills',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ]
        ]);

        $userId = Auth::id();

        // Handle multiple skills if provided
        if ($request->has('skills')) {
            $addedSkills = [];
            foreach ($request->skills as $skillData) {
                $userSkill = UserSkill::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'skill_id' => $skillData['skill_id']
                    ],
                    [
                        'proficiency_level' => $skillData['proficiency_level']
                    ]
                );
                $userSkill->load('skill');
                $addedSkills[] = [
                    'id' => $userSkill->id,
                    'skill_id' => $userSkill->skill_id,
                    'skill_name' => $userSkill->skill->name,
                    'proficiency_level' => $userSkill->proficiency_level,
                ];
            }
            return ApiResponse::success($addedSkills, 'User skills updated successfully');
        }

        // Handle single skill
        $userSkill = UserSkill::updateOrCreate(
            [
                'user_id' => $userId,
                'skill_id' => $request->skill_id
            ],
            [
                'proficiency_level' => $request->proficiency_level
            ]
        );

        $userSkill->load('skill');
        $result = [
            'id' => $userSkill->id,
            'skill_id' => $userSkill->skill_id,
            'skill_name' => $userSkill->skill->name,
            'proficiency_level' => $userSkill->proficiency_level,
        ];

        return ApiResponse::success($result, 'User skill added successfully');
    }

    /**
     * Remove skill from current user
     */
    public function removeUserSkill(string $skillId)
    {
        $userId = Auth::id();
        
        $userSkill = UserSkill::where('user_id', $userId)
            ->where('skill_id', $skillId)
            ->first();

        if (!$userSkill) {
            return ApiResponse::error('Skill not found for this user', 404);
        }

        $userSkill->delete();

        return ApiResponse::success(null, 'User skill removed successfully');
    }
}
