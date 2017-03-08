<?php

namespace App\Http\Controllers;

use Validator;
use App\Outlet;
use App\Timing;
use App\Session;
use Carbon\Carbon;
use App\Reservation;
use App\Traits\ApiUtils;
use App\Traits\ApiResponse;
use App\Http\Requests\ApiRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\OutletReservationSetting as Setting;

class BookingController extends Controller {

    use ApiUtils;
    use ApiResponse;

    const DATES_WITH_AVAILABLE_TIME_FILE_NAME = 'dates_with_available_time_';

    /** @var  Collection $valid_reservations */
    public $valid_reservations;

    /** @var  int $reservations_pax_size */
    public $reservations_pax_size;
    
    public $recalculate;

    public function __construct(){
        //dd(env('RECALCULATE'));
        if(env('RECALCULATE') == true){
            $this->recalculate = true;
        }
    }

    public function availableTime(){
        $available_sessions = Session::availableSession()->get()->map->assignDate()->collapse();
        $sessions_by_date   = $available_sessions->groupBy(function($s){return $s->date->format('Y-m-d');});
        $timings_by_date    = $sessions_by_date->map(function($g){return $g->map->timings->collapse();});

        $date_with_available_time = $this->loadDatesWithAvailableTimeFromCache() ?: $this->buildDatesWithAvailableTime($timings_by_date);

        /**
         * Change chunk time capacity base on already reservations
         */
        $this->valid_reservations = Reservation::validGroupByDateTimeCapacity();
        //$this->reservations_pax_size = session('reservation_pax_size', 7);

        $date_with_available_time->each(function($group, $date_string){
            $group->each(function($chunk) use($date_string){
                $reserved_on_day = isset($this->valid_reservations[$date_string]);
                $reserved_on_time = $reserved_on_day && isset($this->valid_reservations[$date_string][$chunk->time]);

                if($reserved_on_time){
                    foreach(Timing::CAPACITY_X as $cap_x){
                        try{
                            $reserved_cap = $this->valid_reservations[$date_string][$chunk->time][$cap_x];
                            $chunk->$cap_x = $chunk->$cap_x - $reserved_cap;
                        }catch(\Exception $e){}
                    }
                }
            });
        });

        /**
         * Base on user booking size, filter tables are busy
         * Only accpet capacity > 0 as available
         */
        $dates_with_available_time_capacity =
            $date_with_available_time->map->filter(function($t){

                $reservation_pax_size = $this->getReservationPaxSize();
                $t->max_pax = $t->max_pax ?: Setting::TIMING_MAX_PAX;

                $cap_name = Timing::getCapacityName($reservation_pax_size);
                $is_cap_available = ($t->$cap_name > 0) && ($t->max_pax >= $reservation_pax_size);
                //$is_cap_available = ($t->$cap_name > 0);

                return $is_cap_available;
            })
            ->map->values();


        return $dates_with_available_time_capacity;
    }

    public function loadDatesWithAvailableTimeFromCache(){
        if($this->shouldUseCache()){
            Log::info('Using cache');
            $today = Carbon::now(Setting::timezone());
            $today_string = $today->format('Y-m-d');

            $file_name = BookingController::DATES_WITH_AVAILABLE_TIME_FILE_NAME . $today_string;

            $va =  Cache::get($file_name, null);

            return $va;
        }

        return null;
    }

    public function buildDatesWithAvailableTime($timings_by_date){
        //dd('build new');
        $return =
            $timings_by_date->map(function($group, $date_string){
                /**
                 * Base on arrival time & interval, each timing chunked into small piece
                 */
                $chunks  = $group->map->chunk->collapse();

                $ordered_chunks = $chunks->sortBy(function($c){return $this->getMinutes($c->time);})->values();

                /**
                 * Special timing chunk will override on normal one
                 */
                $merged_chunks =
                    $ordered_chunks->reduce(function($carry, $item){
                        /**
                         * Push first item
                         */
                        $pre_item = $carry->last();
                        //should return immediately to prevent call on null of following step
                        if(is_null($pre_item)){
                            $carry->push($item);
                            return $carry;
                        }

                        $alreday_has = $carry->filter(function($last_item)use($item){return $last_item->time == $item->time;})->count() > 0;

                        /**
                         * overlap item is special, so override on pre_item
                         * bcs item sort out by order
                         * 2 item at same time > special item chose
                         */
                        $pre_item = $carry->last();
                        $new_item_is_special_than_pre = ($pre_item->session_type == Session::NORMAL_SESSION
                            && $item->session_type == Session::SPECIAL_SESSION);

                        $overlap_item_is_special = $alreday_has && $new_item_is_special_than_pre;

                        if($overlap_item_is_special)
                            $carry->pop();

                        /**
                         * Decide push item
                         */
                        $push_new = !$alreday_has || $overlap_item_is_special;

                        if($push_new)
                            $carry->push($item);


                        return $carry;
                    }, collect([]));

                /**
                 * Only push item which satisfied its own interval
                 * After chunk with minimum interval, to match special over normal session
                 * Need loop back to get only which statisfied its own interval
                 */
                $fixed_interval_chunks =
                    $merged_chunks->reduce(function($carry, $item){
                        /**
                         * Push first item
                         */
                        $pre_item = $carry->last();
                        //should return immediately to prevent call on null of following step
                        if(is_null($pre_item)){
                            $carry->push($item);
                            return $carry;
                        }

                        /**
                         * Satisfied interval >>> should push
                         */
                        $delta_time_with_pre = abs(Session::getMinutes($pre_item->time) - Session::getMinutes($item->time));
                        $satisfied_interval  = $delta_time_with_pre >= $pre_item->interval_minutes;

                        /**
                         * New item must pushed, when item special than pre_item
                         */
                        $new_item_is_special_than_pre = ($pre_item->session_type == Session::NORMAL_SESSION
                            && $item->session_type == Session::SPECIAL_SESSION);
                        $new_item_must_pushed = $new_item_is_special_than_pre && !$satisfied_interval;

                        if($new_item_must_pushed)
                            $carry->pop();

                        /**
                         * Respect first arrival
                         */
                        $delta_time_with_first_arrival = abs(Session::getMinutes($item->time) - Session::getMinutes($item->first_arrival_time));
                        $respect_first_arrival = ($delta_time_with_first_arrival % $item->interval_minutes) == 0;

                        /**
                         * Check push new
                         */
                        $push_new = ($satisfied_interval || $new_item_must_pushed) && $respect_first_arrival;

                        if($push_new)
                            $carry->push($item);

                        return $carry;
                    }, collect([]));

                /**
                 * Get buffer config
                 */
                /** @var string $buffer_config */
                $buffer_config = Setting::bufferConfigAsMap();
                $min_hours_slot_time    = $buffer_config('MIN_HOURS_IN_ADVANCE_SLOT_TIME');
                $min_hours_session_time = $buffer_config('MIN_HOURS_IN_ADVANCE_SESSION_TIME');

                $satisfied_prior_time_chunks = $fixed_interval_chunks;

                /**
                 * Compute day time on today with current checking session time
                 */
                $today = Carbon::now(Setting::timezone());
                $today_in_hour = $this->getMinutes($today->format('H:i:s')) / 60;
                $current_date =  Carbon::createFromFormat('Y-m-d', $date_string, Setting::timezone());
                $on_same_day = $current_date->diffInDays($today) == 0;

                /**
                 * Care on hours, only check for session on same day with today
                 */
                if($on_same_day){
                    $satisfied_prior_time_chunks =
                        $fixed_interval_chunks->filter(function($item) use($min_hours_slot_time, $min_hours_session_time, $today_in_hour){
                            $item_in_hour = $this->getMinutes($item->time) / 60;
                            $diff_in_hour = $item_in_hour - $today_in_hour;

                            $satisfied_in_advance_slot_time    = $diff_in_hour >= $min_hours_slot_time;
                            $satisfied_in_advance_session_time = $diff_in_hour >= $min_hours_session_time;

                            return $satisfied_in_advance_slot_time
                                   && $satisfied_in_advance_session_time;
                        })->values();
                }


                return $satisfied_prior_time_chunks;
            });

        /**
         * Save cache before move on
         */
        $today = Carbon::now(Setting::timezone());
        $today_string = $today->format('Y-m-d');

        $file_name = BookingController::DATES_WITH_AVAILABLE_TIME_FILE_NAME . $today_string;
        //expire in day
        Cache::put($file_name, $return, 24 * 60);

        return $return;
    }

    /**
     * New special session come on that day
     * Or change on normal session
     * Let to cache not updated
     * Should recalculate
     */
    public function shouldUseCache(){
        if($this->recalculate)
            return false;

        $session_has_new_update = Session::hasNewUpdate()->get()->count() > 0;

        if($session_has_new_update)
            return false;

        $timing_has_new_update  = Timing::hasNewUpdate()->get()->count() > 0;

        if($timing_has_new_update)
            return false;

        return true;
    }

    /**
     * Get on reservation pax size to assign default
     */
    public function getReservationPaxSize(){
        $val = $this->reservations_pax_size;

        if(is_null($val))
            return Setting::RESERVATION_PAX_SIZE;

        return $val;
    }

    public function setReservationPaxSize($val){
        $this->reservations_pax_size = $val;
    }

    /**
     * Booking Form step 1
     * @param ApiRequest $req
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getBookingForm(ApiRequest $req){
        if($req->method() == 'POST'){
            //return $this->apiResponse($req->all());
            /* @var Validator $validator*/
            $validator = Validator::make($req->all(), [
                'outlet_id'    => 'required',
                'adult_pax'    => 'required',
                'children_pax' => 'required'
            ]);

            if($validator->fails()){
                return $this->apiResponse($req->all(), 422, $validator->getMessageBag()->toArray());
            }

            /**
             * Outlet id as reuse over & over through query builder
             * Store in session for this request
             * Any further call, consider the same outlet_id
             */
            $outlet_id = $req->get('outlet_id');
            session(compact('outlet_id'));

            /**
             *
             */
            $reservation_pax_size = $req->get('adult_pax') + $req->get('children_pax');
            $this->setReservationPaxSize($reservation_pax_size);


            $available_time = $this->availableTime();

            return $this->apiResponse($available_time);
        }



        //handle get
        $outlets = Outlet::all();

        return view('reservations.booking-form', compact('outlets'));
    }

    /**
     * Booking Form step 2
     * @param ApiRequest $req
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getBookingForm2(ApiRequest $req){
        if($req->method() == 'POST' && $req->get('step') == 'booking-form'){
            $validator = Validator::make($req->all(), [
                'outlet_id'        => 'required',
                'adult_pax'        => 'required',
                'outlet_name'      => 'required',
                'children_pax'     => 'required',
                'reservation_date' => 'required',
                'reservation_time' => 'required|regex:/\d+:\d{2}/'
            ]);

            if($validator->fails()){
                return $this->apiResponse($req->all(), 422, $validator->getMessageBag()->toArray());
            }

            $reservation_info = $req->only(['outlet_id', 'outlet_name', 'adult_pax', 'children_pax', 'reservation_date', 'reservation_time']);

            session(compact('reservation_info'));

            //return $reservation_info;

            return redirect('booking-form-2');
        }

        $reservation_info = session('reservation_info', []);

        try{
            $reservation_info['pax_size'] = $reservation_info['adult_pax'] +  $reservation_info['children_pax'];

            $date     = Carbon::createFromFormat('Y-m-d', $reservation_info['reservation_date'], Setting::timezone());
            $reservation_info['date'] = $date->format('M d Y');
        }catch(\Exception $e){}

        if($req->method() == 'POST' && $req->get('step') == 'booking-form-2'){
            $validator = Validator::make($req->all(), [
                'salutation'       => 'required',
                'first_name'       => 'required',
                'last_name'        => 'required',
                'email'            => 'required',
                'phone'            => 'required'
            ]);

            if($validator->fails()){
                return $this->apiResponse($req->all(), 422, $validator->getMessageBag()->toArray());
            }

            $reservation_info = $req->only(['salutation', 'first_name', 'email', 'phone', 'customer_remarks']);

            $reservation_info = array_merge(session('reservation_info', []), $reservation_info);
            
            $reservation_info['reservation_timestamp'] = "{$reservation_info['reservation_date']} {$reservation_info['reservation_time']}:00";

            $reservation_info['reservation_code']=rand(100000,999999);

            $reservation = new Reservation($reservation_info);
            $reservation->status = Reservation::CONFIRMED;//
            $reservation->save();

            //send out an SMS
            $message="Your reservation at ". $reservation_info['outlet_name']. " on ".$reservation_info['reservation_timestamp']. " has been received. \nReservation code: ".  $reservation_info['reservation_code'];
            $this->sendOverHoiio($reservation_info['phone'],$message,"SPIZE");

            //return $reservation_info;
            return view('reservations.booking-summary')->with(compact('reservation'));
        }
        
        return view('reservations.booking-form-2')->with(compact('reservation_info'));
    }

    private function _padTelephone($telephone){
        if (substr($telephone,0,3)!="+65"){
            if (substr($telephone,0,2)!="65"){
                $telephone="+65".$telephone;
            }else{
                $telephone="+".$telephone;
            }
        }
        return $telephone;
    }
    private function sendOverHoiio($telephone,$message,$sender_name){

        //pad the phone number

        $telephone=$this->_padTelephone($telephone);



        $hoiioAppId = "n0rwoAWlLNvTZpXo";
        $hoiioAccessToken = "OsiquwPsGPkpXrxV";
        $sendSmsURL = "https://secure.hoiio.com/open/sms/send";
        $fields = array(
            'app_id' => urlencode($hoiioAppId),
            'access_token' => urlencode($hoiioAccessToken),
            'dest' => urlencode($telephone),     // send SMS to this phone

            'msg' => urlencode($message),                // message content in SMS
            'sender_name'=>$sender_name
        );

        // form up variables in the correct format for HTTP POST
        $fields_string = "";
        foreach($fields as $key => $value)
            $fields_string .= $key . '=' . $value . '&';

        $fields_string = rtrim($fields_string,'&');

        /* initialize cURL */
        $ch = curl_init();

        /* set options for cURL */
        curl_setopt($ch, CURLOPT_URL, $sendSmsURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

        /* execute HTTP POST request */
        $raw_result = curl_exec($ch);
        $result = json_decode($raw_result);     // parse JSON formatted result

        /* close connection */
        curl_close($ch);


        if($result->status == "success_ok") {
            return true;
        } else {
            return false;
        }
    }
}
