<html>
    <head>
        <title>CryptoTax</title>
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700" rel="stylesheet">
        <link href="./cryptotax.css" rel="stylesheet" />
    </head>
    <body>
<?php
require_once('./CryptoTax.php');

set_time_limit(300);
$crypto = new CryptoTax();

$coinbase = new Exchanges\Coinbase;
$balances = [];
$balances = array_merge($balances,$coinbase -> parseTradesCSV('./coinbase-ltc.csv'));
$balances = array_merge($balances,$coinbase -> parseTradesCSV('./coinbase-eth.csv'));
$balances = array_merge($balances,$coinbase -> parseTradesCSV('./coinbase-btc.csv'));

$crypto ->addBalances($balances);

$bittrex = new Exchanges\Bittrex();
$crypto -> addTrades($bittrex -> parseTradesCSV('./bittrex.csv'));

$binance = new Exchanges\Binance();
$crypto ->addTrades($binance ->parseTradesCSV('./binance.csv'));

$kucoin = new Exchanges\Kucoin();
$crypto -> addTrades($kucoin ->getTrades());

$huobi = new Exchanges\Huobi();
$crypto -> addTrades($huobi ->getTrades(['dtaeth','thetaeth','kncbtc','itcbtc','qashbtc','ekobtc']));
$crypto ->setDeltaMethod(CryptoTax::FIFO);
$crypto ->calculateDeltas();
$missing = $crypto ->getUnresolved();
$totalRev = $crypto ->getTotalDelta();
?>
        <h1>Total taxable revenue: $<?php echo round($totalRev,2); ?></h1>
<?php
$trades = $crypto -> getTrades();
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
            <?php //var_dump($trades); ?>
        </pre>
    </body>
</html>