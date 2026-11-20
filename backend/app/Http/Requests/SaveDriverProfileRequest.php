<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDriverProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'experience_years' => ['required', 'integer', 'between:0,80'],
            'vehicle' => ['required', 'array:make,model,color,passenger_seats'],
            'vehicle.make' => ['required', 'string', 'max:80'],
            'vehicle.model' => ['required', 'string', 'max:80'],
            'vehicle.color' => ['required', 'string', 'max:40'],
            'vehicle.passenger_seats' => ['required', 'integer', 'between:1,8'],
        ];
    }
}
