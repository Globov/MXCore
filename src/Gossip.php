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

class Gossip {

    public $name;
    public $key;
    public $state;
    public $port;
    public $ip;
    public $enable_mine;
    public $pending_transactions;
    public $coinbase;
    public $syncing;

    /** @var DB $object */
    public $chaindata;
    private $make_genesis;
    private $bootstrap_node;
    private $connected_to_bootstrap;
    private $p2p_enabled;
    private $openned_ports;

    private $peersRetrys = array();

    /**
     * Gossip constructor
     *
     * @param $name
     * @param $ip
     * @param $port
     * @param $enable_mine
     * @param bool $make_genesis_block
     * @param bool $bootstrap_node
     * @param bool $p2p_enabled
     */
    public function __construct($name, $ip, $port, $enable_mine, $make_genesis_block=false, $bootstrap_node = false, $p2p_enabled = true)
    {
        ob_start();

        //Limpiamos la ventana
        Display::ClearScreen();

        Display::_printer("Welcome to the %G%PhpMx client");
        Display::_printer("Maximum peer count                       %G%MX%W%=25 %G%total%W%=25");
        Display::_printer("Listening on %G%".$ip."%W%:%G%".$port);
        Display::_printer("PeerID %G%".Tools::GetIdFromIpAndPort($ip,$port));

        if (!extension_loaded("sqlite3")) {
            Display::_printer("%LR%ERROR%W%    Debes instalar la extension %LG%sqlite3");
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                readline("Press any Enter to close close window");
            exit();
        }

        if (!extension_loaded("bcmath")) {
            Display::_printer("%LR%ERROR%W%    Debes instalar la extension %LG%bcmath");
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                readline("Press any Enter to close close window");
            exit();
        }

        $this->make_genesis = $make_genesis_block;
        $this->bootstrap_node = $bootstrap_node;
        $this->p2p_enabled = $p2p_enabled;

        //We declare that we are not synchronizing
        $this->syncing = false;
        $this->name = $name;
        $this->ip = $ip;
        $this->port = $port;
        $this->enable_mine = $enable_mine;

        //We create the folder of data folders so that the node works
        State::MakeDataDirectory();

        //Instance the pointer to the chaindata
        $this->chaindata = new DB();
        Display::_printer("Loading chaindata");

        //We started with that we do not have pending transactions
        $this->pending_transactions = array();

        //We create the Wallet for the node
        $this->key = new Key(Wallet::LoadOrCreate('coinbase',null));
        $this->coinbase = Wallet::GetWalletAddressFromPubKey($this->key->pubKey);

        Display::_printer("Coinbase detected: %LG%".$this->coinbase);

        //We cleaned the table of peers
        $this->chaindata->truncate("peers");

        //By default we mark that we are not connected to the bootstrap and that we do not have ports open for P2P
        $this->connected_to_bootstrap = false;
        $this->openned_ports = false;

        //WE GENERATE THE GENESIS BLOCK
        if ($make_genesis_block) {
            //We check that there is no block GENESIS
            $GENESIS_block_chaindata = $this->chaindata->db->querySingle("SELECT height, block_hash FROM blocks WHERE height = 0",true);
            if (empty($GENESIS_block_chaindata)) {
                //we show the message that we generated the GENESIS block
                Display::_printer("Generating %G%GENESIS%W% - Block %G%#0");
                Display::_printer("Minning Block %G%#0");

                //We created the Blockchain and we undermined the GENESIS block
                $blockchain = new Blockchain($this->coinbase, $this->key->privKey,50);

                //Once the blockchain has been created, it means that the first block has been mined, so we keep the block in the chaindata
                $this->chaindata->addBlock(0,$blockchain->blocks[0]);

                //We show the information of the mined block
                Display::_printer("New Block mined with hash: %G%".$blockchain->blocks[0]->hash);
                Display::_br();
                Display::_printer("Nonce of Block: %G%".$blockchain->blocks[0]->nonce);
                Display::_printer("Transactions in Block: %LG%".count($blockchain->blocks[0]->transactions));

                Display::_printer("%G%GENESIS%W% Block was successfully generated");
                Display::_br();
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                    readline("Press any Enter to close close window");
                exit();
            } else {
                //we show the message that there is already a block genesis
                Display::_printer("%LR%ERROR");
                Display::_printer("There is alrady exist a %G%GENESIS%W% Block");
                Display::_printer("Block #0 -> Hash: %LG%".$GENESIS_block_chaindata['hash']);
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                    readline("Press any key to close close window");
                exit();
            }
        }

        //We are a BOOTSTRAP node
        else if ($bootstrap_node) {
            Display::_printer("%Y%BOOTSTRAP NODE %W%loaded successfully");
            //We try to load the local node information
            $blockchain = Blockchain::loadFromChaindata($this->chaindata);
            $this->state = new State($name,$blockchain,$this->chaindata);

            Display::_printer("Blockchain loaded successfully");
            Display::_printer("Height: %G%".$blockchain->count());
            Display::_printer("LastBlock: %G%".$blockchain->GetLastBlock()->hash);
            Display::_printer("Difficulty: %G%".$blockchain->difficulty);
            Display::_printer("MaxDifficulty: %G%".$blockchain->blocks[0]->info['max_difficulty']);
        }

        //If we already have information, we establish the loaded state
        else {
            //We connect to the Bootstrap node
            if ($this->_addBootstrapNode('blockchain.mataxetos.es','80')) {
                $this->connected_to_bootstrap = true;

                //If we have activated p2p mode (by default it is activated) and we do not have open ports, we can not continue
                if ($this->p2p_enabled && !$this->openned_ports) {
                    Display::_printer("%LR%ERROR%W%    Impossible to establish a P2P connection");
                    Display::_printer("%LR%ERROR%W%    Check that it is accessible from the internet: %Y%http://".$this->ip.":".$this->port);
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                        readline("");
                    exit();
                }

                //We ask the BootstrapNode to give us the information of the connected peers
                $peersNode = BootstrapNode::GetPeers($this->chaindata);
                if (is_array($peersNode) && !empty($peersNode)) {
                    foreach ($peersNode as $peer) {
                        if (trim($this->ip).":".trim($this->port) != trim($peer->ip).":".trim($peer->port)) {
                            $this->_addPeer(trim($peer->ip),trim($peer->port));
                        }
                    }
                }

                //We get the last block from the BootstrapNode
                $lastBlock = BootstrapNode::GetLastBlockNum($this->chaindata);

                //We try to load the local node information
                $blockchain = Blockchain::loadFromChaindata($this->chaindata);

                //We check if we need to synchronize or not
                if ($blockchain->count() < $lastBlock) {
                    //We declare that we are synchronizing
                    $this->syncing = true;

                    //If we do not have the GENESIS block, we download it from the BootstrapNode
                    if ($blockchain->count() == 0) {
                        $genesis_block_bootstrap = BootstrapNode::GetGenesisBlock($this->chaindata);

                        $transactions = array();
                        if (!empty($genesis_block_bootstrap->transactions)) {
                            foreach ($genesis_block_bootstrap->transactions as $transactionInfo) {
                                $transactions[] = new Transaction(
                                    $transactionInfo->wallet_from_key,
                                    $transactionInfo->wallet_to,
                                    $transactionInfo->amount,
                                    null,
                                    null,
                                    true,
                                    $transactionInfo->txn_hash,
                                    $transactionInfo->signature,
                                    $transactionInfo->timestamp
                                );
                            }
                        }

                        $infoBlock = @unserialize($genesis_block_bootstrap->info);

                        $genesis_block = new Block(
                            $genesis_block_bootstrap->block_previous,
                            $genesis_block_bootstrap->difficulty,
                            $transactions,
                            $blockchainNull,
                            true,
                            $genesis_block_bootstrap->block_hash,
                            $genesis_block_bootstrap->nonce,
                            $genesis_block_bootstrap->timestamp_start_miner,
                            $genesis_block_bootstrap->timestamp_end_miner,
                            $infoBlock
                        );

                        //We check if the received block is valid
                        if ($genesis_block->isValid()) {
                            //We add the GENESIS block to the local blockchain
                            $this->chaindata->addBlock(0,$genesis_block);

                            //We add the block to the blockchain
                            $blockchain->addSync(0,$genesis_block);
                        }
                    }
                    $this->state = new State($name,$blockchain,$this->chaindata);
                    $local_lastBlock = $this->state->blockchain->count();
                    Display::_printer("%Y%Imported%W% new blocks headers               %G%count%W%=1");
                } else {
                    $this->state = new State($name,$blockchain,$this->chaindata);

                    Display::_printer("Blockchain loaded successfully");
                    Display::_printer("Height: %G%".$this->chaindata->GetNextBlockNum());
                    Display::_printer("LastBlock: %G%".$blockchain->GetLastBlock()->hash);
                    Display::_printer("Difficulty: %G%".$blockchain->difficulty);
                    Display::_printer("MaxDifficulty: %G%".$blockchain->blocks[0]->info['max_difficulty']);
                }
            } else {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                    readline("Press any Enter to close close window");
                exit();
            }
        }
    }

    /**
     * We add the BootstrapNode
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function _addBootstrapNode($ip, $port) {
        $infoToSend = array(
            'action' => 'HELLOBOOTSTRAP',
            'client_ip' => $this->ip,
            'client_port' => $this->port
        );
        $url = 'https://' . $ip . '/gossip.php';
        $response = Tools::postContent($url, $infoToSend);

        if (isset($response->status)) {
            if ($response->status == true) {
                $this->chaindata->addPeer($ip, $port);
                Display::_printer("Connected to BootstrapNode -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));
                $this->openned_ports = ($response->result == "p2p_off") ? false:true;
            }
            return true;
        }
        else {
            Display::_printer("%LR%Error%W% Can't connect to BootstrapNode %G%". Tools::GetIdFromIpAndPort($ip,$port));
            return false;
        }
    }

    /**
     * We add to the chaindata
     * First we check if we have a connection to the
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function _addPeer($ip, $port) {

        if (!$this->chaindata->haveThisPeer($ip,$port)) {
            $infoToSend = array(
                'action' => 'HELLO',
                'client_ip' => $this->ip,
                'client_port' => $this->port
            );
            $response = Tools::postContent('http://' . $ip . ':' . $port . '/gossip.php', $infoToSend, 5);
            if (isset($response->status)) {
                if ($response->status == true) {
                    $this->chaindata->addPeer($ip, $port);
                    //Display::_printer("Connected to peer -> %G%" . $ip.":".$port);
                    Display::_printer("Connected to peer -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));
                }
                return true;
            }
            else {
                //Display::_printer("%LR%Error%W% Can't connect to peer %G%". $ip.":".$port);
                Display::_printer("%LR%Error%W% Can't connect to peer %G%". Tools::GetIdFromIpAndPort($ip,$port));
                return false;
            }
        }
    }

    /**
     * Check the connection with the peers, if they do not respond to 5 pings, remove them
     */
    public function CheckConnectionWithPeers() {
        $peers = $this->chaindata->GetAllPeers();
        foreach ($peers as $peer) {
            if ($this->ip.":".$this->port != $peer['ip'].":".$peer['port']) {
                $infoToSend = array(
                    'action' => 'PING'
                );
                $response = Tools::postContent('http://' . $peer['ip'] . ':' . $peer['port'] . '/gossip.php', $infoToSend, 5);
                if ($response == null) {
                    if (isset($this->peersRetrys[Tools::GetIdFromIpAndPort($peer['ip'],$peer['port'])]))
                        $this->peersRetrys[Tools::GetIdFromIpAndPort($peer['ip'],$peer['port'])] += 1;
                    else
                        $this->peersRetrys[Tools::GetIdFromIpAndPort($peer['ip'],$peer['port'])] = 1;
                }
            }
        }

        //We go through the retrys of the peers
        foreach ($this->peersRetrys as $peerID => $retrys) {

            //If this peer has 5 or more retrys
            if ($retrys >= 5) {

                //We go through the peers
                foreach ($peers as $peer) {
                    //We get the id of the peer
                    $peerIDtoCheck = Tools::GetIdFromIpAndPort($peer['ip'],$peer['port']);

                    //If the id of the peer matches the id of the retrys
                    if ($peerID == $peerIDtoCheck) {

                        //We eliminated the peer
                        $this->chaindata->removePeer($peer['ip'],$peer['port']);
                        Display::_printer("Removed peer %G%". Tools::GetIdFromIpAndPort($peer['ip'],$peer['port'])."%W% because does not respond to ping");

                        //We eliminated the retrys of this peer
                        unset($this->peersRetrys[$peerID]);
                    }
                }
            }
        }

    }

    /**
     * Set the title of the process with useful information
     */
    private function SetTitleProcess() {
        $title = "PhpMX client";
        $title .= " | PeerID: " . substr(PoW::hash($this->ip . $this->port), 0, 18);
        if ($this->connected_to_bootstrap || $this->bootstrap_node)
            $title .= " | BootstrapNode: Connected";
        else
            $title .= " | BootstrapNode: Disconnected";
        $title .= " | Peers: " . count($this->chaindata->GetAllPeers());

        if ($this->syncing)
            $title .= " | Blockchain: Synchronizing";
        else
            $title .= " | Blockchain: Synchronized";

        if ($this->enable_mine)
            $title .= " | Minning";

        cli_set_process_title($title);
    }

    /**
     * General loop of the node
     */
    public function loop() {
        if (!$this->make_genesis && ($this->bootstrap_node || $this->connected_to_bootstrap)) {

            //If we have the miner activated and we are not synchronizing, we warn that we started mining
            if ($this->enable_mine && !$this->syncing) {
                Display::DisplayMinerScreen();
            }

            //If we do not build the genesis, we'll go around
            while (true) {
                //We establish the title of the process
                $this->SetTitleProcess();

                //If we are not the bootstrap node, we request all bootstrapNode peers to connect to them
                if (!$this->bootstrap_node) {
                    $peersNode = BootstrapNode::GetPeers($this->chaindata);
                    if (is_array($peersNode) && !empty($peersNode)) {
                        foreach ($peersNode as $peer) {
                            if (trim($this->ip).":".trim($this->port) != trim($peer->ip).":".trim($peer->port)) {
                                $this->_addPeer(trim($peer->ip),trim($peer->port));
                            }
                        }
                    }
                }

                //If we are not synchronizing and (we are connected to the bootstrap node or we are the bootstrap node)
                if (!$this->syncing && ($this->connected_to_bootstrap || $this->bootstrap_node)) {

                    //We send all pending transactions to the network
                    $this->sendPendingTransactionsToNetwork();


                    //We mine the block
                    if ($this->enable_mine) {
                        $mined = Miner::MineNewBlock($this);
                        if ($mined)
                            Display::NewBlockMined($this);
                        else {
                            //We get the block mined by another user
                            $blockMinedByPeer = $this->state->blockchain->GetLastBlock();

                            //We load the information of the difficulty and counting of blocks with the information of the mined block
                            $this->state->blockchain->difficulty = $blockMinedByPeer->difficulty;
                            $this->state->blockchain->blocks_count_reset = $blockMinedByPeer->info['current_blocks_difficulty'];
                            $this->state->blockchain->blocks_count_halving = $blockMinedByPeer->info['current_blocks_halving'];
                        }
                    } else {
                        //We check if there are new blocks to be added and that they are next to the last block of the blockchain
                        $last_hash_block = $this->state->blockchain->GetLastBlock()->hash;
                        $peerMinedBlock = $this->chaindata->GetPeersMinedBlockByPrevious($last_hash_block);

                        if (is_array($peerMinedBlock) && !empty($peerMinedBlock)) {
                            $blockMinedByPeer = Tools::objectToObject(@unserialize($peerMinedBlock['block']),"Block");

                            //We obtain the block number to be entered and the block information
                            $numBlock = $this->chaindata->GetNextBlockNum();

                            $mini_hash = substr($blockMinedByPeer->hash,-12);
                            $mini_hash_previous = substr($blockMinedByPeer->previous,-12);

                            //We obtain the difference between the creation of the block and the completion of the mining
                            $minedTime = date_diff(
                                date_create(date('Y-m-d H:i:s', $blockMinedByPeer->timestamp)),
                                date_create(date('Y-m-d H:i:s', $blockMinedByPeer->timestamp_end))
                            );
                            $blockMinedInSeconds = $minedTime->format('%im%ss');

                            //If the previous block received by network refer to the last block of my blockchain
                            if ($blockMinedByPeer->previous == $last_hash_block) {

                                //If the block is valid
                                if ($blockMinedByPeer->isValid()) {

                                    //The block mined by the peer is valid
                                    $this->chaindata->RemovePeerMinedBlockByPrevious($last_hash_block);

                                    //We add the block to the chaindata and the blockchain
                                    $this->chaindata->addBlock($numBlock,$blockMinedByPeer);
                                    $this->state->blockchain->addSync($numBlock,$blockMinedByPeer);

                                    //We load the information of the difficulty and counting of blocks with the information of the mined block
                                    $this->state->blockchain->difficulty = $blockMinedByPeer->difficulty;
                                    $this->state->blockchain->blocks_count_reset = $blockMinedByPeer->info['current_blocks_difficulty'];
                                    $this->state->blockchain->blocks_count_halving = $blockMinedByPeer->info['current_blocks_halving'];

                                    //We send the mined block to all connected peers
                                    $this->sendBlockMinedToNetwork($blockMinedByPeer);

                                    Display::_printer("%Y%Imported%W% new block headers               %G%nonce%W%=".$blockMinedByPeer->nonce."   %G%elapsed%W%=".$blockMinedInSeconds."   %G%number%W%=".$numBlock."   %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash);
                                } else {
                                    Display::_printer("%LR%Ignored%W% new block headers               %G%reason%W%=NoVaid     %G%nonce%W%=".$blockMinedByPeer->nonce."   %G%elapsed%W%=".$blockMinedInSeconds."   %G%number%W%=".$numBlock."   %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash);
                                }
                            } else {
                                Display::_printer("%LR%Ignored%W% new block headers               %G%reason%W%=NoPreviusHashCheck     %G%nonce%W%=".$blockMinedByPeer->nonce."   %G%elapsed%W%=".$blockMinedInSeconds."   %G%number%W%=".$numBlock."   %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash);
                            }
                        }
                    }
                }

                //If we are synchronizing and we are connected with the bootstrap
                if ($this->syncing && $this->connected_to_bootstrap) {
                    //We get the last block from the BootstrapNode
                    $bootstrapNode_lastBlock = BootstrapNode::GetLastBlockNum($this->chaindata);
                    $local_lastBlock = $this->state->blockchain->count();

                    if ($local_lastBlock < $bootstrapNode_lastBlock) {
                        $nextBlocksToSyncFromPeer = BootstrapNode::SyncNextBlocksFrom($this->chaindata, $local_lastBlock);

                        $blocksSynced = 0;
                        foreach ($nextBlocksToSyncFromPeer as $object) {

                            $infoBlock = @unserialize($object->info);

                            $transactions = array();
                            foreach ($object->transactions as $transactionInfo) {
                                $transactions[] = new Transaction(
                                    $transactionInfo->wallet_from_key,
                                    $transactionInfo->wallet_to,
                                    $transactionInfo->amount,
                                    null,
                                    null,
                                    (isset($transactionInfo->tx_fee)) ? $transactionInfo->tx_fee:'',
                                    true,
                                    $transactionInfo->txn_hash,
                                    $transactionInfo->signature,
                                    $transactionInfo->timestamp
                                );
                            }

                            $blockchainNull = "";
                            $block = new Block(
                                $object->block_previous,
                                $object->difficulty,
                                $transactions,
                                $blockchainNull,
                                true,
                                $object->block_hash,
                                $object->nonce,
                                $object->timestamp_start_miner,
                                $object->timestamp_end_miner,
                                $infoBlock
                            );

                            //If the block is valid and the previous one refers to the last block of the local blockchain
                            if ($block->isValid()) {

                                //We add the block to the chaindata and blockchain
                                $this->state->blockchain->addSync($object->height,$block);
                                $this->chaindata->addBlock($object->height,$block);

                                $blocksSynced++;
                            }
                        }
                        Display::_printer("%Y%Imported%W% new blocks headers               %G%count%W%=".$blocksSynced);
                    } else {
                        $this->syncing = false;

                        //We synchronize the information of the blockchain
                        $this->state->blockchain->difficulty = $this->state->blockchain->GetLastBlock()->difficulty;
                        $this->state->blockchain->blocks_count_reset = $this->state->blockchain->GetLastBlock()->info['current_blocks_difficulty'];
                        $this->state->blockchain->blocks_count_halving = $this->state->blockchain->GetLastBlock()->info['current_blocks_halving'];

                        //We check the difficulty
                        $this->state->blockchain->checkDifficulty();

                        //We clean the table of blocks mined by the peers
                        $this->chaindata->truncate("mined_blocks_by_peers");
                        $this->chaindata->truncate("transactions_pending");

                        //If we have the miner, we can start mining the last block
                        if ($this->enable_mine) {
                            Display::DisplayMinerScreen();
                        }
                    }
                    continue;
                }


                //We get the last block from the BootstrapNode and compare it with our local
                $bootstrapNode_lastBlock = ($this->bootstrap_node == true) ? $this->state->blockchain->count():BootstrapNode::GetLastBlockNum($this->chaindata);
                $local_lastBlock = $this->state->blockchain->count();

                if ($local_lastBlock < $bootstrapNode_lastBlock) {
                    $this->syncing = true;
                    continue;
                }

                //We check the connections with the peers
                //$this->CheckConnectionWithPeers();

                usleep(1000000);
            }
        }
    }

    /**
     * We send all pending transactions to our peers
     *
     * @param $blockMined
     */
    public function sendPendingTransactionsToNetwork() {

        //We obtain all pending transactions to send
        $pending_tx = $this->chaindata->GetAllPendingTransactionsToSend();

        //We add the pending transaction to the chaindata
        foreach ($pending_tx as $tx)
            $this->chaindata->addPendingTransaction($tx);

        //We get all the peers and send the pending transactions to all
        $peers = $this->chaindata->GetAllPeers();
        foreach ($peers as $peer) {

            $infoToSend = array(
                'action' => 'ADDPENDINGTRANSACTIONS',
                'txs' => $pending_tx
            );

            if ($peer["ip"] == "blockchain.mataxetos.es") {
                Tools::postContent('https://blockchain.mataxetos.es/gossip.php', $infoToSend);
            }
            else {
                Tools::postContent('http://' . $peer['ip'] . ':' . $peer['port'] . '/gossip.php', $infoToSend);
            }
        }

        //We delete transactions sent from transactions_pending_to_send
        foreach ($pending_tx as $tx)
            $this->chaindata->removePendingTransactionToSend($tx['txn_hash']);
    }

    /**
     * We send the mined block to all our peers
     *
     * @param $blockMined
     */
    public function sendBlockMinedToNetwork($blockMined) {
        $peers = $this->chaindata->GetAllPeers();
        foreach ($peers as $peer) {
            if ($this->ip.":".$this->port != $peer['ip'].":".$peer['port']) {
                $infoToSend = array(
                    'action' => 'MINEDBLOCK',
                    'hash_previous' => $blockMined->previous,
                    'block' => SQLite3::escapeString(@serialize($blockMined))
                );

                if ($peer["ip"] == "blockchain.mataxetos.es") {
                    Tools::postContent('https://blockchain.mataxetos.es/gossip.php', $infoToSend);
                }
                else {
                    Tools::postContent('http://' . $peer['ip'] . ':' . $peer['port'] . '/gossip.php', $infoToSend);
                }
            }
        }
    }
}
?>