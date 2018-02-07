<?php
spl_autoload_register(function ($class) {
    $file = $class . '.php';
    $file = str_replace('_','/',$file);
    
    require_once('./' . $file);
});

class CryptoTax {
    protected $_balances = [];
    protected $_trades = [];
    protected $_unresolved = [];
    protected $_db;
    protected $_deltaMethod = self::FIFO;
    const FIFO = 'fifo';
    const LIFO = 'lifo';
    
    public function addTrades($trades = []) {
        $trades = $this -> _trades + $trades;
        ksort($trades);
        $this -> _trades = $trades;
    }
    
    public function setDeltaMethod($method) {
        $this -> _deltaMethod = $method;
    }
    
    public function addBalances($balances = []) {
        foreach($balances as $balance) {
            $this ->addBalance($balance['time'], $balance['amount'], $balance['price'], $balance['symbol'],$balance['fee']);
        }
    }
    
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
    
    public function getBalances() {
        return $this -> _balances;
    }
    
    public function getTrades() {
        return $this -> _trades;
    }
    
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
    
    public function initStorage() {
        if(empty($this -> _db)) {
            $db = new SQLite3('./prices.db');
            $this -> _db = $db;
        }
        
    }
    
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
    
    public function getUnresolved() {
        return $this -> _unresolved;
    }
}