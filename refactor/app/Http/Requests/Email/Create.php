<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_type'         => 'required|in:admin,superadmin',
            'user_email_job_id' => 'required|exists:jobs,id',
            'user_email'        => 'nullable|email',
            'reference'         => 'nullable|string',
            'address'           => 'nullable|string',
            'instructions'      => 'nullable|string',
            'town'              => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'user_type.in'             => 'The user type must be either "admin" or "superadmin".',
            'user_email_job_id.exists' => 'The selected job does not exist in our records.',
        ];
    }
}
