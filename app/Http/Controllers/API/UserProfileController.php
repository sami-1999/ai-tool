<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserProfileStoreRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\UserProfile;
use App\Models\UserProfile as UserProfileModel;
use App\Models\UserSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    public function __construct(
        private UserProfile $userProfile
    ) {}

    // Legacy methods
    public function updateProfile(UserProfileStoreRequest $request, $id)
    {
        $data = $this->userProfile->update($request->validated(), $id);
        return ApiResponse::success($data, 'User profile updated successfully', 201);
    }

    public function profile($id)
    {
        $data = $this->userProfile->profile($id);
        return ApiResponse::success($data, 'User profile retrieved successfully');
    }

    // ======= V2 ENDPOINTS FOR CURRENT USER =======

    /**
     * Get current authenticated user's profile
     */
    public function getCurrentUserProfile()
    {
        $userId = Auth::id();
        $profile = UserProfileModel::where('user_id', $userId)->first();
        
        if (!$profile) {
            return ApiResponse::success(null, 'No profile found. Please create your profile first.');
        }

        return ApiResponse::success($profile, 'User profile retrieved successfully');
    }

    /**
     * Update current authenticated user's profile
     */
    public function updateCurrentUserProfile(Request $request)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'years_experience' => 'sometimes|integer|min:0|max:50',
            'default_tone' => 'sometimes|string|in:professional,friendly,enthusiastic,formal',
            'writing_style_notes' => 'sometimes|string|max:1000',
            'birthday' => 'sometimes|date',
            'bio' => 'sometimes|string|max:5000',
            'country' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'portfolio_site_link' => 'sometimes|url|max:255',
            'github_link' => 'sometimes|url|max:255',
            'linkedin_link' => 'sometimes|url|max:255'
        ]);

        $userId = Auth::id();
        
        $profile = UserProfileModel::updateOrCreate(
            ['user_id' => $userId],
            $request->only([
                'title', 
                'years_experience', 
                'default_tone', 
                'writing_style_notes',
                'birthday',
                'bio',
                'country',
                'city',
                'address',
                'portfolio_site_link',
                'github_link',
                'linkedin_link'
            ])
        );

        return ApiResponse::success($profile, 'User profile updated successfully');
    }

    // ======= USER SKILLS MANAGEMENT VIA PROFILE API =======

    /**
     * Get current user's profile with skills
     */
    public function getProfileWithSkills()
    {
        $userId = Auth::id();
        $profile = UserProfileModel::where('user_id', $userId)->first();
        
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

        $data = [
            'profile' => $profile,
            'skills' => $userSkills
        ];

        return ApiResponse::success($data, 'User profile with skills retrieved successfully');
    }

    /**
     * Add skills to current user's profile
     */
    public function addUserSkills(Request $request)
    {
        $request->validate([
            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.proficiency_level' => [
                'required',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ]
        ]);

        $userId = Auth::id();
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

    /**
     * Update user skill proficiency
     */
    public function updateUserSkill(Request $request, $skillId)
    {
        $request->validate([
            'proficiency_level' => [
                'required',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ]
        ]);

        $userId = Auth::id();
        
        $userSkill = UserSkill::where('user_id', $userId)
            ->where('skill_id', $skillId)
            ->first();

        if (!$userSkill) {
            return ApiResponse::error('Skill not found for this user', 404);
        }

        $userSkill->update(['proficiency_level' => $request->proficiency_level]);
        $userSkill->load('skill');

        $result = [
            'id' => $userSkill->id,
            'skill_id' => $userSkill->skill_id,
            'skill_name' => $userSkill->skill->name,
            'proficiency_level' => $userSkill->proficiency_level,
        ];

        return ApiResponse::success($result, 'User skill updated successfully');
    }

    /**
     * Remove skill from current user's profile
     */
    public function removeUserSkill($skillId)
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

    /**
     * Get all available skills for selection
     */
    public function getAvailableSkills()
    {
        $skills = \App\Models\Skill::where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return ApiResponse::success($skills, 'Available skills retrieved successfully');
    }
}
