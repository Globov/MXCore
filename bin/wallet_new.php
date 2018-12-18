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

if (!isset($argv[1]))
    exit('You must specify a password for the Wallet, Example: php wallet_new.php PASSWORD');

$wallet_password = $argv[1];

$wallet = Wallet::LoadOrCreate("",$argv[1]);
echo "The wallet was generated correctly: " . Wallet::GetWalletAddressFromPubKey($wallet["public"]);
?>