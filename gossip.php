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

include('CONFIG.php');
include('src/DB.php');
include('src/ColorsCLI.php');
include('src/Display.php');
include('src/Subprocess.php');
include('src/BootstrapNode.php');
include('src/ArgvParser.php');
include('src/Tools.php');
include('src/Wallet.php');
include('src/Block.php');
include('src/Blockchain.php');
include('src/Gossip.php');
include('src/Key.php');
include('src/Pki.php');
include('src/PoW.php');
include('src/Transaction.php');
include('src/GenesisBlock.php');
include('src/Peer.php');
include('src/Miner.php');

$return = array(
    'status'    => false,
    'error'     => null,
    'message'   => null,
    'result'    => null
);

date_default_timezone_set("UTC");

//Check if NODE is alive
if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK)) {
    $mainThreadTime = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK);
    $minedTime = date_diff(
        date_create(date('Y-m-d H:i:s', $mainThreadTime)),
        date_create(date('Y-m-d H:i:s', time()))
    );
    $diffTime = $minedTime->format('%s');
    if ($diffTime >= 120)
        $_REQUEST = null;
}

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

                    Tools::writeLog('New connection from peer '.$_SERVER['REMOTE_ADDR'].' to gossip.php?action=MINEDBLOCK');

                    //Get last block
                    $lastBlock = $chaindata->GetLastBlock();

                    /** @var Block $blockMinedByPeer */
                    $blockMinedByPeer = Tools::objectToObject(@unserialize($_REQUEST['block']),"Block");

                    if (is_object($blockMinedByPeer) && isset($blockMinedByPeer->hash)) {

                        //Check if is a next block
                        if ($lastBlock['block_hash'] == $blockMinedByPeer->previous) {

                            $return['message'] = "NEXT BLOCK";

                            //Valid block to add in Blockchain
                            $returnCode = Blockchain::isValidBlockMinedByPeer($chaindata,$lastBlock,$blockMinedByPeer);
                            if ($returnCode == "0x00000000") {
                                $return['status'] = true;
                                $return['error'] = $returnCode;
                            }
                            else {
                                $return['status'] = true;
                                $return['error'] = $returnCode;
                            }
                        }

                        //Check if same height block but different hash block
                        else if ($lastBlock['block_previous'] == $blockMinedByPeer->previous && $lastBlock['block_hash'] != $blockMinedByPeer->hash) {

                            Tools::writeLog('Send me Same height but different BLOCK');

                            //Valid new block in same hiehgt to add in Blockchain
                            $returnCode = Blockchain::isValidBlockMinedByPeerInSameHeight($chaindata,$lastBlock,$blockMinedByPeer);
                            if ($returnCode == "0x00000000") {

                                //Remove previous block from announce table
                                //$chaindata->RemoveBlockAnnounced($blockMinedByPeer->previous);

                                $return['status'] = true;
                                $return['error'] = $returnCode;
                            }
                            else {
                                $return['status'] = true;
                                $return['error'] = $returnCode;
                            }

                            Tools::writeLog('Result isValidBlockMinedByPeerInSameHeight: '.$returnCode);

                        }
                        //Check if same block
                        else if ($lastBlock['block_hash'] == $blockMinedByPeer->hash) {

                            Tools::writeLog('Send me Same block: ' .$blockMinedByPeer->hash);

                            //Check if i announced this block on main thread
                            if (!$chaindata->BlockHasBeenAnnounced($blockMinedByPeer->hash)) {

                                //Its same block i have in my blockchain but i not announced on main thread
                                $chaindata->AddBlockToDisplay($blockMinedByPeer,"2x00000000");

                                //Propagate mined block to network
                                Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer);

                                Tools::writeLog('Accepted block, we will announce it in the main process');
                            } else {
                                Tools::writeLog('Discard block, i announced it');
                            }

                            $return['status'] = true;
                            $return['error'] = "0x00000000";
                        }
                        else {

                            Tools::writeLog($_SERVER['REMOTE_ADDR'].' Else message');

                            //TODO Check if peer have more block than me, > = sync || < = send order to peer to synchronize with me
                            $return['status'] = true;
                            $return['error'] = "0x10000001";
                            $return['message'] = "LastBlock: " . $lastBlock['block_hash'] . " | Received: ".$blockMinedByPeer->hash.'   -   LastBlockPrevious: '.$lastBlock['block_hash'].' | ReceivedPrevious: ' . $blockMinedByPeer->previous;
                        }
                    } else {
                        $return['status'] = true;
                        $return['error'] = "5x00000000";
                        $return['message'] = "Block received malformed";
                    }
                } else {
                    $return['status'] = true;
                    $return['error'] = "0x10000002";
                    $return['message'] = "Need hashPrevious & blockInfo";
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
            case 'STATUSNODE':
                $return['status'] = true;
                $config = $chaindata->GetAllConfig();
                $return['result'] = array(
                    'hashrate'      => $config['hashrate'],
                    'miner'         => $config['miner'],
                    'network'       => $config['network'],
                    'p2p'           => $config['p2p'],
                    'syncing'       => $config['syncing'],
                    'dbversion'     => $config['dbversion'],
                    'nodeversion'   => VERSION,
                    'lastBlock'     => $chaindata->GetNextBlockNum()
                );
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

        //Closing DB connection
        $chaindata->db->close();
    }
}
echo json_encode($return);
exit();
?>