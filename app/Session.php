<?php

namespace App;

use Carbon\Carbon;
use App\Traits\ApiUtils;
//use App\Library\Carbon;
use Hamcrest\Core\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\OutletReservationSetting as Setting;

/**
 * @property mixed one_off
 * @property static date
 * @property mixed one_off_date
 * @property mixed timings
 * @property mixed type
 * @property mixed session_name
 */
class Session extends Model {
    
    use ApiUtils;

    /**
     * Session type
     * base on one_off
     * one_off == 0 > normal session
     * one_off == 1 > special session 
     */
    const NORMAL_SESSION  = 0;
    const SPECIAL_SESSION = 1;

    /**
     * Normal session reused on_x day
     * if it's value = 1
     */
    const DAY_AVAILABLE  = 1;

    /**
     * Convert Carbon day const to session day
     */
    const DAY_OF_WEEK = [
        Carbon::MONDAY    => 'on_mondays',
        Carbon::TUESDAY   => 'on_tuesdays',
        Carbon::WEDNESDAY => 'on_wednesdays',
        Carbon::THURSDAY  => 'on_thursdays',
        Carbon::FRIDAY    => 'on_fridays',
        Carbon::SATURDAY  => 'on_saturdays',
        Carbon::SUNDAY    => 'on_sundays'
    ];

    protected  $table = 'session';
    
    public function isSpecial(){
        return $this->one_off == Session::SPECIAL_SESSION;
    }

    public function getTypeAttribute(){
        return $this->one_off;
    }

    /**
     * Relationship with Timing
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function timings(){
        return $this->hasMany(Timing::class, 'session_id', 'id');
    }

    public function scopeNormalSession($query){
        return $query->where('one_off', Session::NORMAL_SESSION);
    }

    public function scopeSpecialSession($query){
        $date_range = $this->availableDateRange();

        return $query->where([
            ['one_off', '=', Session::SPECIAL_SESSION],
            ['one_off_date', '>=', $date_range[0]->format('Y-m-d')],
            ['one_off_date',  '<', $date_range[1]->format('Y-m-d')]
        ]);
    }

    public function scopeAvailableSession($query){
        return $query->normalSession()
            ->orWhere(function($q){$q->specialSession();})
            ->with(['timings' => function($relation){Timing::available($relation)->with('session');}]);
    }

    /**
     * Check normal session available on day x of week
     * @param string $session_day
     * @return bool
     */
    public function availableOnDay($session_day){
        return $this->$session_day == Session::DAY_AVAILABLE;
    }

    /**
     * Assign date to session
     * @warn normal session can reuse for many days
     * > return as collection of session with date
     * > to normalize the consistent
     * > special session after assigned also return a collection 
     * @return \Illuminate\Support\Collection
     */
    public function assignDate(){
        $sessions = collect([]);

        if($this->isSpecial()){
            /* @case one_off_date NULL */  
            $this->date = Carbon::createFromFormat('Y-m-d', $this->one_off_date, Setting::timezone());

            return $sessions->push($this);
        }

        if(!$this->isSpecial()){
            $date_range = Setting::dateRange();

            $current = $date_range[0]->copy();
            while($current->lte($date_range[1])){
                $current_day = $current->dayOfWeek;
                $session_day = Session::DAY_OF_WEEK[$current_day];

                if($this->availableDateRange($session_day)){
                    $as = clone $this;
                    $as->date = $current->copy();

                    $sessions->push($as);
                }

                //increase loop
                $current->addDay();

            }

        }

        return $sessions;
    }


    protected static function boot() {
        parent::boot();

        $outlet_id = session('outlet_id');

        if(!is_null($outlet_id)){
            static::addGlobalScope('base on outlet', function (Builder $builder) use($outlet_id){
                $builder->where('outlet_id', $outlet_id);
            });
        }
    }
    
    

}
