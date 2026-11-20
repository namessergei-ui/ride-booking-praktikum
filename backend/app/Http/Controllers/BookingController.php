<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function mine(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['trip_id' => ['sometimes', 'required', 'integer', 'min:1']]);
        $bookings = Booking::with('trip.stops.location')->where('passenger_id', $request->user()->id);

        if (isset($filters['trip_id'])) {
            $bookings->where('trip_id', $filters['trip_id']);
        }

        return BookingResource::collection($bookings->latest('id')->paginate(20)->withQueryString());
    }

    public function index(Request $request, Trip $trip): AnonymousResourceCollection
    {
        abort_unless($trip->isDrivenBy($request->user()), 403, 'Only the trip driver can list its bookings.');

        return BookingResource::collection($trip->bookings()->latest('id')->paginate(20));
    }

    public function show(Request $request, Trip $trip, Booking $booking): BookingResource
    {
        $user = $request->user();
        abort_unless($booking->passenger_id === $user->id || $trip->isDrivenBy($user), 403, 'You cannot view this booking.');

        return new BookingResource($booking->load('auditEvents'));
    }

    public function store(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate(['seats' => ['required', 'integer', 'between:1,8']]);
        $booking = $this->bookings->request($trip, $request->user(), (int) $data['seats']);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function confirm(Request $request, Trip $trip, Booking $booking): BookingResource
    {
        return new BookingResource($this->bookings->transition($trip, $booking, $request->user(), BookingStatus::Confirmed));
    }

    public function reject(Request $request, Trip $trip, Booking $booking): BookingResource
    {
        return new BookingResource($this->bookings->transition($trip, $booking, $request->user(), BookingStatus::Rejected));
    }

    public function cancel(Request $request, Trip $trip, Booking $booking): BookingResource
    {
        return new BookingResource($this->bookings->transition($trip, $booking, $request->user(), BookingStatus::Cancelled));
    }

    public function rate(Request $request, Trip $trip, Booking $booking): JsonResponse
    {
        abort_unless($booking->passenger_id === $request->user()->id, 403, 'Only the booking owner can rate this trip.');
        abort_unless($booking->status === BookingStatus::Confirmed && $trip->departure_at->isPast(), 409, 'Rating requires a confirmed booking and a departed trip.');

        $data = $request->validate(['score' => ['required', 'integer', 'between:1,5']]);
        $rating = $booking->rating()->updateOrCreate([], [
            'driver_profile_id' => $trip->driver_profile_id,
            'score' => $data['score'],
        ]);

        return response()->json(['data' => $rating->only('booking_id', 'score')]);
    }
}
