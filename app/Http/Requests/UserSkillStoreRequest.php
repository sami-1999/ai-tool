<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UserSkillStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Single skill add - either skill_id or skill_name is required
            'skill_id' => 'nullable|exists:skills,id|required_without:skill_name',
            'skill_name' => 'nullable|string|max:255|required_without:skill_id',
            'proficiency_level' => [
                'required',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ],
            // Bulk skills add
            'skills' => 'sometimes|array',
            'skills.*.skill_id' => 'nullable|exists:skills,id',
            'skills.*.skill_name' => 'nullable|string|max:255',
            'skills.*.proficiency_level' => [
                'required_with:skills',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'skill_id.required_without' => 'Either skill_id or skill_name must be provided',
            'skill_name.required_without' => 'Either skill_id or skill_name must be provided',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}
