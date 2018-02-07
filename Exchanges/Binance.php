<?php

/**
 * Binance exchange class
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */

namespace Exchanges;
class Binance extends Base {
   
    /**
     * Errors array
     * @var array
     */
    protected $_errors = [];
    
    /*
     * API Credentials
     */
    protected  $_credentials = [
        'key' => '',
        'secret' => ''
    ];
    
    /**
     * Get Balance via API
     */
    public function getBalances() {
        return $this ->apiCall('account/getbalances');
    }
    
    /**
     * Get market order via API
     * @param string $market
     * @return type
     */
    public function getOrders($market = '') {
        $options = [];
        if(!empty($market)) {
            $options['symbol'] = $market;
        }
        return $this ->apiCall('myTrades',$options);
    }
    
    /**
     * Get account info via API
     * @return type
     */
    public function info() {
        return $this -> apiCall('exchangeInfo',[],false);
    }
    
    /**
     * Get credential by key
     * @param string $key
     * @return string
     */
    public function getCred($key) {
        return $this -> _credentials[$key];
    }
    
    /**
     * Perform API call 
     * @param string $uri Endpoint
     * @param array $params
     * @param boolean $signed
     * @return json/string Response
     */
    public function apiCall($uri,$params = [],$signed = true) {
        if($signed) {
            $apikey = $this ->getCred('key');
            $apisecret = $this ->getCred('secret');
            $time = round(microtime(true),3) * 1000;

            $params['timestamp'] = $time;
        }
        $qs = http_build_query($params);
        
        if($signed) {
            $sign = hash_hmac('sha256',$qs,$apisecret);
            $endpoint = 'https://api.binance.com/api/v3/' . $uri . '?' . $qs . '&signature=' . $sign;
        } else {
            $endpoint = 'https://api.binance.com/api/v1/' . $uri . '?' . $qs;
        }
       
        $certificate =  dirname(__FILE__) . '/cacert.pem';
        
		$curlOptions = array (             
			CURLOPT_URL => $endpoint,
			CURLOPT_VERBOSE => 1,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO => $certificate,
            CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => 1
		) ;
        
        if($signed) {
            $curlOptions[CURLOPT_HTTPHEADER] = ['X-MBX-APIKEY: ' . $apikey];
        }
		
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
		  	
		}
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headersRaw = substr($response, 0, $header_size);
        $headersUnformated = explode("\r",$headersRaw);
        $headers = [];
        foreach($headersUnformated as $header) {
            if(stripos($header,'HTTP/') !== false) {
                $parts = explode(' ',$header);
                array_shift($parts);
                $headers['http'] = implode(' ',$parts);
                $headers['http_status'] = $parts[0];
            } else {
                
                $parts = explode(': ',$header);
                if(count($parts) == 2) {
                    $headers[trim($parts[0])] = $parts[1];
                }
            }
        }
        $body = substr($response, $header_size);
        if($headers['http_status'] >= 400) {
            if(strlen($body) > 0) {
                $body = json_decode($body);
                if(isset($body -> code)) {
                    $this -> _errors += [
                        $body -> code => $body -> msg
                    ];
                }
            }
            $this -> _errors += [
                $headers['http_status'] => $headers['http']
            ];
            return false;
        };
        
        
        
        curl_close($ch);
        
		return json_decode($body);
    }
    
    /**
     * Get error array
     * @return array
     */
    public function getErrors() {
        return $this -> _errors;
    }
    
    /**
     * Parse deposits CSV file
     * @param string $file
     */
    public function parseDepositCSV($file) {
        if(is_file($file)) {
            
			$handle = fopen($file,'r');
			$compare = new CryptoCompare();
            $trades = [];
			while(!feof($handle)) {
				$row = fgetcsv($handle);
                if(is_numeric($row[2]) && $row[5] == 'Completed') {
                    $coin = $row[1];
                    $time = strtotime($row[0]);
                    $amount = $row[2];
                    $price = $compare ->getHistoricalPrice($coin, $time);
                    $this -> addBalance($amount,$coin,$time,$price);
                    
                    
                }
            }
            
        }
    }

    /**
     * Parse trades CSV file
     * @param string $file
     * @param string $stop End timestamp
     * @return array Trades
     */
    public function parseTradesCSV($file,$stop = '2017-12-31 23:59:59') {
        if(is_file($file)) {
			$handle = fopen($file,'r');
			
            $trades = [];
            $i = 0;
            $endTime = !empty($stop) ? strtotime($stop) : time();
            
            $time = 0;
			while(!feof($handle) && $time < $endTime) {
                
				$row = fgetcsv($handle);
                //Make sure it's a full trade row
				if(count($row) > 1 ) {
                    if(!empty($row[0]) && $row[8] != 'Canceled' && is_numeric($row[5])) {
                        $i++;
                        if(strlen($row[1]) == 7) {
                            $coin1 = substr($row[1],0,4);
                            $coin2 = substr($row[1],4,3);
                        } else {
                            $coin1 = substr($row[1],0,3);
                            $coin2 = substr($row[1],3,3);
                        }
                        if($row[2] == 'SELL') {
                            $bought = $coin2;
                            $boughtAmount = $row[7];
                            $sold = $coin1;
                            $soldAmount = $row[6];
                        } else {
                            $bought = $coin1;
                            $boughtAmount = $row[6];
                            $sold = $coin2;
                            $soldAmount = $row[7];
                        }
                        $time = strtotime($row[0]);
                        $soldPrice = $this ->getPrice($sold, $time);
                        $boughtPrice = $this -> getPrice($bought,$time);
                        $trade = [
                            'bought' => $bought,
                            'sold' => $sold,
                            'timestamp' => $time,
                            'date' => $row[0],
                            'price' => $boughtPrice,
                            'amount' => $boughtAmount,
                            'sold_amount' => $soldAmount,
                            'sold_price' => $soldPrice,
                            'exchange' => 'binance'
                        ];

                        $trades[$time] = $trade;
                    // Calculate fees
                    } elseif(empty($row[0]) && empty($row[6]) && is_numeric($row[3])) {
                        $feeStr = $row[5];
                        $parts = $this ->extractCoin($feeStr);
                                              
                        $coin = $parts['coin'];
                        $amount = $parts['amount'];
                        
                        if(isset($trades[$time])) {
                            $trade = $trades[$time];
                            if($trade['sold'] == $coin) {
                                $fee = $trade['sold_price'] * $amount;
                            } else {
                                $fee = $trade['price'] * $amount;
                            }
                            
                            if(isset($trade['fee'])) {
                                $fee += $trade['fee'];
                            }
                            $trades[$time]['fee'] = $fee;
                        } else {
                           //var_dump($row);
                        }
                    }
				}
			}    
            
            return $trades;
		}
    }
    
    /**
     * Extract coin symbol and amount 
     * @param string $str
     * @return array
     */
    protected function extractCoin($str) {
        $parts = [];
        $end = strlen($str) - 1;
        while(!is_numeric($str[$end]) && $end > 0) {
            $end--;
        }
        $parts['amount'] = substr($str,0,$end);
        $parts['coin'] = substr($str,$end+1);
        return $parts;
    }
}
