<?php

namespace Exchanges;

/**
 * Bittrex exchange class
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
class Bittrex extends Base {
    
    /**
     * API credentials
     * @var array
     */
    protected  $_credentials = [
        'key' => '',
        'secret' => ''
    ];
    
    /**
     * Get balances via API
     */
    public function getBalances() {
        return $this ->apiCall('account/getbalances');
    }
    
    /**
     * Get order history
     * @param string $market
     * @return array
     */
    public function getOrders($market = '') {
        $options = [];
        if(!empty($market)) {
            $options['market'] = $market;
        }
        return $this ->apiCall('account/getorderhistory',$options);
    }
    
    /**
     * Get API credential
     * @param string $key
     * @return string
     */
    public function getCred($key) {
        return $this -> _credentials[$key];
    }
    
    /**
     * Make API call
     * @param string $uri Endpoint
     * @param array $options
     * @return json/string Response
     */
    public function apiCall($uri,$options = []) {
        $apikey = $this ->getCred('key');
        $apisecret = $this ->getCred('secret');
        $nonce=time();
        $params = [
            'apikey' => $apikey,
            'nonce' => $nonce
        ];
        if(!empty($options)) {
            $params += $options;
        }
        $endpoint = 'https://bittrex.com/api/v1.1/' . $uri . '?apikey=' . $apikey . '&nonce=' . $nonce;
        $sign = hash_hmac('sha512',$endpoint,$apisecret);
        $certificate =  './cacert.pem';
		$curlOptions = array (
            CURLOPT_HTTPHEADER => ['apisign: ' . $sign],
			CURLOPT_URL => $endpoint,
			CURLOPT_VERBOSE => 1,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO => $certificate,
			CURLOPT_RETURNTRANSFER => 1
		) ;
		
		$ch = curl_init();
		curl_setopt_array($ch,$curlOptions);
		$response = curl_exec($ch);
		
		if (curl_errno($ch)) {
			$this -> _errors += array (
				'curl_error_no' => curl_errno($ch),
				'curl_error_msg' => curl_error($ch)
			);
			return false;
		} else  {
		  	curl_close($ch);
		}
        
		return json_decode($response);
    }
    
    /**
     * Parse trades CSV file
     * @param string $file CSV file location
     * @param string $stop End timestamp
     * @return array Trades
     */
    public function parseTradesCSV($file,$stop = '2017-12-31 23:59:59') {
        if(is_file($file)) {
            
            $handle = fopen($file,'r');
            $trades = [];
            $i = 0;
            $time = 0;
            $endTime = strtotime($stop);
            
			while(!feof($handle) && $i < 450) {  
                $i++;
                
                $row = fgetcsv($handle);
                
			
                $time = strtotime($row[8]);
                //Make sure it's a trade row
				if(count($row) > 1 && strlen($row[0]) > 30 && $time < $endTime) {
                   
                    $coins = explode('-',$row[1]);
               
                    if(stripos($row[2],'SELL') !== false) {
                        $bought = $coins[0];
                        $boughtAmount = $row[6];
                        $sold = $coins[1];
                        $soldAmount = $row[3];
                    } else {
                        $bought = $coins[1];
                        $boughtAmount = $row[3];
                        $sold = $coins[0];
                        $soldAmount = $row[6];
                    }
                   
                    
                    $soldPrice = $this ->getPrice($sold, $time);
                    
                    $boughtPrice = $this -> getPrice($bought,$time);
                                  
                    
                    if(empty($soldPrice) || empty($boughtPrice)) {
                        if($soldPrice > 0) {
                            $boughtPrice = $soldPrice * $soldAmount / $boughtAmount;
                        } else if($boughtPrice > 0) {
                            $soldPrice = $boughtPrice * $boughtAmount / $soldAmount;
                        }
                    }
                    $fee = $coins[0] == $sold ? ($soldPrice * $row[5]) : ($boughtPrice * $row[5]);
                    $trade = [
                        'bought' => $bought,
                        'sold' => $sold,
                        'timestamp' => $time,
                        'date' => $row[8],
                        'price' => $boughtPrice,
                        'amount' => $boughtAmount,
                        'sold_amount' => $soldAmount,
                        'sold_price' => $soldPrice,
                        'fee' => $fee,
                        'exchange' => 'bittrex'
                    ];

                    $trades[$time] = $trade;
                }
            }
            
           
            return $trades;
        }
    }
}
