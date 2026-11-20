<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = mb_strtolower($data['q'] ?? '');

        $cities = City::orderBy('id')->get()
            ->filter(fn (City $city): bool => str_contains(mb_strtolower($city->name), $query)
                || str_contains($city->id, $query))
            ->map(fn (City $city): array => $city->only('id', 'name', 'lat', 'lon'));

        return response()->json(['data' => $cities->values()]);
    }
}
