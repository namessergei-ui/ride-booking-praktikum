<?php

namespace App\Http\Requests;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchTripsRequest extends FormRequest
{
    public function rules(): array
    {
        $city = ['sometimes', 'bail', 'required', 'string', 'max:50', 'alpha_dash:ascii', Rule::exists(City::class, 'id')];

        return [
            'from' => $city,
            'to' => [...$city, 'different:from'],
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'max_price_kopecks' => ['sometimes', 'required', 'integer', 'between:0,100000000'],
            'seats' => ['sometimes', 'required', 'integer', 'between:1,8'],
            'luggage_allowed' => ['sometimes', 'required', 'boolean'],
            'min_rating' => ['sometimes', 'required', 'numeric', 'between:1,5'],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
