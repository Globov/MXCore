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
include('src/State.php');
include('src/DB.php');
include('src/BootstrapNode.php');
include('src/ArgvParser.php');
include('src/Tools.php');
include('src/ColorsCLI.php');
include('src/Display.php');
include('src/Wallet.php');
include('src/Block.php');
include('src/Blockchain.php');
include('src/Gossip.php');
include('src/Key.php');
include('src/Pki.php');
include('src/PoW.php');
include('src/Transaction.php');
include('src/Miner.php');

$return = array(
    'status'    => false,
    'message'   => null,
    'result'    => null
);

if (isset($_REQUEST)) {
    if (isset($_REQUEST['action'])) {
        $chaindata = new DB();
        switch (strtoupper($_REQUEST['action'])) {
            case 'GETPENDINGTRANSACTIONS':
                $return['status'] = true;
                $return['result'] = $chaindata->GetAllPendingTransactions();
            break;
            case 'ADDPENDINGTRANSACTIONS':
                if (isset($_REQUEST['txs'])) {
                    $return['status'] = true;
                    $return['result'] = $chaindata->addPendingTransactionsByPeer($_REQUEST['txs']);
                }
            break;
            case 'GETBLOCKBYHASH':
                if (isset($_REQUEST['hash'])) {
                    //We get a block given a hash
                    $return['status'] = true;
                    $return['result'] = $chaindata->GetBlockByHash($_REQUEST['hash']);
                }
                break;
            case 'PING':
                $return['status'] = true;
            break;
            case 'GETPEERS':
                $return['status'] = true;
                $return['result'] = $chaindata->GetAllPeers();
            break;
            case 'MINEDBLOCK':
                if (isset($_REQUEST['hash_previous']) && isset($_REQUEST['block'])) {
                    $return['status'] = true;
                    $chaindata->AddMinedBlockByPeer($_REQUEST['hash_previous'],$_REQUEST['block']);
                }
            break;
            case 'HELLOBOOTSTRAP':
                if (isset($_REQUEST['client_ip']) && isset($_REQUEST['client_port'])) {
                    $return['status'] = true;

                    $infoToSend = array(
                        'action' => 'HELLO_PONG'
                    );
                    $response = Tools::postContent('http://' . $_REQUEST['client_ip'] . ':' . $_REQUEST['client_port'] . '/gossip.php', $infoToSend);
                    if (isset($response->status)) {
                        if ($response->status == true) {
                            $chaindata->addPeer($_REQUEST['client_ip'],$_REQUEST['client_port']);
                            $return['result'] = "p2p_on";
                        }
                        else
                            $return['result'] = "p2p_off";
                    }
                    else
                        $return['result'] = "p2p_off";
                }
            BREAK;
            case 'HELLO':
                if (isset($_REQUEST['client_ip']) && isset($_REQUEST['client_port'])) {
                    $return['status'] = true;
                    $chaindata->addPeer($_REQUEST['client_ip'],$_REQUEST['client_port']);
                } else {
                    $return['message'] = "No ClientIP or ClientPort defined";
                }
            BREAK;
            case 'LASTBLOCKNUM':
                $return['status'] = true;
                $return['result'] = $chaindata->GetNextBlockNum();
            break;
            case 'GETGENESIS':
                $return['status'] = true;
                $return['result'] = $chaindata->GetGenesisBlock();
            break;
            case 'SYNCBLOCKS':
                if (isset($_REQUEST['from'])) {
                    $return['status'] = true;
                    $return['result'] = $chaindata->SyncBlocks($_REQUEST['from']);
                }
            break;
            case 'HELLO_PONG':
                $return['status'] = true;
            break;
        }
    }
}
echo @json_encode($return);
?>