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
    public $port;
    public $ip;
    public $enable_mine;
    public $pending_transactions;
    public $coinbase;
    public $syncing;
    public $config;
    public $peers = array();
    public $difficulty;
    public $isTestNet;

    /** @var DB $object */
    public $chaindata;
    private $make_genesis;
    private $bootstrap_node;
    private $connected_to_bootstrap;
    private $p2p_enabled;
    private $openned_ports;

    private $peersRetrys = array();

    private $loop_x5 = 0;
    private $loop_x10 = 0;

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
     * @param bool $isTestNet
     */
    public function __construct($db, $name, $ip, $port, $enable_mine, $make_genesis_block=false, $bootstrap_node = false, $p2p_enabled = true, $isTestNet=false)
    {
        //Clear screen
        Display::ClearScreen();

        //Init Display message
        Display::_printer("Welcome to the %G%PhpMXC CLI - Version: " . VERSION);
        Display::_printer("Maximum peer count                       %G%value%W%=".PEERS_MAX);
        Display::_printer("Listening on %G%".$ip."%W%:%G%".$port);
        Display::_printer("PeerID %G%".Tools::GetIdFromIpAndPort($ip,$port));

        $this->make_genesis = $make_genesis_block;
        $this->bootstrap_node = $bootstrap_node;
        $this->p2p_enabled = $p2p_enabled;
        $this->isTestNet = $isTestNet;

        //We declare that we are not synchronizing
        $this->syncing = false;
        $this->name = $name;
        $this->ip = $ip;
        $this->port = $port;
        $this->enable_mine = $enable_mine;

        //We create default folders
        Tools::MakeDataDirectory();

        //Clear TMP files
        Tools::clearTmpFolder();

        //Default miners stopped
        Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

        //Update MainThread time for subprocess
        Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

        //Instance the pointer to the chaindata and get config
        $this->chaindata = new DB();
        $this->config = $this->chaindata->GetConfig();

        //We started with that we do not have pending transactions
        $this->pending_transactions = array();

        //We create the Wallet for the node
        $this->key = new Key(Wallet::LoadOrCreate('coinbase',null));

        if (strlen($this->key->pubKey) != 451) {
            Display::_printer("%LR%ERROR%W% Can't get the public/private key");
            Display::_printer("%LR%ERROR%W% Make sure you have openssl installed and activated in php");
            exit();
        }
        $this->coinbase = Wallet::GetWalletAddressFromPubKey($this->key->pubKey);
        Display::_printer("Coinbase detected: %LG%".$this->coinbase);

        //We cleaned the table of peers
        if (!$this->bootstrap_node)
            $this->chaindata->truncate("peers");

        //We cleaned the table of blocks mined by peers
        $this->chaindata->RemovePeerMinedBlocks();

        //By default we mark that we are not connected to the bootstrap and that we do not have ports open for P2P
        $this->connected_to_bootstrap = false;
        $this->openned_ports = false;

        //WE GENERATE THE GENESIS BLOCK
        if ($make_genesis_block) {
            if(!$isTestNet)
                GenesisBlock::make($this->chaindata,$this->coinbase,$this->key->privKey,$this->isTestNet,"50");
            else
                GenesisBlock::make($this->chaindata,$this->coinbase,$this->key->privKey,$this->isTestNet,"99999999999999999999999999999999");
        }

        //We are a BOOTSTRAP node
        else if ($bootstrap_node) {
            if ($this->isTestNet)
                Display::_printer("%Y%BOOTSTRAP NODE %W%(%G%TESTNET%W%) loaded successfully");
            else
                Display::_printer("%Y%BOOTSTRAP NODE %W%loaded successfully");

            Display::_printer("Height: %G%".$this->chaindata->GetNextBlockNum());

            $lastBlock = $this->chaindata->GetLastBlock();

            Display::_printer("LastBlock: %G%".$lastBlock['block_hash']);
            Display::_printer("Difficulty: %G%".$lastBlock['difficulty']);

            $this->difficulty = $lastBlock['difficulty'];

            //Check peers status
            $this->CheckConnectionWithPeers();
        }

        //If we already have information, we establish the loaded state
        else {
            //We connect to the Bootstrap node
            if ($this->_addBootstrapNode()) {
                $this->connected_to_bootstrap = true;

                //If we have activated p2p mode (by default it is activated) and we do not have open ports, we can not continue
                if ($this->p2p_enabled && !$this->openned_ports) {
                    Display::_printer("%LR%ERROR%W%    Impossible to establish a P2P connection");
                    Display::_printer("%LR%ERROR%W%    Check that it is accessible from the internet: %Y%http://".$this->ip.":".$this->port);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

                //We ask the BootstrapNode to give us the information of the connected peers
                $peersNode = BootstrapNode::GetPeers($this->chaindata,$this->isTestNet);
                if (is_array($peersNode) && !empty($peersNode)) {

                    $maxRand = PEERS_MAX;
                    if (count($peersNode) < PEERS_MAX)
                        $maxRand = count($peersNode);

                    $randomPeers = array_rand($peersNode,$maxRand);
                    if (is_array($randomPeers)) {
                        foreach ($randomPeers as $randomPeer) {
                            if (trim($this->ip).":".trim($this->port) != trim($peersNode[$randomPeer]->ip).":".trim($peersNode[$randomPeer]->port)) {
                                if (count($this->peers) < PEERS_MAX) {
                                    $this->_addPeer(trim($peersNode[$randomPeer]->ip),trim($peersNode[$randomPeer]->port));
                                }
                            }
                        }
                    } else {
                        if (trim($this->ip).":".trim($this->port) != trim($peersNode[$randomPeers]->ip).":".trim($peersNode[$randomPeers]->port)) {
                            if (count($this->peers) < PEERS_MAX) {
                                $this->_addPeer(trim($peersNode[$randomPeers]->ip),trim($peersNode[$randomPeers]->port));
                            }
                        }
                    }
                }

                if (count($this->peers) < PEERS_REQUIRED) {
                    Display::_printer("%LR%ERROR%W%    there are not enough peers       count=".count($this->peers)."   required=".PEERS_REQUIRED);
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }

                //We get the last block from the BootstrapNode
                $lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($this->chaindata,$this->isTestNet);
                $lastBlock_LocalNode = $this->chaindata->GetNextBlockNum();


                //We check if we need to synchronize or not
                if ($lastBlock_LocalNode < $lastBlock_BootstrapNode) {
                    Display::_printer("%LR%DeSync detected %W%- Downloading blocks (%G%".$lastBlock_LocalNode."%W%/%Y%".$lastBlock_BootstrapNode.")");

                    //We declare that we are synchronizing
                    $this->syncing = true;

                    //If we do not have the GENESIS block, we download it from the BootstrapNode
                    if ($lastBlock_LocalNode == 0) {
                        //Make Genesis from Peer
                        $genesis_block_bootstrap = BootstrapNode::GetGenesisBlock($this->chaindata,$this->isTestNet);
                        $genesisMakeBlockStatus = GenesisBlock::makeFromPeer($genesis_block_bootstrap,$this->chaindata);

                        if ($genesisMakeBlockStatus)
                            Display::_printer("%Y%Imported%W% GENESIS block header               %G%count%W%=1");
                        else {
                            Display::_printer("%LR%ERROR%W%    Can't make GENESIS block");
                            if (IS_WIN)
                                readline("Press any Enter to close close window");
                            exit();
                        }
                    }
                } else {
                    Display::_printer("Blockchain up to date");
                    Display::_printer("Height: %G%".$this->chaindata->GetNextBlockNum());

                    $lastBlock = $this->chaindata->GetLastBlock();

                    Display::_printer("LastBlock: %G%".$lastBlock['block_hash']);
                    Display::_printer("Difficulty: %G%".$lastBlock['difficulty']);

                    $this->difficulty = $lastBlock['difficulty'];

                }

                //Check if have same GENESIS block from BootstrapNode
                $genesis_block_bootstrap = BootstrapNode::GetGenesisBlock($this->chaindata,$this->isTestNet);
                $genesis_block_local = $this->chaindata->GetGenesisBlock();
                if ($genesis_block_local['block_hash'] != $genesis_block_bootstrap->block_hash) {
                    Display::_printer("%LR%ERROR    %Y%GENESIS BLOCK NO MATCH%W%    genesis block does not match the block genesis of bootstrapNode");
                    if (IS_WIN)
                        readline("Press any Enter to close close window");
                    exit();
                }


            } else {
                if (IS_WIN)
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
    public function _addBootstrapNode() {

        if ($this->isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

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

                $this->peers[] = array($ip.':'.$port => $ip,$port);

                if ($this->isTestNet)
                    Display::_printer("Connected to BootstrapNode (TESTNET) -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));
                else
                    Display::_printer("Connected to BootstrapNode -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));


                $this->openned_ports = ($response->result == "p2p_off") ? false:true;
            }
            return true;
        }
        else {
            if ($this->isTestNet)
                Display::_printer("%LR%Error%W% Can't connect to BootstrapNode (TESTNET) %G%". Tools::GetIdFromIpAndPort($ip,$port));
            else
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
    public function _addPeer($ip, $port,$displayMessage=true) {

        if (!$this->chaindata->haveThisPeer($ip,$port) && ($this->ip != $ip && $this->port != $port)) {
            $infoToSend = array(
                'action' => 'HELLO',
                'client_ip' => $this->ip,
                'client_port' => $this->port
            );
            $response = Tools::postContent('http://' . $ip . ':' . $port . '/gossip.php', $infoToSend, 1);
            if ($response != null && isset($response->status)) {
                if ($response->status == true) {
                    $this->chaindata->addPeer($ip, $port);
                    $this->peers[] = array($ip.':'.$port => $ip,$port);
                    if ($displayMessage)
                        Display::_printer("Connected to peer -> %G%" . Tools::GetIdFromIpAndPort($ip,$port));
                }
                return true;
            }
            else {
                //Display::_printer("%LR%Error%W% Can't connect to peer %G%". $ip.":".$port);
                if ($displayMessage)
                    Display::_printer("%LR%Error%W% Can't connect to peer %G%". Tools::GetIdFromIpAndPort($ip,$port));
                return false;
            }
        }
    }

    /**
     * Check the connection with the peers, if they do not respond remove them
     */
    public function CheckConnectionWithPeers() {

        //Run subprocess peerAlive per peer
        $peers = $this->chaindata->GetAllPeers();

        if (count($peers) > 0) {
            Display::_printer("Checking status of peers                 %G%count%W%=".count($peers));
            $id = 0;
            foreach ($peers as $peer) {
                //Params for subprocess
                $params = array(
                    $peer['ip'],
                    $peer['port']
                );

                //Run subprocess propagation
                Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'peerAlive',$params,$id);
                $id++;
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

        if ($this->isTestNet)
            $title .= " | TESTNET";
        else
            $title .= " | MAINNET";

        cli_set_process_title($title);
    }

    /**
     * This loop only run this loop only runs 1 time of 5 main loop
     */
    public function loop_x5() {
        $this->loop_x5++;
        if ($this->loop_x5 == 5) {
            $this->loop_x5 = 0;

            if ($this->syncing)
                return;

            if (!$this->connected_to_bootstrap || !$this->bootstrap_node)
                return;

            //We get the pending transactions from BootstrapNode
            if (!$this->bootstrap_node) {
                $transactionsByPeer = BootstrapNode::GetPendingTransactions($this->chaindata,$this->isTestNet);
                foreach ($transactionsByPeer as $transactionByPeer) {
                    $this->chaindata->addPendingTransactionByBootstrap($transactionByPeer);
                }
            }
        }
    }

    /**
     * This loop only run this loop only runs 1 time of 5 main loop
     */
    public function loop_x10() {
        $this->loop_x10++;
        if ($this->loop_x10 == 10) {
            $this->loop_x10 = 0;

            $blocksMinedByPeers = $this->chaindata->GetPeersMinedBlocks();
            if (!empty($blocksMinedByPeers)) {
                while ($blockMinedByPeerDB = $blocksMinedByPeers->fetch_array(MYSQLI_ASSOC)) {
                    $blockMinedByPeer = Tools::objectToObject($blockMinedByPeerDB['block'],'Block');

                    //Check if block is valid
                    if (!$blockMinedByPeer->isValid())
                        $this->chaindata->RemovePeerMinedBlockByPrevious($blockMinedByPeerDB['previous_hash']);

                    //Check if block is on blockchain
                    $blockInBlockchain = $this->chaindata->GetBlockByHash($blocksMinedByPeers->hash);
                    if ($blockInBlockchain != null)
                        $this->chaindata->RemovePeerMinedBlockByPrevious($blockMinedByPeerDB['previous_hash']);
                }
            }

            //Check dead peers
            $this->CheckConnectionWithPeers();
        }
    }

    /**
     * We get the pending transactions from BootstrapNode
     */
    public function GetPendingTransactions() {
        if (!$this->bootstrap_node) {
            $transactionsByPeer = BootstrapNode::GetPendingTransactions($this->chaindata,$this->isTestNet);
            foreach ($transactionsByPeer as $transactionByPeer) {
                $this->chaindata->addPendingTransactionByBootstrap($transactionByPeer);
            }
        }
    }

    /**
     * Check if the peers have mined last block
     *
     * @param $last_hash_block
     */
    public function CheckIfPeersHaveMinedBlock($last_hash_block) {

        //Get next block by last hash
        $peerMinedBlock = $this->chaindata->GetPeersMinedBlockByPrevious($last_hash_block);
        if (is_array($peerMinedBlock) && !empty($peerMinedBlock)) {

            //Load Block class
            $blockMinedByPeer = Tools::objectToObject(@unserialize($peerMinedBlock['block']),"Block");

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

                    //We load the information of the difficulty and counting of blocks with the information of the mined block
                    $this->difficulty = $blockMinedByPeer->difficulty;

                    //We add the block to the chaindata and the blockchain
                    $this->chaindata->addBlock($numBlock,$blockMinedByPeer);

                    if ($this->enable_mine) {
                        if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {
                            //Stop minning subprocess
                            Tools::clearTmpFolder();
                            Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
                            Display::NewBlockCancelled();
                        }
                    }

                    //We send the mined block to all connected peers
                    Tools::sendBlockMinedToNetworkWithSubprocess($this->chaindata,$blockMinedByPeer);

                    Display::_printer("%Y%Imported%W% new block headers               %G%nonce%W%=".$blockMinedByPeer->nonce."      %G%elapsed%W%=".$blockMinedInSeconds."     %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash."      %G%number%W%=".$numBlock."");
                } else {
                    Display::_printer("%LR%Ignored%W% new block headers                %G%nonce%W%=".$blockMinedByPeer->nonce."      %G%elapsed%W%=".$blockMinedInSeconds."     %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash."      %G%number%W%=".$numBlock."");
                }
            } else {
                Display::_printer("%LR%Ignored%W% new block headers                %G%nonce%W%=".$blockMinedByPeer->nonce."      %G%elapsed%W%=".$blockMinedInSeconds."     %G%previous%W%=".$mini_hash_previous."   %G%hash%W%=".$mini_hash."      %G%number%W%=".$numBlock."");
            }
        }
    }

    /**
     * General loop of the node
     */
    public function loop() {

        if ($this->make_genesis)
            return;

        if (!$this->connected_to_bootstrap && !$this->bootstrap_node)
            return;

        $this->GetPendingTransactions();

        //If we do not build the genesis, we'll go around
        while (true) {
            //We establish the title of the process
            $this->SetTitleProcess();

            //Update MainThread time for subprocess
            Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK,time());

            //Get pending transactions
            $this->GetPendingTransactions();

            //Exec delayed loops
            $this->loop_x5();

            //If we are not synchronizing
            if (!$this->syncing) {

                //We send all transactions_pending_to_send to the network
                $this->sendPendingTransactionsToNetwork();

                //We check the difficulty
                if (!$this->isTestNet)
                    Blockchain::checkDifficulty($this->chaindata,$this->difficulty);

                //We mine the block
                if ($this->enable_mine) {
                    //Enable Miners if not enabled
                    if (@!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)){
                        Miner::MineNewBlock($this);

                        //Wait 0.5s
                        usleep(500000);
                    }
                    //Check if threads are enabled
                    else {

                        for($i=0;$i<MINER_MAX_SUBPROCESS;$i++){
                            //Check if MinersThreads is alive
                            if (@!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i) && @!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {
                                Display::_printer("The miner thread #".$i." do not seem to respond. Restarting Thread");

                                //Get info to pass miner
                                $lastBlock = $this->chaindata->GetLastBlock();
                                $directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;

                                $network = "mainnet";
                                if ($this->isTestNet)
                                    $network = "testnet";

                                $params = array(
                                    $lastBlock['block_hash'],
                                    $this->difficulty,
                                    $i,
                                    MINER_MAX_SUBPROCESS,
                                    $network
                                );
                                Subprocess::newProcess($directoryProcessFile,'miner',$params,$i);

                                //Wait 0.5s
                                usleep(500000);
                            }
                        }
                    }

                    //If found new block
                    if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {
                        $blockMined = Tools::objectToObject(@unserialize(@file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)),'Block');

                        //Display message
                        Display::NewBlockMined($blockMined);

                        //Stop minning subprocess
                        Tools::clearTmpFolder();
                        Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

                        //Wait 2-2.5s
                        usleep(rand(2000000,2500000));
                    }
                }

                //We check if there are new blocks to be added and that they are next to the last block of the blockchain
                $last_hash_block = $this->chaindata->GetLastBlock()['block_hash'];
                $this->CheckIfPeersHaveMinedBlock($last_hash_block);
            }

            //If we are synchronizing and we are connected with the bootstrap
            else if ($this->syncing) {

                //We get the last block from the BootstrapNode
                $lastBlock_BootstrapNode = BootstrapNode::GetLastBlockNum($this->chaindata,$this->isTestNet);
                $lastBlock_LocalNode = $this->chaindata->GetNextBlockNum();

                if ($lastBlock_LocalNode < $lastBlock_BootstrapNode) {
                    $nextBlocksToSyncFromPeer = BootstrapNode::SyncNextBlocksFrom($this->chaindata, $lastBlock_LocalNode,$this->isTestNet);
                    Peer::SyncBlocks($this->chaindata,$nextBlocksToSyncFromPeer,$lastBlock_LocalNode,$lastBlock_BootstrapNode);
                } else {
                    $this->syncing = false;

                    Display::_printer("%Y%Synchronization%W% finished");

                    //We synchronize the information of the blockchain
                    $this->difficulty = $this->chaindata->GetLastBlock()['difficulty'];

                    //We check the difficulty
                    if ($this->isTestNet)
                        Blockchain::checkDifficulty($this->chaindata,$this->difficulty);

                    //We clean the table of blocks mined by the peers
                    $this->chaindata->truncate("mined_blocks_by_peers");
                    $this->chaindata->truncate("transactions_pending");
                }

                continue;
            }


            //We get the last block from the BootstrapNode and compare it with our local
            $bootstrapNode_lastBlock = ($this->bootstrap_node == true) ? $this->chaindata->GetNextBlockNum():BootstrapNode::GetLastBlockNum($this->chaindata,$this->isTestNet);
            $local_lastBlock = $this->chaindata->GetNextBlockNum();

            if ($local_lastBlock < $bootstrapNode_lastBlock) {

                if ($this->enable_mine && @file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED)) {
                    //Stop minning subprocess
                    Tools::clearTmpFolder();
                    Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
                    Display::NewBlockCancelled();
                }

                $this->syncing = true;
                continue;
            }

            $this->loop_x10();

            usleep(1000000);
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

            $myPeerID = Tools::GetIdFromIpAndPort($this->ip,$this->port);
            $peerID = Tools::GetIdFromIpAndPort($peer['ip'],$peer['port']);

            if ($myPeerID != $peerID) {
                $infoToSend = array(
                    'action' => 'ADDPENDINGTRANSACTIONS',
                    'txs' => $pending_tx
                );

                if ($peer["ip"] == NODE_BOOTSTRAP) {
                    Tools::postContent('https://'.NODE_BOOTSTRAP.'/gossip.php', $infoToSend,5);
                }
                else if ($peer["ip"] == NODE_BOOTSTRAP_TESTNET) {
                    Tools::postContent('https://'.NODE_BOOTSTRAP_TESTNET.'/gossip.php', $infoToSend,5);
                }
                else {
                    Tools::postContent('http://' . $peer['ip'] . ':' . $peer['port'] . '/gossip.php', $infoToSend,5);
                }
            }
        }

        //We delete transactions sent from transactions_pending_to_send
        foreach ($pending_tx as $tx)
            $this->chaindata->removePendingTransactionToSend($tx['txn_hash']);
    }
}
?>