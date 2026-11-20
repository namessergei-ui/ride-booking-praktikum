<?php

namespace App\Http\Requests;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    public function rules(): array
    {
        $city = ['bail', 'required', 'string', 'max:50', 'alpha_dash:ascii', Rule::exists(City::class, 'id')];

        return [
            'from' => $city,
            'to' => [...$city, 'different:from'],
            'stops' => ['sometimes', 'array', 'list', 'max:10'],
            'stops.*' => [...$city, 'distinct'],
            'departure_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:now'],
            'price_kopecks' => ['required', 'integer', 'between:0,100000000'],
            'seats' => ['required', 'integer', 'between:1,8'],
            'luggage_allowed' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $stops = $this->input('stops', []);
                if (in_array($this->input('from'), $stops, true) || in_array($this->input('to'), $stops, true)) {
                    $validator->errors()->add('stops', 'Intermediate stops must differ from the origin and destination.');
                }
            },
        ];
    }
}
