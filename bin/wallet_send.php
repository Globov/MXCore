<?php
// MIT License
//
// Copyright (c) 2018 MXCCoin
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//

include('../src/DB.php');
include('../src/ArgvParser.php');
include('../src/ColorsCLI.php');
include('../src/Tools.php');
include('../src/Display.php');
include('../src/Wallet.php');
include('../src/Block.php');
include('../src/Blockchain.php');
include('../src/Gossip.php');
include('../src/Key.php');
include('../src/Pki.php');
include('../src/PoW.php');
include('../src/State.php');
include('../src/Transaction.php');
include('../src/Miner.php');

if (isset($argv)) {

    if (!isset($argv[1])) {
        echo "You must specify the ".ColorsCLI::$FG_LIGHT_RED."Sender Wallet".ColorsCLI::$FG_WHITE.PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM");
    }

    if (!isset($argv[2])) {
        echo "You must specify the ".ColorsCLI::$FG_LIGHT_RED."Recipient Wallet".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM");
    }

    if (!isset($argv[3])) {
        echo "You must specify the amount you want to send".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM");
    }

    if (!isset($argv[4])) {
        echo "You must specify the password of the Sender Wallet to sign the transaction".PHP_EOL;
        exit("Example: php wallet_send.php WALLET_FROM|coinbase WALLET_TO AMOUNT PASSWORD_FROM");
    }

    $tx_fee = 2;
    if (isset($argv[5])) {
        switch ($argv[5]) {
            case "high":
                $tx_fee = 3;
            break;
            case "medium":
                $tx_fee = 2;
            break;
            case "low":
                $tx_fee = 1;
            break;
            default:
                $tx_fee = 2;
            break;
        }
    }

    $wallet_from = $argv[1];
    $wallet_to = $argv[2];
    $amount = $argv[3];

    if ($argv[4] != "null")
        $wallet_from_password = $argv[4];
    else
        $wallet_from_password = "";

    echo Wallet::SendTransaction($wallet_from,$wallet_from_password,$wallet_to,$amount,$tx_fee);
}
?>