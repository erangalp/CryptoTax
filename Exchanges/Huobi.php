<?php

namespace Exchanges;

/** 
 * We're using Huobi PHP library from the official Github
 * @link https://github.com/huobiapi/REST-API-demos/tree/master/REST-PHP-DEMO
 */
require_once(dirname(__FILE__) . '/Huobi-lib.php');

/**
 * Huobi exchange class
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
class Huobi extends Base {
    
    /**
     * API Crednetials
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
     * Get trades via API
     * @param array $symbols Trade-pair symbols
     * @param string $end End timestamp
     * @return array Trades
     */
    public function getTrades($symbols = [],$end = '2017-12-31 23:59:59') {
        $req = new \req($this ->getCred('key'),$this ->getCred('secret'));
        $trades = [];
        foreach($symbols as $pair) {
            $params = ['states' => 'filled,partial-filled','symbol' => $pair];
            
            $response =  $req ->get_order_orders($pair, '', '', '', 'filled,partial-filled');

            if($response -> status == 'ok') {
                $endTime = strtotime($end);
                $tradeData = $response -> data;
                
                foreach($tradeData as $row) {
                    $field = 'finished-at';
                    $time = round($row -> $field / 1000);
                    
                    if($time > $endTime) {
                        
                        continue;
                    }
                    $symbol = $row -> symbol;
                    $coin1 = strtoupper(substr($symbol,0,strlen($symbol) - 3));
                    $coin2 = strtoupper(substr($symbol,-3));
                    
                    
                    if(stripos($row ->type,'sell') !== false) {
                        $bought = $coin2;
                        $field = 'field-cash-amount';
                        $boughtAmount = $row -> $field;
                        $sold = $coin1;
                        $soldAmount = $row -> amount;
                    } else {
                        $bought = $coin1;
                        $boughtAmount = $row -> amount;
                        $sold = $coin2;
                        $field = 'field-cash-amount';
                        $soldAmount = $row -> $field;
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
                    $field = 'field-fees';
                    $fee = $boughtPrice * $row -> $field;
                    $trade = [
                        'bought' => $bought,
                        'sold' => $sold,
                        'timestamp' => $time,
                        'date' => date('Y-m-d H:m:s',$time),
                        'price' => $boughtPrice,
                        'amount' => (float)$boughtAmount,
                        'sold_amount' => (float)$soldAmount,
                        'sold_price' => $soldPrice,
                        'fee' => $fee,
                        'exchange' => 'huobi'
                    ];
                    
                    $trades[] = $trade;
                }
                
            }
        }
        return $trades;
    }
    
}
