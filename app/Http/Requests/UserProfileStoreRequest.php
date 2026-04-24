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
            'title'                => 'required|string|max:255',
            'years_experience'     => 'required|integer|min:0|max:50',
            'birthday'             => 'nullable|date|before:today',
            'bio'                  => 'nullable|string|max:5000',
            'country'              => 'nullable|string|max:255',
            'city'                 => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'portfolio_site_link'  => 'nullable|url|max:255',
            'linkedin_link'        => 'nullable|url|max:255',
            'github_link'          => 'nullable|url|max:255',
            'user_id'              => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Professional Title is required',
            'title.max' => 'Professional Title cannot exceed 255 characters',
            'years_experience.required' => 'Years of Experience is required',
            'years_experience.min' => 'Years of Experience cannot be negative',
            'years_experience.max' => 'Years of Experience cannot exceed 50 years',
            'birthday.before' => 'Birthday must be a date before today',
            'birthday.date' => 'Birthday must be a valid date',
            'bio.max' => 'Professional Bio cannot exceed 5000 characters',
            'country.max' => 'Country cannot exceed 255 characters',
            'city.max' => 'City cannot exceed 255 characters',
            'address.max' => 'Full Address cannot exceed 500 characters',
            'portfolio_site_link.url' => 'Portfolio Website must be a valid URL',
            'portfolio_site_link.max' => 'Portfolio Website cannot exceed 255 characters',
            'linkedin_link.url' => 'LinkedIn Profile must be a valid URL',
            'linkedin_link.max' => 'LinkedIn Profile cannot exceed 255 characters',
            'github_link.url' => 'GitHub link must be a valid URL',
            'github_link.max' => 'GitHub link cannot exceed 255 characters',
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
