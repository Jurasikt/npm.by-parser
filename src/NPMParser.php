<?php

class NPMParser implements NPMInterface
{
    public function generateStation()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://npm.by/booking/arrival",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "id_station=36&is_waypoint=false",
          CURLOPT_HTTPHEADER => array(
            "x-requested-with: XMLHttpRequest"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            throw new Exception('Can not load the station');
        }
        $data = json_decode($response, true);

        $return = [];
        foreach ($data as $value) {
            $return = array_merge($return, $value);
        }

        curl_setopt($curl, CURLOPT_POSTFIELDS, "id_station=53&is_waypoint=false");
        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            throw new Exception('Can not load the station');
        }

        foreach (json_decode($response, true) as $value) {
            $return = array_merge($return, $value);
        }

        $ret = array();
        foreach ($return as $value) {
            $ret[] = array(
                'id' => $value['id'],
                'name' => array_key_exists('name', $value) ? $value['name'] : $value['value']
                );
        }

        return $ret;
    }

    public function getPlaces($from, $to, $date)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://npm.by/booking/route-time",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query(array('id_departure_station' => $from, 'departure_is_waypoint' => 0,
                'id_arrival_station' => $to, 'arrival_is_waypoint' => 0, 'date' => $date)),
            CURLOPT_HTTPHEADER => array(
            "x-requested-with: XMLHttpRequest"
            ),
        ));
        $response = curl_exec($curl);

        if (!$response || !json_decode($response)) {
            throw new Exception("Error Processing Request");
        }

        foreach (json_decode($response, true) as $val) {
            if (!empty($val)) {
                return $val;
            }
        }
        return $val;
    }

    /**
     * @param array $intime  = (15, 16, 18, 20);
     *
     * @return param for reserve
     */
    public function getFreePlaces($from, $to, $date, $intime)
    {
        $free = $this->getPlaces($from, $to, $date);
        $return = array();
        foreach($free as $item) {

            if ($item['count'] == 0 || !isset($item['time'])) {
                continue;
            }

            array_map(function($x) use ($item, &$return) {

                if ("$x:00" <= $item['time'] && "$x:60" > $item['time']) {
                    
                    $return[$x] = $item;
                }
                return $x;

            }, $intime);

            //if ($return) break;
        }

        $result = [];
        foreach ($intime as $key) {
            if  (array_key_exists($key, $return)) $result[$key] = $return[$key];
        }
        return $result;
    }

    public function oath($user, $pass)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://npm.by/auth/auth",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HEADER => true,
            CURLOPT_POSTFIELDS => http_build_query(array('password' => $pass, 'phone_code' => substr($user, 0, 2),
                'phone' => substr($user, 2))),
            CURLOPT_HTTPHEADER => array(
            "x-requested-with: XMLHttpRequest"
            ),
        ));
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', curl_exec($curl), $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        return $cookies;
    }

    public function checkSid($sid)
    {
        $curl = curl_init('http://npm.by/account/booking');
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => "SID=$sid",
            CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
        ));
        $result = curl_exec($curl);

        if (!$result) {
            return false;
        }

        return (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 302);
    }

    /**
     * reserve_date 11/06/2016, 14:00,
     * reserve_to 65
     * reserve_from 36 
     *
     */
    public function reserve(array $param, $sid)
    {
        if (!$this->checkSid($sid)) {
            throw new Exception('Unauthorized user');
        }

        $param = array_merge($param, array('book' => '', 'reserve_from_is_waypoint' => 0,
            'reserve_passangers' => 1, 'reserve_to_is_waypoint' => 0));

        $curl = curl_init('http://npm.by/booking/reserve');
        curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIE => "SID=$sid",
                CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POSTFIELDS => http_build_query($param),
                CURLOPT_CUSTOMREQUEST => 'POST',
            ));

        $result = curl_exec($curl);

        if (!$result) {
            throw new Exception("The tickets can not be booked");
        }
        curl_close($curl);

        if (preg_match('/name=\"phone\" value=\"(.+)\"/', $result, $phone) && 
                preg_match('/selected=\"selected\">(.+)</', $result, $code) )
        {
            $code = $code[1];
            $phone = $phone[1];
        } else {
            
            throw new Exception("The tickets can not be booked. Incorrect server response 
                http://npm.by/booking/reserve");
        }

        $cookies = [
            'SID' => $sid,
            'backTicket' => 0,
            'fromIdStation' => $param['reserve_from'],
            'toIdStation' => $param['reserve_to'],
            'dateValueDay' => current(explode(',', $param['reserve_date'])),
            'toIsWaypoint' => 0,
            'fromIsWaypoint' => 0,
        ];

        
        $curl = curl_init('http://npm.by/booking-verification/check-phone');
        curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIE => http_build_query($cookies, null, "; ", PHP_QUERY_RFC3986),
                CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POSTFIELDS => http_build_query(['phone_code' => $code, 'phone' => $phone]),
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => ["x-requested-with: XMLHttpRequest"],
            ));

        //$result = curl_exec($curl);
        $result = '{"email_available":"NO","status":"OK"}';
        if (!$result) {
            throw new Exception("The tickets can not be booked. Incorrect server response 
                code when http://npm.by/booking-verification/check-phone");

        }

        if ($data = json_decode($result, true) and $data['status'] == 'OK') {
            return true;
        } else {
            throw new Exception("The tickets can not be booked. Incorrect server response: '$result'.");
        }
    }

}