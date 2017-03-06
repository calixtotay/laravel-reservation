<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;

Route::get('/', function(){return view('index');});
Route::get('/home', function(){return 'fuck';});
Route::get('/fuck', function(){return 'fuck you';});

Auth::routes();

//var_dump(auth());
Route::get('test', function(){
    return ['name' => 'Anh Le Hoang'];
});

Route::post('booking/date-available', 'BookingController@dateAvailable');

Route::get('test/session-scope', function(){
//    $s = App\Session::specialSession()->get();
//    $s = App\Session::normalSession()->get();
    $s = App\Session::available();
    return $s;
});


Route::get('test/session/buildStep1', function(){
//    $s = App\Session::specialSession()->get();
//    $s = App\Session::normalSession()->get();
    $s = App\Session::buildStep1();
    return $s;
});

Route::get('test/session/buildStep2', function(){
    $available_days = App\Session::buildStep2();

    return $available_days;
});

Route::get('test/session/buildStep3', function(App\Http\Controllers\BookingController $c){
//    $available_days = App\Session::availableV2();
    //$available_days = App\Session::availableTime();
    //$available_days = App\Session::availableSession()->get()->map->assignDate()->collapse();
    $c->recalculate = true;
    $data = $c->availableTime();

//    $data = json_encode($data);
    return view('reservations.booking-form')->with(compact('data'));

    return $data;
});

Route::get('test', function(App\Http\Controllers\BookingController $c){

    //return \App\Timing::hasNewUpdate()->get()->count();

    //return view('reservations.booking-summary');

    return view('reservations.booking-form-2');
});