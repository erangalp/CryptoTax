<?php

/**
 * CryptoCompare API wrapper
 * 
 * @docs https://www.cryptocompare.com/api/
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
class CryptoCompare {
    
    /**
     * Get historical coin price
     * - Averages out 1 hour close / open prices
     * 
     * @param string $symbol Coin symbol
     * @param int $time UNIX Timestamp
     * @return float Price in USD
     */
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
