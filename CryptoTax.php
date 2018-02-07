<?php
spl_autoload_register(function ($class) {
    $file = $class . '.php';
    $file = str_replace('_','/',$file);
    
    require_once('./' . $file);
});

/**
 * CryptoTax - cost-basis calculation class
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
class CryptoTax {
    
    /**
     * Coin balances
     * @var array 
     */
    protected $_balances = [];
    
    /**
     * Trades
     * @var array
     */
    protected $_trades = [];
    
    /**
     * Unresolved trades
     * - Trades we could not find existing balance for and therefor could not calculate the cost-basis
     * @var array
     */
    protected $_unresolved = [];
    
    /**
     * Historical coin price storage
     * @var object SQLite3 
     */
    protected $_db;
    
    /**
     * Cost-basis method
     * - FIFO - First-in / First-out
     * - LIFO - Last-in / First-out
     * 
     * Use class constants to change this
     * @var string
     */
    protected $_deltaMethod = self::FIFO;
    
    const FIFO = 'fifo';
    const LIFO = 'lifo';
    
    /**
     * Add trades array
     * @param array $trades
     */
    public function addTrades($trades = []) {
        $trades = $this -> _trades + $trades;
        ksort($trades);
        $this -> _trades = $trades;
    }
    
    /**
     * Set cost-basis method
     * @param string $method
     */
    public function setDeltaMethod($method) {
        $this -> _deltaMethod = $method;
    }
    
    /**
     * Add balances array
     * @param array $balances
     */
    public function addBalances($balances = []) {
        foreach($balances as $balance) {
            $this ->addBalance($balance['time'], $balance['amount'], $balance['price'], $balance['symbol'],$balance['fee']);
        }
    }
    
    /**
     * Add balance 
     * @param int $time UNIX timestamp
     * @param float $amount Balance amount
     * @param float $unitCost USD Price at the time of purchase
     * @param string $symbol Coin symbol
     * @param float $fee Fee paid (USD)
     */
    public function addBalance($time,$amount,$unitCost,$symbol,$fee = 0) {
        if(!isset($this -> _balances[$symbol])) {
            $this -> _balances[$symbol] = [];
        }
        
        if(isset($this -> _balances[$symbol][$time])) {
            $amount += $this -> _balances[$symbol][$time]['amount'];
            $fee += $this -> _balances[$symbol][$time]['fee'];
        }
        $this -> _balances[$symbol][$time] = ['amount' => $amount,'price' => $unitCost,'fee' => $fee];
    }
    
    /**
     * Get balances
     * @return array
     */
    public function getBalances() {
        return $this -> _balances;
    }
    
    /**
     * Get trades
     * @return array
     */
    public function getTrades() {
        return $this -> _trades;
    }
    
    /**
     * Calculate cost-basis for each trade
     */
    public function calculateDeltas() {
        
        foreach($this -> _trades as $timestamp => $trade) {
            
            // We can only calculate deltas for coins we have a cost basis for
            if(isset($this -> _balances[$trade['sold']])) {
                $delta = $this ->reduceBalance($trade['sold_amount'], $trade['sold'], $trade['timestamp'], $trade['sold_price']);
                if(is_numeric($delta)) {
                    $this -> _trades[$timestamp]['delta'] = $delta;
                    
                }
                $this ->addBalance($trade['timestamp'], $trade['amount'], $trade['price'], $trade['bought']);
            } else {
                $this -> _unresolved[$timestamp] = $trade;
            }
        }
    }
    
    /**
     * Reduce balance for each trade executed
     * - Used by calculateDeltas() according to cost-basis method
     * 
     * @param float $amount Amount to reduce balance by
     * @param string $coin Coin symbol
     * @param int $time UNIX Timestamp
     * @param float $price Price at the time we perform the reduction
     * @return float The delta between balance price and trade price
     */
    public function reduceBalance($amount,$coin,$time,$price) {
        if(isset($this -> _balances[$coin])) {
            $delta = 0;
            $balances = $this -> _balances[$coin];            
            $this -> _deltaMethod == self::LIFO ? krsort($balances) : ksort($balances);
            
            
            foreach($balances as $balanceTime => $balance) {
                if($balance['amount'] == 0) {
                    continue;
                }
                if($balanceTime > $time) {                
                    continue;
                }
                if(!isset($balance['price'])) {
                    $balance['price'] = $this -> getPrice($coin,$time);
                }
                $priceDiff = $price - $balance['price'];
                
                $balanceAmount = $balance['amount'];
                if($amount >= $balanceAmount) {
                    $amount = $amount - $balanceAmount;
                    $remaining = 0;
                    $delta += $balanceAmount * $priceDiff;
                } else {
                    $remaining = $balanceAmount - $amount;
                    $delta += $amount * $priceDiff;
                    $amount = 0;
                    
                }
                
                $this -> _balances[$coin][$balanceTime]['amount'] = $remaining;
                
                if($amount == 0) {
                    break;
                }
                
            }
            
            return $delta;
        }
    }
    
    /**
     * Get total deltas for all the transaction calculated
     * @return float
     */
    public function getTotalDelta() {
        $delta = 0;
        $fees = 0;
        foreach($this -> _trades as $trade) {
            if(isset($trade['delta'])) {
                $delta += $trade['delta'];
            }
            if(isset($trade['fee'])) {
                $fees += $trade['fee'];
            }
        }
        return $delta - $fees;
    }
    
    /**
     * Init coin price storage
     * - Requires SQLite3 extension to be active
     */
    public function initStorage() {
        if(empty($this -> _db)) {
            $db = new SQLite3('./prices.db');
            $this -> _db = $db;
        }
        
    }
    
    /**
     * Get historical coin price
     * - Uses CryptoCompare API to retrieve historical price data
     * - Uses SQLite database to avoid repeated API calls to speed up operation
     * 
     * @param string $coin Coin symbol
     * @param int $time UNIX Timestamp
     * @return float
     */
    public function getPrice($coin,$time) {
        $this ->initStorage();
        $start = date('Y-m-d H:',$time) . '00:00';
        $end = date('Y-m-d H:',$time) . '59:59';
        $query = "SELECT price FROM prices WHERE symbol='" . $coin . "' AND ts BETWEEN '" . $start . "' AND '" . $end . "'";
       
        $result = $this -> _db -> query($query);
        $row = $result -> fetchArray(SQLITE3_ASSOC);
        
        if(empty($row)) {
            $compare = new CryptoCompare;
            $price = $compare ->getHistoricalPrice($coin, $time);
            if($price === false) {
                $price = 0;
            }
            $result = $this -> _db -> exec('INSERT INTO prices (symbol,price,ts) VALUES (' . "'" . $coin . "'," . $price . ",'" . date('Y-m-d H:i:s',$time) . "')");
            return $price;
        } else {
            
            return $row['price'];
        }
        
        
    }
    
    
    /**
     * Get delta per month
     * @return array
     */
    public function getTimeSeries() {
        $series = array();
        foreach($this -> _trades as $trade) {
            
            $month = date('M',$trade['timestamp']);
            if(!isset($series[$month])) {
                $series[$month] = 0;
            }
            if(!empty($trade['delta'])) {
                $series[$month] += $trade['delta'] - (isset($trade['fee']) ? $trade['fee'] : 0);
            }
        }
        return $series;
    }
    
    /**
     * Get unresolved trades
     * @return array
     */
    public function getUnresolved() {
        return $this -> _unresolved;
    }
}