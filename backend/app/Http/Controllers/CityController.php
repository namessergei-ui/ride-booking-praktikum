<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = mb_strtolower($data['q'] ?? '');

        $cities = collect(config('cities'))
            ->filter(fn (array $city, string $id): bool => $query === ''
                || str_contains(mb_strtolower($city['name']), $query) || str_contains($id, $query))
            ->map(fn (array $city, string $id): array => ['id' => $id, ...$city]);

        return response()->json(['data' => $cities->values()]);
    }
}
