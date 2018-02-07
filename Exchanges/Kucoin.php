<?php
namespace Exchanges;

/**
 * Kucoin exchange class
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
class Kucoin extends Base {
    
    /**
     * API base URI
     * @var string
     */
    protected $_host = "https://api.kucoin.com"; 
    
    /**
     * API Credentials
     * @var array
     */
    protected $_credentials = [
        'key' => '',
        'secret' => ''
    ];
    
    /**
     * Get API credential
     * @param string $key
     * @return string
     */
    public function getCred($key) {
        return $this -> _credentials[$key];
    }
    
    /**
     * Get account info
     */
    public function getInfo() {
        return $this ->apiCall('/v1/user/info','POST');
    }
    
    /**
     * Get trades via API
     * @param string $endDate End timestamp
     * @return array Trades
     */
    public function getTrades($endDate = '2017-12-31 23:59:59') {
        $tradeData = $this ->apiCall('/v1/order/dealt','GET');
        $trades = [];
        if(!empty($tradeData)) {
            $endTime = strtotime($endDate);
            $tradeData = $tradeData -> data -> datas;
            foreach($tradeData as $row) {
                $time = round($row -> createdAt / 1000);
                if($time > $endTime) {
                    continue;
                }
                
                $soldAmount = $row -> dealValue;
                $boughtAmount = $row -> amount;
                $sold = $row -> coinTypePair;
                $bought = $row -> coinType;
                $soldPrice = $this ->getPrice($sold, $time);                    
                $boughtPrice = $this -> getPrice($bought,$time);
                if(empty($soldPrice) || empty($boughtPrice)) {
                    if($soldPrice > 0) {
                        $boughtPrice = $soldPrice * $soldAmount / $boughtAmount;
                    } else if($boughtPrice > 0) {
                        $soldPrice = $boughtPrice * $boughtAmount / $soldAmount;
                    }
                }
                $fee = $soldPrice * $row -> fee;
                $trade = [
                    'bought' => $row -> coinType,
                    'sold' => $row -> coinTypePair,
                    'timestamp' => $row -> createdAt,
                    'date' => date('Y-m-d H:m:s',$time),
                    'price' => $boughtPrice,
                    'amount' => $row -> amount,
                    'sold_amount' => $row -> dealValue,
                    'sold_price' => $soldPrice,
                    'fee' => $fee,
                    'exchange' => 'kucoin'
                ];
                $trades[] = $trade;
            }
        }
        return $trades;
    }
    
    /**
     * Make API call
     * @param string $endpoint
     * @param string $method
     * @param array $params
     * @return json/string Response
     */
    public function apiCall($endpoint,$method = 'POST',$params = []) {
        
       
        $secret = $this -> getCred('secret');
        ksort($params);        
        $queryString = http_build_query($params); 
        $timeNonce = round(microtime(true),3) * 1000;
       
        //splice string for signing
        $strForSign = $endpoint . "/" . $timeNonce . "/" . $queryString;  

        //Make a base64 encoding of the completed string
        $signatureStr = base64_encode($strForSign);
        $signature = hash_hmac('sha256',$signatureStr,$secret);
    
        $certificate =  dirname(__FILE__) . '/cacert.pem';
        
		$curlOptions = array (             
			CURLOPT_URL => $this -> _host . $endpoint,
			CURLOPT_VERBOSE => 1,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO => $certificate,
            CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => 1
		) ;
        if($method == 'POST') {
            $curlOptions[CURLOPT_POST] = true;
        }
     
        $curlOptions[CURLOPT_HTTPHEADER] = [
            'KC-API-KEY: ' . $this ->getCred('key'),
            "KC-API-NONCE: " . $timeNonce,
            "KC-API-SIGNATURE: " . $signature,
            "Accept-Language: ". "en_US",
            "Content-Type: application/json"

        ];
        
		
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
        
        $response = json_decode($response);       
        if(!isset($response -> success) || $response -> success !== true) {            
            $this -> _errors += [
                $response -> status => $response -> message
            ];
            return false;
        };
             
        curl_close($ch);        
		return $response;

    }
}
