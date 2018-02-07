<html>
    <head>
        <title>CryptoTax</title>
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700" rel="stylesheet">
        <link href="./cryptotax.css" rel="stylesheet" />
    </head>
    <body>
<?php
require_once('./CryptoTax.php');

// We might need a higher time limit depending on the amount of trades we process
set_time_limit(300);
$crypto = new CryptoTax();

// Loading up balances from Coinbase transaction CSV files (See class for additional infromation)
$coinbase = new Exchanges\Coinbase;
$balances = [];
$balances = array_merge($balances,$coinbase -> parseTradesCSV('./coinbase-ltc.csv'));
$balances = array_merge($balances,$coinbase -> parseTradesCSV('./coinbase-eth.csv'));
$balances = array_merge($balances,$coinbase -> parseTradesCSV('./coinbase-btc.csv'));

$crypto ->addBalances($balances);

// Loading up Bittrex CSV file. Note - Bittrex CSV files sometimes come with weird encoding, which might require you to save manually 
// in the correct encoding 
$bittrex = new Exchanges\Bittrex();
$crypto -> addTrades($bittrex -> parseTradesCSV('./bittrex.csv'));

// Loading up Binance trades CSV file
$binance = new Exchanges\Binance();
$crypto ->addTrades($binance ->parseTradesCSV('./binance.csv'));

// Using Kucoin API to fetch trades
$kucoin = new Exchanges\Kucoin();
$crypto -> addTrades($kucoin ->getTrades());

// Using Houbi API to fetch trades
// Note - similarly to Binance, Huobi API does not allow us to retrieve all trades. We have to specify coin-pairs which is
// rather annoying to practically impossible if you've used many pairs and can't remember which. Unfortunately, they also
// do not provide a CSV export, so there's no choice for now.
$huobi = new Exchanges\Huobi();
$crypto -> addTrades($huobi ->getTrades(['dtaeth','thetaeth','kncbtc','itcbtc','qashbtc','ekobtc']));

// Setting the cost-basis method
$crypto ->setDeltaMethod(CryptoTax::FIFO);

// Calculating the deltas between acquisition and sale of each coin
$crypto ->calculateDeltas();

// Fetching unresolved trades (trades that have no previous balance to use as a cost-basis)
$missing = $crypto ->getUnresolved();

// Fetch the total taxable revenue for all executed trades
$totalRev = $crypto ->getTotalDelta();
?>
        <h1>Total taxable revenue: $<?php echo round($totalRev,2); ?></h1>
<?php
$trades = $crypto -> getTrades();

// Displaying taxable revenue per month
$timeSeries = $crypto ->getTimeSeries();
if(!empty($timeSeries)) : ?>
        <ul class="month-revenue">
    <?php foreach($timeSeries as $month => $delta) : 
        
        $rl = round($delta * 100 / $totalRev,1);
        ?>
            <li><em><?php echo $month; ?></em> $<?php echo round($delta,2); ?></li>
    <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if(!empty($missing)) : ?>
        <h2>Unresolved Transactions:</h2>
        <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <th>Time</th>
                <th>Bought</th>
                
                <th>Sold</th>
                <th>$ value</th>
                <th>Exchange</th>
            </tr>
            <?php foreach($missing as $trade) : ?>
            <tr>
                <td><?php echo date('Y-m-d H:i',$trade['timestamp']); ?></td>
                <td><?php echo $trade['amount'] . ' ' . $trade['bought']; ?></td>
                <td><?php echo $trade['sold_amount'] . ' ' . $trade['sold']; ?></td>
                <td>$<?php echo round($trade['price'] * $trade['amount'],2); ?></td>
                <td><?php echo $trade['exchange']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
        <pre>
            <?php 
                // We can also dump the trades to make sure our results make sense
                //var_dump($trades); 
            ?>
        </pre>
    </body>
</html>