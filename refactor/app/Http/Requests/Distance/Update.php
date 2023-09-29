<?php

namespace App\Http\Requests\Distance;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
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
            'distance'          => 'nullable|numeric',
            'time'              => 'nullable|numeric',
            'job_id'            => 'required|numeric',
            'session_time'      => 'nullable|numeric',
            'flagged'           => 'required|boolean',
            'admin_comment'     => 'nullable|required_if:flagged,true',
            'manually_handled'  => 'required|boolean',
            'by_admin'          => 'required|boolean',
        ];
    }
}