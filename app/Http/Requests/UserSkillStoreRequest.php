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
            'skill_id' => 'required|exists:skills,id',
            'proficiency_level' => [
                'required',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ],
            'skills' => 'sometimes|array',
            'skills.*.skill_id' => 'required_with:skills|exists:skills,id',
            'skills.*.proficiency_level' => [
                'required_with:skills',
                Rule::in(['beginner', 'intermediate', 'expert'])
            ]
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}
