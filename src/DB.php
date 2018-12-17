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

class DB {

    public $db;

    /**
     * DB constructor.
     */
    public function __construct() {
        //We create or load the database
        $this->db = new SQLite3(State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."chaindata");

        //If we are not in WINDOWS, we set the directory permissions in 777
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            exec("chmod -R 777 ".State::GetBaseDir());

        //Set the shortest busy mode
        $this->db->busyTimeout(5000);

        //We activate the WAL mode
        //+Info: https://www.sqlite.org/wal.html
        $this->db->exec("PRAGMA journal_mode = wal;");

        //We check if the tables needed for the blockchain are created
        $this->CheckIfExistTables();
    }

    /**
     * Execute a SQLite statement towards the chaindata
     *
     * @param $sql
     * @return bool
     */
    public function exec($sql) {
        if ($this->db->exec($sql))
            return true;
        return false;
    }

    /**
     * @param $table
     * @return bool
     */
    public function truncate($table) {
        if ($this->db->exec("DELETE FROM " . $table.";"))
            return true;
        return false;
    }

    /**
     * @return bool|mixed
     */
    public function GetBootstrapNode() {
        //Seleccionamos el primer peer (Que sera el bootstrap node)
        $info_mined_blocks_by_peer = $this->db->querySingle("SELECT * FROM peers LIMIT 1;",true);
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Add a peer to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->querySingle("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';",true);
        if (empty($info_mined_blocks_by_peer)) {
            if ($this->db->exec("INSERT INTO peers (ip,port) VALUES ('".$ip."', '".$port."');"))
                return true;
        }
        return false;
    }

    /**
     * Returns whether or not we have this peer saved in the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function haveThisPeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->querySingle("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';",true);
        if (!empty($info_mined_blocks_by_peer)) {
            return true;
        }
        return false;
    }

    /**
     * Returns a block given a hash
     *
     * @param $hash
     * @return bool
     */
    public function GetBlockByHash($hash) {
        $sql = "SELECT * FROM blocks WHERE block_hash = '".$hash."'";
        $info_block = $this->db->querySingle($sql,true);
        if (!empty($info_block)) {

            $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."';");
            $transactions = array();
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetchArray(SQLITE3_ASSOC)) {
                    $transactions[] = $transactionInfo;
                }
            }

            $info_block["transactions"] = $transactions;

            return $info_block;
        }
        return null;
    }

    /**
     * Returns a transaction given a hash
     *
     * @param $hash
     * @return bool
     */
    public function GetTransactionByHash($hash) {
        $sql = "SELECT * FROM transactions WHERE txn_hash = '".$hash."';";
        $info_txn = $this->db->querySingle($sql,true);
        if (!empty($info_txn)) {
            return $info_txn;
        }
        return null;
    }

    /**
     * Returns the information of a wallet
     *
     * @param $hash
     * @return array
     */
    public function GetWalletInfo($wallet) {
        $totalSended = $this->db->querySingle("SELECT sum(T.amount) as TotalSend FROM transactions as T WHERE T.wallet_from = '".$wallet."';");
        $totalReceived = $this->db->querySingle("SELECT sum(T.amount) as TotalReceived FROM transactions as T WHERE T.wallet_to = '".$wallet."';");

        if ($totalSended == null)
            $totalSended = 0;

        if ($totalReceived == null)
            $totalReceived = 0;

        //By default, we have what we have received
        $current = $totalReceived;

        //If we have sent something, we subtract it
        if ($totalSended > 0)
            $current = $totalReceived - $totalSended;

        return array(
            'sended' => $totalSended,
            'received' => $totalReceived,
            'current' => $current
        );
    }

    /**
     * Returns all the transactions of a wallet
     *
     * @param $hash
     * @return array
     */
    public function GetTransactionsByWallet($wallet) {
        $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE wallet_to = '".$wallet."' OR wallet_from = '".$wallet."' ORDER BY timestamp DESC;");
        $transactions = array();
        if (!empty($transactions_chaindata)) {
            while ($transactionInfo = $transactions_chaindata->fetchArray(SQLITE3_ASSOC)) {
                $transactions[] = $transactionInfo;
            }
        }

        return $transactions;
    }

    /**
     * Returns a block given a height
     *
     * @param $hash
     * @return bool
     */
    public function GetBlockByHeight($height) {
        $sql = "SELECT * FROM blocks WHERE height = ".$height.";";
        $info_block = $this->db->querySingle($sql,true);
        if (!empty($info_block)) {

            $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."';");
            $transactions = array();
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetchArray(SQLITE3_ASSOC)) {
                    $transactions[] = $transactionInfo;
                }
            }

            $info_block["transactions"] = $transactions;

            return $info_block;
        }
        return null;
    }

    /**
     * Add a pending transaction to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPendingTransaction($transaction) {
        $into_tx_pending = $this->db->querySingle("SELECT txn_hash FROM transactions_pending WHERE txn_hash = '".$transaction['txn_hash']."';",true);
        if (empty($into_tx_pending)) {

            $sql_update_transactions = "INSERT INTO transactions_pending (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('','".$transaction['txn_hash']."','".$transaction['wallet_from_key']."','".$transaction['wallet_from']."','".$transaction['wallet_to']."','".$transaction['amount']."','".$transaction['signature']."','".$transaction['tx_fee']."','".$transaction['timestamp']."');";
            if ($this->db->exec($sql_update_transactions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return array with all pending transactions
     *
     * @return array
     */
    public function GetAllPendingTransactions() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM transactions_pending ORDER BY tx_fee ASC LIMIT 512");
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetchArray()) {
                $txs[] = $tx_chaindata;
            }
        }
        return $txs;
    }

    /**
     * Add pending transactions received by a peer
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPendingTransactionsByPeer($transactionsByPeer) {
        foreach ($transactionsByPeer as $tx)
            $this->addPendingTransaction($tx);

        return true;
    }

    /**
     * Add a pending transaction to send to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPendingTransactionToSend($txHash,$transaction) {
        $into_tx_pending = $this->db->querySingle("SELECT txn_hash FROM transactions_pending_to_send WHERE txn_hash = '".$txHash."';",true);
        if (empty($into_tx_pending)) {

            $wallet_from_pubkey = "";
            $wallet_from = "";
            if ($transaction->from != null) {
                $wallet_from_pubkey = $transaction->from;
                $wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
            }

            $sql_update_transactions = "INSERT INTO transactions_pending_to_send (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->timestamp."');";
            if ($this->db->exec($sql_update_transactions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete a pending transaction
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePendingTransaction($txHash) {
        $this->db->exec("DELETE FROM transactions_pending WHERE txn_hash='".$txHash."';");
    }

    /**
     * Delete a pending transaction to send
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePendingTransactionToSend($txHash) {
        $this->db->exec("DELETE FROM transactions_pending_to_send WHERE txn_hash='".$txHash."';");
    }

    /**
     * Return array with all pending transactions to send
     *
     * @return array
     */
    public function GetAllPendingTransactionsToSend() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM transactions_pending_to_send");
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetchArray()) {
                $txs[] = $tx_chaindata;
            }
        }
        return $txs;
    }

    /**
     * Remove a peer from the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePeer($ip,$port) {
        //Comprobamos que no hayamos registrado ya este bloque minado
        $info_mined_blocks_by_peer = $this->db->querySingle("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';",true);
        if (!empty($info_mined_blocks_by_peer)) {
            if ($this->db->exec("DELETE FROM peers WHERE ip = '".$ip."' AND port= '".$port."';"))
                return true;
        }
        return false;
    }

    /**
     * Returns an array with all the peers
     *
     * @return array
     */
    public function GetAllPeers() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetchArray()) {
                $ip = str_replace("\r","",$peer['ip']);
                $ip = str_replace("\n","",$ip);

                $port = str_replace("\r","",$peer['port']);
                $port = str_replace("\n","",$port);

                $infoPeer = array(
                    'ip' => $ip,
                    'port' => $port
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

    /**
     * Returns an array with 25 random peers
     *
     * @return array
     */
    public function GetPeers() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers LIMIT 25");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetchArray()) {
                $infoPeer = array(
                    'ip' => $peer['ip'],
                    'port' => $peer['port']
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

    /**
     * Add a block in the chaindata
     *
     * @param $blockNum
     * @param $blockInfo
     * @return bool
     */
    public function addBlock($blockNum,$blockInfo) {
        $info_block_chaindata = $this->db->querySingle("SELECT block_hash FROM blocks WHERE block_hash = '".$blockInfo->hash."';",true);
        if (empty($info_block_chaindata)) {

            //Check if exist previous
            $block_previous = "";
            if ($blockInfo->previous != null)
                $block_previous = $blockInfo->previous;

            //SQL Insert Block
            $sql_insert_block = "INSERT INTO blocks (height,block_previous,block_hash,nonce,timestamp_start_miner,timestamp_end_miner,difficulty,info)
            VALUES (".$blockNum.",'".$block_previous."','".$blockInfo->hash."','".$blockInfo->nonce."','".$blockInfo->timestamp."','".$blockInfo->timestamp_end."','".$blockInfo->difficulty."','".SQLite3::escapeString(@serialize($blockInfo->info))."');";

            if ($this->db->exec($sql_insert_block)) {

                foreach ($blockInfo->transactions as $transaction) {

                    $wallet_from_pubkey = "";
                    $wallet_from = "";
                    if ($transaction->from != null) {
                        $wallet_from_pubkey = $transaction->from;
                        $wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
                    }

                    $sql_update_transactions = "INSERT INTO transactions (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('".$blockInfo->hash."','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->timestamp."');";
                    $this->db->exec($sql_update_transactions);


                    //We eliminated the pending transaction
                    $this->removePendingTransaction($transaction->message());
                    $this->removePendingTransactionToSend($transaction->message());

                }
                return true;
            }
        }
        return false;
    }

    /**
     * Add a block mined by a peer by saving the previous_hash and the mined block
     *
     * @param $previous_hash
     * @param $blockMined
     * @return bool
     */
    public function AddMinedBlockByPeer($previous_hash, $blockMined) {
        $info_mined_blocks_by_peer = $this->db->querySingle("SELECT previous_hash FROM mined_blocks_by_peers WHERE previous_hash = '".$previous_hash."';",true);
        if (empty($info_mined_blocks_by_peer)) {
            if ($this->db->exec("INSERT INTO mined_blocks_by_peers (previous_hash,block) VALUES ('".$previous_hash."', '".$blockMined."');"))
                return true;
        }
        return false;
    }

    /**
     * Return array of mined blocks by peers
     *
     * @param $previous_hash
     * @return bool|mixed
     */
    public function GetPeersMinedBlockByPrevious($previous_hash) {
        $info_mined_blocks_by_peer = $this->db->querySingle("SELECT previous_hash, block FROM mined_blocks_by_peers WHERE previous_hash = '".$previous_hash."';",true);
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Remove a block mined by a peer given a previous_hash
     *
     * @param $previous_hash
     * @return bool
     */
    public function RemovePeerMinedBlockByPrevious($previous_hash) {
        if ($this->db->exec("DELETE FROM mined_blocks_by_peers WHERE previous_hash = '".$previous_hash."';"))
            return true;
        return false;
    }

    /**
     * Returns the next block number in the block chain
     * Must be the number entered in the next block
     *
     * @return mixed
     */
    public function GetNextBlockNum() {
        return $this->db->querySingle("SELECT COUNT(height) as NextBlockNum FROM blocks");
    }

    /**
     * Returns the GENESIS block
     *
     * @return mixed
     */
    public function GetGenesisBlock() {
        $genesis_block = null;
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks WHERE height = 0");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            $height = 0;
            while ($blockInfo = $blocks_chaindata->fetchArray(SQLITE3_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."';");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetchArray(SQLITE3_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $genesis_block = $blockInfo;
            }
        }
        return $genesis_block;

    }

    /**
     * Returns the blocks to be synchronized from the block passed by parameter
     *
     * @param $fromBlock
     * @return array
     */
    public function SyncBlocks($fromBlock) {
        $blocksToSync = array();
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks ORDER BY height ASC LIMIT ".$fromBlock.",100");

        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            $height = 0;
            while ($blockInfo = $blocks_chaindata->fetchArray(SQLITE3_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."';");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetchArray(SQLITE3_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $blocksToSync[] = $blockInfo;
            }
        }
        return $blocksToSync;
    }

    /**
     * Check that the basic tables exist for the blockchain to work
     */
    private function CheckIfExistTables() {
        //We create the tables by default
        $this->db->exec("CREATE TABLE IF NOT EXISTS blocks (height INTEGER NOT NULL, block_previous TEXT, block_hash TEXT NOT NULL, nonce TEXT NOT NULL, timestamp_start_miner TEXT NOT NULL, timestamp_end_miner TEXT NOT NULL, difficulty TEXT NOT NULL, info BLOB NOT NULL);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS transactions (block_hash TEXT NOT NULL, txn_hash TEXT NOT NULL, wallet_from_key BLOB, wallet_from TEXT, wallet_to TEXT NOT NULL, amount TEXT NOT NULL, signature TEXT NOT NULL, tx_fee TEXT, timestamp TEXT NOT NULL);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS transactions_pending (block_hash TEXT NULL, txn_hash TEXT NOT NULL, wallet_from_key BLOB, wallet_from TEXT, wallet_to TEXT NOT NULL, amount TEXT NOT NULL, signature TEXT NOT NULL, tx_fee TEXT, timestamp TEXT NOT NULL);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS transactions_pending_to_send (block_hash TEXT NULL, txn_hash TEXT NOT NULL, wallet_from_key BLOB, wallet_from TEXT, wallet_to TEXT NOT NULL, amount TEXT NOT NULL, signature TEXT NOT NULL, tx_fee TEXT, timestamp TEXT NOT NULL);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS peers (ip TEXT NOT NULL, port TEXT NOT NULL);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS mined_blocks_by_peers (previous_hash TEXT UNIQUE NOT NULL, block BLOB NOT NULL);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS transactions_pending (hash TEXT UNIQUE NOT NULL, tx BLOB NOT NULL);");
    }

}

?>