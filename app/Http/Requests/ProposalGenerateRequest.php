<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ProposalGenerateRequest extends FormRequest
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
            'job_description' => 'required|string|min:50',
            'client_name' => 'nullable|string|max:120',
            'client_rating' => 'nullable|numeric|min:0|max:5',
            'client_spending' => 'nullable|string|max:100',
            'job_type' => 'nullable|string|max:50',
            'budget' => 'nullable|string|max:100',
            'job_posted_at' => 'nullable|date',
            'proposals_count' => 'nullable|integer|min:0',
            'has_payment_verified' => 'nullable|boolean',
            'force_generate' => 'nullable|boolean',
            'provider' => 'nullable|string|in:openai,claude,gemini,groq',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('force_generate')) {
            $this->merge([
                'force_generate' => filter_var($this->input('force_generate'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}
