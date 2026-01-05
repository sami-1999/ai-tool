<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ProjectUpdateRequest extends FormRequest
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
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'industry'    => 'nullable|string|max:255',
            'challenges'  => 'nullable|string',
            'outcome'     => 'nullable|string',
            'skills'      => 'nullable|array',
            'skills.*'    => 'exists:skills,id',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}
