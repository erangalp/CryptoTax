<?php

namespace Exchanges;

/**
 * Base class for Exchange interfacing classes
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
abstract class Base {
    protected $_errors = [];
    
    /**
     * Get historical coin price
     * 
     * @param string $coin
     * @param int $time
     * @return float
     */
    public function getPrice($coin,$time) {
        $tax = new \CryptoTax;
        return $tax -> getPrice($coin, $time);
    }
    
    /**
     * Get errors array
     * @return array
     */
    public function getErrors() {
        return $this -> _errors;
    }
}
