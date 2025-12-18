<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'start_date'     => 'sometimes|date|before_or_equal:end_date',
            'end_date'       => 'sometimes|date|after_or_equal:start_date',
            'reason'         => 'sometimes|nullable|string|max:2000',
            'status'         => 'sometimes|in:pending,approved,rejected',
            'rejection_note' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
