# CryptoTax
A set of classes for calculating cryptocurrency tax (US)

## About

The goal of this project is to be able to calculate capital gain tax for each individual cryptocurrency trade.
For that purpose, we load initial balances, add all the trades and then execute those in chornological order to figure out the cost-basis
for each trade.

## Exchanges

Where possible, we try to utilize the exchange API to retrieve the trades. Some exchanges make this difficult by providing incomplete 
list of trades (Bittrex) or requiring the trade-pair symbol (Binance), so in those cases we parse CSV files instead, which typically
contain all the information.

## How to use

The index.php shows an example usage and renders a basic dashboard. 
Our main class is the CryptoTax class which collects balances and trades and then calculates the cost basis for reach trade (called deltas here).

Each supported exchange has its own class under /Exchanges, and follows a similmar approach with the goal of obtaining a normalized array of
trades. The exception is Coinbase, which is used to initialize the balances instead.

The method of operation is retrieving the trades array from each exchange, and then loading it up using the `addTrades()` method on the `CryptoTax` instance.

You can add individual balances and trades using the `addBalance()` and `addTrade()` respectively. See usage instructions in the class' documentation.

You can also pick between two common methods for calculating cost-basis - FIFO (First-in / First out) and LIFO (Last-in / First-out). You should
consult with your CPA / Tax lawyer regarding the method to use in your case.

After calculating the cost-basis using `calculateDeltas()`, you can also retrieve unresolved trades using `getUnresolved()` - those are trades 
that did not have available balance left to calculate the cost-basis from. If you manually add the balance with the original price, you should
be able to resolve all of the trades.
