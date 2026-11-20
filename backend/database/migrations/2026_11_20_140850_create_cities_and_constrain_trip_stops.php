<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->string('id', 50)->primary();
            $table->string('name', 120);
            $table->decimal('lat', 7, 4);
            $table->decimal('lon', 7, 4);
        });

        DB::statement('ALTER TABLE cities ADD CONSTRAINT cities_coordinate_bounds CHECK (lat BETWEEN -90 AND 90 AND lon BETWEEN -180 AND 180)');

        DB::table('cities')->insert([
            ['id' => 'ekaterinburg', 'name' => 'Екатеринбург', 'lat' => '56.8389', 'lon' => '60.6057'],
            ['id' => 'rezh', 'name' => 'Реж', 'lat' => '57.3739', 'lon' => '61.3831'],
            ['id' => 'alapaevsk', 'name' => 'Алапаевск', 'lat' => '57.8465', 'lon' => '61.6980'],
            ['id' => 'nevyansk', 'name' => 'Невьянск', 'lat' => '57.4913', 'lon' => '60.2144'],
            ['id' => 'nizhny-tagil', 'name' => 'Нижний Тагил', 'lat' => '57.9101', 'lon' => '59.9813'],
            ['id' => 'pervouralsk', 'name' => 'Первоуральск', 'lat' => '56.9081', 'lon' => '59.9429'],
            ['id' => 'revda', 'name' => 'Ревда', 'lat' => '56.7987', 'lon' => '59.9083'],
            ['id' => 'polevskoy', 'name' => 'Полевской', 'lat' => '56.4437', 'lon' => '60.1875'],
            ['id' => 'sysert', 'name' => 'Сысерть', 'lat' => '56.5025', 'lon' => '60.8103'],
            ['id' => 'kamensk-uralsky', 'name' => 'Каменск-Уральский', 'lat' => '56.4149', 'lon' => '61.9188'],
            ['id' => 'tyumen', 'name' => 'Тюмень', 'lat' => '57.1522', 'lon' => '65.5272'],
            ['id' => 'chelyabinsk', 'name' => 'Челябинск', 'lat' => '55.1600', 'lon' => '61.4026'],
            ['id' => 'perm', 'name' => 'Пермь', 'lat' => '58.0105', 'lon' => '56.2502'],
        ]);

        Schema::table('trip_stops', function (Blueprint $table): void {
            $table->foreign('city')->references('id')->on('cities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_stops', function (Blueprint $table): void {
            $table->dropForeign(['city']);
        });

        Schema::dropIfExists('cities');
    }
};
