@extends('layouts.app')

@section('content')
<div class="container">
    <div class="box">
        @component('reservations.header')
            @slot('title')
                <span class="r-name"><a href="{{ url('') }}" target="_blank">Spize (Bedok)</a></span>
                <p class="sub">Your reservation has been made! <br>A confirmation SMS has been sent.</p>
            @endslot
        @endcomponent
        <div id="reservation-details" class="content legend">

            <h6 class="r-title">Your Reservation Information</h6>
            <table id="r-rsrve-info">
                <tbody>

                <tr>
                    <td><label>Date &amp; Time:</label></td>
                    <td>{{ $reservation->date->format('M d Y') }}, {{ $reservation->date->format('H:i') }}</td>
                </tr>
                <tr>
                    <td><label>People:</label></td>
                    <td>{{ $reservation['adult_pax'] }} Adults</td>
                    <td>{{ $reservation['children_pax'] }} Children</td>
                </tr>

                <tr>
                    <td><label>Confirmation ID:</label></td>
                    <td>{{ $reservation->confirm_id }}</td>
                </tr>

                </tbody>
            </table>

            <table id="r-dnr-info">
                <tbody>
                <tr>
                    <td><label>Name:</label></td>
                    <td>{{ $reservation['first_name'] }} {{ $reservation['last_name'] }}</td>
                </tr>
                <tr>
                    <td><label>Phone Number:</label></td>
                    <td>{{ $reservation['phone_country_code'] }} {{ $reservation['phone'] }}</td>
                </tr>
                <tr>
                    <td><label>Email:</label></td>
                    <td>{{ $reservation['email'] }}</td>
                </tr>
                <tr>
                    <td><label>Special Request:</label></td>
                    <td><p>{{ $reservation['customer_remarks'] ?: '' }}</p></td>
                </tr>
                </tbody>
            </table>
            <div class="form-actions cf legend">
                <a href="{{ url('') }}" type="button" class="btn btn-success">Home</a>
            </div>
        </div>
    </div>
</div>
@endsection