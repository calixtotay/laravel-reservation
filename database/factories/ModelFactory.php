<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(App\ReservationUser::class, function (Faker\Generator $faker) {
    static $password;

    return [
        'user_name' => $faker->userName,
        'password' => $password ?: $password = bcrypt('password'),
        'email' => $faker->unique()->safeEmail,
        'display_name' => $faker->name,
        'secret_token' => str_random(10),
    ];
});
