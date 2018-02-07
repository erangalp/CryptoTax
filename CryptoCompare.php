<?php

/**
 * @docs https://www.cryptocompare.com/api/
 */
class CryptoCompare {
    public function getHistoricalPrice($symbol,$time) {
        
        // Get the hour that includes the timestamp
        $time = ceil($time / 3600) * 3600;
        if($symbol == 'IOTA') {
            $symbol = 'IOT';
        }
       
        $endpoint = 'https://min-api.cryptocompare.com/data/histohour?fsym=' . $symbol . '&tsym=USD&aggregate=1&limit=1&e=CCCAGG&toTs=' . $time;
        $response = file_get_contents($endpoint);
        
        $response = json_decode($response);
        if(!isset($response -> Data[1])) {
           return false;
        }
        $price = $response -> Data[1];
        
        // Return average of open / close for that hour
        return (($price -> close + $price -> open) / 2);
        
    }
}
