<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->smallInteger('experience_years');
            $table->timestampsTz();
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_profile_id')->unique()->constrained();
            $table->string('make', 80);
            $table->string('model', 80);
            $table->string('color', 40);
            $table->smallInteger('passenger_seats');
            $table->timestampsTz();
        });

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained();
            $table->timestampTz('departure_at')->index();
            $table->bigInteger('price_kopecks');
            $table->smallInteger('total_seats');
            $table->smallInteger('available_seats');
            $table->boolean('luggage_allowed');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE trips ADD CONSTRAINT trips_seat_bounds CHECK (total_seats > 0 AND available_seats BETWEEN 0 AND total_seats)');
        DB::statement('ALTER TABLE trips ADD CONSTRAINT trips_nonnegative_price CHECK (price_kopecks >= 0)');

        Schema::create('trip_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('city', 50);
            $table->smallInteger('position');
            $table->unique(['trip_id', 'position']);
            $table->index(['city', 'trip_id', 'position']);
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained();
            $table->foreignId('passenger_id')->constrained('users');
            $table->smallInteger('seats');
            $table->string('status', 12);
            $table->timestampsTz();
            $table->index(['trip_id', 'status', 'id']);
            $table->index(['passenger_id', 'id']);
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_valid_status CHECK (status IN ('REQUESTED', 'CONFIRMED', 'REJECTED', 'CANCELLED'))");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_positive_seats CHECK (seats > 0)');
        DB::statement("CREATE UNIQUE INDEX bookings_one_active_per_passenger ON bookings (trip_id, passenger_id) WHERE status IN ('REQUESTED', 'CONFIRMED')");

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('status', 12);
            $table->timestampTz('created_at');
            $table->index(['booking_id', 'id']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->timestampsTz();
            $table->index(['trip_id', 'id']);
        });

        Schema::create('ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained();
            $table->foreignId('driver_profile_id')->constrained()->index();
            $table->smallInteger('score');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_score_range CHECK (score BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('driver_profiles');
    }
};
