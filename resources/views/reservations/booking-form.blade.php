@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="box">
            @component('reservations.header')
            @slot('title')
            Make a Reservation at <span class="r-name"> <a href="{{ url('') }}" target="_blank">Spize (Bedok)</a></span>
            <p class="sub"></p>
            @endslot
            @endcomponent
            <div id="check-availability" class="content">
                <form id="booking-form" action="{{ url('booking-form-2') }}" method="POST">
                    <input type="hidden" name='step' value="booking-form">
                    <div class="rid-select">
                        <input type="hidden" name='outlet_name' value="{{ $outlets->first()->name }}">
                        <select name="outlet_id" id="rid" title="spize" class="form-control">
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}"
                                        @if($loop->first)
                                        selected
                                        @endif
                                >{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="selectors cf">
                        <div id="adults-wrap">
                           <label for="adults">Adults</label>
                           <select name="adult_pax" class="form-control">
                               <option value="1" selected>1</option>
                               <option value="2">2</option>
                               <option value="3">3</option>
                               <option value="4">4</option>
                              <option value="5" >5</option>
                              <option value="6">6</option>
                              <option value="7">7</option>
                              <option value="8">8</option>
                              <option value="9">9</option>
                              <option value="10">10</option>
                              <option value="11">11</option>
                              <option value="12">12</option>
                              <option value="13">13</option>
                              <option value="14">14</option>
                              <option value="15">15</option>
                           </select>
                        </div>
                        <div id="children-wrap">
                           <label for="children">Children</label>
                           <select name="children_pax" class="form-control">
                              <option value="0" selected>0</option>
                              <option value="1">1</option>
                              <option value="2">2</option>
                              <option value="3">3</option>
                              <option value="4">4</option>
                              <option value="5">5</option>
                              <option value="6">6</option>
                              <option value="7">7</option>
                              <option value="8">8</option>
                              <option value="9">9</option>
                           </select>
                        </div>
                    </div>

                    <div class="datetime cf">
                        <div class="clear"></div>
                        <div id="calendar-box" align="center"></div>
                        <div id="dt-choice" class="cf">
                            <label id="reservation_date">{{ date('M d Y') }}</label>
                            <input type="hidden" name="reservation_date" value="">
                            <select name="reservation_time" class="form-control">
                                <option>N/A</option>
                            </select>
                        </div>
                        <div class="agree-box cf">
                            <div class="checkbox cf">
                                <label for="agree_box">I acknowledge that this is a waitlisted reservation and is
                                    subjected
                                    to
                                    the restaurant's confirmation.</label>
                                <input id="agree_box" type="checkbox" name="agree_box" value="1"
                                       class="form-control agree-check" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions cf" id="bottom_room">
                        <button id="btn_next" class="btn btn-primary" style="float:right">Next</button>
                    </div>
                </form>
            </div>
        </div>{{--box--}}
    </div>{{--container--}}
    <style>
        #bottom_room {
            text-align: center;
            margin: 20px auto;
            border: 0;
            width: 330px;
            bottom: 0;
        }
    </style>
    <div class="modal fade" id="ajax-dialog">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Searching for available time</h4>
                </div>
                <div class="modal-body center">
                    {{--<div style="width: 100px; display: inline-block">--}}
                    <div style="width: 184px; display: inline-block">
                        {{--<img src="{{ url('images/gears.svg') }}">--}}
                        <img src="{{ url('images/hourglass.svg') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <hr>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@endsection

@push('script')
    <script src="{{ url(substr(mix('js/calendar.js'), 1)) }}"></script>
    <script src="{{ url(substr(mix('js/booking-form.js'), 1)) }}"></script>
    <script>
        //let c = $('#calendar-box').Calendar();
    </script>
@endpush