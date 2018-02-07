<?php

namespace Exchanges;

/**
 * Coinbase exchange class
 *
 * @author Eran Galperin
 * @license https://opensource.org/licenses/BSD-3-Clause BSD License
 */
class Coinbase extends Base {
    
    /**
     * Parse Coinbase transaction history CSV file
     * - In your Coinbase account, go to "Tools" -> "Reports" 
     * - Click on the "+ New Report" button
     * - Select type "Transaction History", the relevant wallet account, time range should be "last year"
     * - Download report
     * 
     * @param string $file Path to CSV file
     * @return array Balances
     */
    public function parseTradesCSV($file) {
        if(is_file($file)) {
			$handle = fopen($file,'r');
			
            $balances = [];
            $i = 0;
			while(!feof($handle)) {       
				$row = fgetcsv($handle);
                
                if(isset($row[7]) && is_numeric($row[7])) {
                    // It's a USD deposit / buy order
                    if($row[8] == 'USD') {
                        $balances[] = [
                            'time' => strtotime($row[0]),
                            'amount' => (float)$row[2],
                            'price' => $row[7] / $row[2],
                            'symbol' => $row[3],
                            'fee' => $row[9]
                        ];
                        
                    }
                }
            }
            return $balances;
        }
    }
    
}
