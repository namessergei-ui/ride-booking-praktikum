<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDriverProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('driverProfile.vehicle');
        $profile = $user->driverProfile;

        return response()->json(['data' => [
            ...$user->only('id', 'name', 'email'),
            'driver' => $profile ? [
                'experience_years' => $profile->experience_years,
                'vehicle' => $profile->vehicle->only('make', 'model', 'color', 'passenger_seats'),
            ] : null,
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return $this->show($request);
    }

    public function saveDriver(SaveDriverProfileRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $driver = $user->driverProfile()->updateOrCreate([], ['experience_years' => $data['experience_years']]);

            if ($driver->trips()->where('departure_at', '>', now())
                ->where('total_seats', '>', $data['vehicle']['passenger_seats'])->exists()) {
                throw ValidationException::withMessages([
                    'vehicle.passenger_seats' => ['The vehicle must accommodate the seats in all upcoming trips.'],
                ]);
            }

            $driver->vehicle()->updateOrCreate([], $data['vehicle']);
        });

        return $this->show($request);
    }
}
