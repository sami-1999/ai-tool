<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UserProfileStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Required basic fields
            'title'                => 'required|string|max:255',
            'years_experience'     => 'required|integer|min:0|max:100',
            
            // Tone and style fields
            'default_tone'         => 'nullable|string|in:professional,casual,creative,formal,friendly',
            'writing_style_notes'  => 'nullable|string|max:2000',
            
            // Personal information
            'birthday'             => 'nullable|date|before:today',
            'bio'                  => 'nullable|string|max:1000',
            
            // Address information
            'country'              => 'nullable|string|max:255',
            'city'                 => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            
            // Professional links
            'portfolio_site_link'  => 'nullable|url|max:255',
            'github_link'          => 'nullable|url|max:255',
            'linkedin_link'        => 'nullable|url|max:255',
            
            // System field
            'user_id'              => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Professional title is required',
            'years_experience.required' => 'Years of experience is required',
            'years_experience.min' => 'Years of experience cannot be negative',
            'years_experience.max' => 'Years of experience seems too high',
            'birthday.before' => 'Birthday must be a date before today',
            'bio.max' => 'Bio cannot exceed 1000 characters',
            'portfolio_site_link.url' => 'Portfolio site must be a valid URL',
            'github_link.url' => 'GitHub link must be a valid URL',
            'linkedin_link.url' => 'LinkedIn link must be a valid URL',
            'hourly_rate.min' => 'Hourly rate cannot be negative',
            'hourly_rate.max' => 'Hourly rate seems too high',
            'achievements.max' => 'Achievements section cannot exceed 2000 characters',
            'default_tone.in' => 'Default tone must be one of: professional, casual, creative, formal, friendly',
            'communication_style.in' => 'Communication style must be one of: formal, casual, friendly, professional, technical',
            'availability.in' => 'Availability must be one of: full-time, part-time, contract, freelance, not-available',
        ];
    }
    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}
