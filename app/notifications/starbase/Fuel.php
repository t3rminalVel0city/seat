<?php
/*
The MIT License (MIT)

Copyright (c) 2014 eve-seat

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

namespace Seat\Notifications\Starbase;

use Seat\Notifications\BaseNotify;

class StarbaseFuel extends BaseNotify
{

    public static function update()
    {

        $starbases = \EveCorporationStarbaseDetail::all();

        $fuel_needed = array();

        // Set some base usage values ...

        $usage = 0;
        $stront_usage =0;

        // ... fuel for non sov towers ...

        $large_usage = 40;
        $medium_usage = 20;
        $small_usage = 10;

        // ... and sov towers

        $sov_large_usage = 30;
        $sov_medium_usage = 15;
        $sov_small_usage = 8;

        // Stront usage

        $stront_large = 400;
        $stront_medium = 200;
        $stront_small = 100;

        // basically, here we check if the names Small/Medium exists in the tower name. Then,
        // if the tower is in the sov_tower array, set the value for usage

        foreach($starbases as $starbase) {

            $sov_tower = false;

            $alliance_id = \DB::table('corporation_corporationsheet')
                ->where('corporationID', $starbase->corporationID)
                ->pluck('allianceID');

            if($alliance_id) {

                $location = \DB::table('map_sovereignty')
                    ->select('solarSystemID')
                    ->where('factionID', 0)
                    ->where('allianceID', $alliance_id);

                if($location)
                    $sov_tower = true;
            }

            if (strpos($starbase->typeName, 'Small') !== false) {

                $stront_usage = $stront_small;

                if ($sov_tower)
                    $usage = $sov_small_usage;
                else
                    $usage = $small_usage;

            } elseif (strpos($starbase->typeName, 'Medium') !== false) {

                $stront_usage = $stront_medium;

                if ($sov_tower)
                    $usage = $sov_medium_usage;
                else
                    $usage = $medium_usage;

            } else {

                $stront_usage = $stront_large;

                if ($sov_tower)
                    $usage = $sov_large_usage;
                else
                    $usage = $large_usage;

            }

            if(\Carbon\Carbon::now()->addHours($starbase->fuelBlocks / $usage)->lte(\Carbon\Carbon::now()->addDays(3)))
                $fuel_needed = array_add($fuel_needed, $starbase->id, array($starbase->fuelBlocks, \Carbon\Carbon::now()->addHours($starbase->fuelBlocks / $usage)->diffForHumans(), $starbase->corporationID));
        }

        $pos_users = \Sentry::findAllUsersWithAccess('pos_manager');

        if(!empty($fuel_needed)) {
            foreach($fuel_needed as $pos_needs_fuel_id => $pos_needs_fuel_data) {
                foreach($pos_users as $pos_user) {
                    if(BaseNotify::canAccessCorp($pos_user->id, $pos_needs_fuel_data[2])) {
                        $notification_type = "POS";
                        $notification_title = "Low Fuel!";
                        $notification_text = "One of your starbases has only ".$pos_needs_fuel_data[0]." fuel blocks left, this will last for ".$pos_needs_fuel_data[1];
                        $hash = BaseNotify::makeNotificationHash($pos_user->id, $notification_type, $notification_title, $notification_text);

                        $check = \SeatNotification::where('hash', '=', $hash)->exists();

                        if(!$check) {

                            $notification = new \SeatNotification;
                            $notification->user_id = $pos_user->id;
                            $notification->type = $notification_type;
                            $notification->title = $notification_title;
                            $notification->text = $notification_text;
                            $notification->hash = $hash;
                            $notification->save();
                        }
                    }
                }
            }
        }
    }
}
