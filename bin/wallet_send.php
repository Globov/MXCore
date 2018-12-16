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

    $wallet_from = $argv[1];
    $wallet_to = $argv[2];
    $amount = $argv[3];

    if ($argv[4] != "null")
        $wallet_from_password = $argv[4];
    else
        $wallet_from_password = "";

    if ($wallet_from == "coinbase") {
        $wallet_from_info = Wallet::GetCoinbase();
        $wallet_from = Wallet::GetWalletAddressFromPubKey($wallet_from_info['public']);
    } else {
        $wallet_from_info = Wallet::GetWallet($wallet_from);
    }

    // If have wallet from info
    if ($wallet_from_info !== false) {

        // Get current balance of wallet
        $currentBalance = Wallet::GetBalance($wallet_from);

        // If have balance
        if ($currentBalance >= $amount) {

            //Make transaction and sign
            $transaction = new Transaction($wallet_from_info["public"],$wallet_to,$amount,$wallet_from_info["private"],$wallet_from_password);

            // Check if transaction is valid
            if ($transaction->isValid()) {

                //Instance the pointer to the chaindata
                $chaindata = new DB();

                //We add the pending transaction to send into our chaindata
                $chaindata->addPendingTransactionToSend($transaction->message(),$transaction);

                echo "Transaction created successfully".PHP_EOL;
                echo "TX: ".ColorsCLI::$FG_GREEN. $transaction->message().ColorsCLI::$FG_WHITE.PHP_EOL;
            } else {
                echo "An error occurred while trying to create the transaction".PHP_EOL."The wallet_from password may be incorrect".PHP_EOL;
            }
        } else {
            echo ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;
        }
    } else {
        echo "Could not find the ".ColorsCLI::$FG_RED."public/private key".ColorsCLI::$FG_WHITE." of wallet ".ColorsCLI::$FG_GREEN.$wallet_from.ColorsCLI::$FG_WHITE.PHP_EOL;
        echo "Please check that in the directory ".ColorsCLI::$FG_CYAN.State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.ColorsCLI::$FG_WHITE." there is the keystore of the wallet".PHP_EOL;
    }
}
?>