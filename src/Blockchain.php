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

class Blockchain {

    public $blocks = [];
    public $difficulty;
    public $blocks_count_reset;
    public $blocks_count_halving;

    /**
     * Blockchain constructor.
     * @param $coinbase
     * @param $privKey
     * @param $amount
     * @param bool $newBC
     */
    public function __construct($coinbase,$privKey,$amount,$newBC=true)
    {
        if ($newBC) {

            $this->difficulty = 1;
            $this->blocks_count_reset = 0;
            $this->blocks_count_halving = 0;

            //We mine the GENESIS Block
            $this->blocks[] = Block::createGenesis($coinbase,$privKey,$amount,$this);
        }
        else {
            $this->blocks = $coinbase;
        }
    }

    /**
     * @return int
     */
    public function count() {
        if (isset($this->blocks))
            return count($this->blocks);
        return 0;
    }

    /**
     * @return int|mixed
     */
    public function GetLastBlock() {
        if (isset($this->blocks))
            return $this->blocks[$this->count()-1];
        return 0;
    }

    /**
     * @return int|mixed
     */
    public function GetPreviousBlock() {
        if (isset($this->blocks))
            return $this->blocks[$this->count()-2];
        return 0;
    }

    /**
     * @param $block
     * @return bool
     */
    public function add($block) {

        //We add the block to the block chain
        $this->blocks[] = $block;

        //We increase the number of blocks processed for the reset of the difficulty and the reward halving
        $this->blocks_count_reset++;
        $this->blocks_count_halving++;

        //We check the difficulty of the network
        return $this->checkDifficulty();
    }

    /**
     * Add a block to the blockchain
     *
     * @param $blockID
     * @param $block
     * @param bool $counting
     * @return bool
     */
    public function addSync($blockID, $block,$counting=false) {

        //We add the block to the block chain
        $this->blocks[$blockID] = $block;


        //We increase the number of blocks processed for the reset of the difficulty and the reward halving
        if ($counting) {
            $this->blocks_count_reset++;
            $this->blocks_count_halving++;
        }

        //We check the difficulty of the network
        return $this->checkDifficulty();
    }

    /**
     * Function that checks the difficulty of the network given the current block
     *
     * @return bool
     */
    public function checkDifficulty() {
        if ($this->blocks_count_reset >= $this->blocks[0]->info['num_blocks_to_change_difficulty']) {

            //We obtain the number of minutes in which the previous 2016 blocks have been mined
            $minutesMinedLast2016Blocks = round(abs($this->blocks[(count($this->blocks)-$this->blocks[0]->info['num_blocks_to_change_difficulty'])]->timestamp - $this->blocks[(count($this->blocks) - 1)]->timestamp) / 60,0);

            if ($minutesMinedLast2016Blocks <= 0)
                $minutesMinedLast2016Blocks = 1;

            //We get the difficulty setting
            $adjustDifficulty = $this->blocks[0]->info['time_expected_to_mine'] / $minutesMinedLast2016Blocks;

            //We readjusted the difficulty
            $this->difficulty *= $adjustDifficulty;

            //If the difficulty is less than 1, we set it to 1
            //The difficulty can not be less than 1 because, if not the target of cut for validity, a hash would exceed the maximum hash
            if ($this->difficulty < 1)
                $this->difficulty = 1;

            //We reset the counter
            $this->blocks_count_reset = 0;

            return true;
        }
        return false;
    }

    /**
     * We load the information of a blockchain received by network
     *
     * @param DB $chaindata
     * @return Blockchain|bool
     */
    public static function loadFromChaindata(&$chaindata) {

        //We load the blockchain blocks from the chaindata
        $blocks_to_import = array();
        $blocks_chaindata = $chaindata->db->query("SELECT * FROM blocks ORDER BY height ASC");

        //By default we create an empty blockchain
        $bc = new Blockchain(null,null,null,false);

        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            $height = 0;
            while ($blockInfo = $blocks_chaindata->fetchArray(SQLITE3_ASSOC)) {

                $transactions_chaindata = $chaindata->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."';");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetchArray(SQLITE3_ASSOC)) {

                        $transactions[] = new Transaction(
                            $transactionInfo["wallet_from_key"],
                            $transactionInfo["wallet_to"],
                            $transactionInfo["amount"],
                            null,
                            null,
                            (isset($transactionInfo["tx_fee"])) ? $transactionInfo["tx_fee"]:'',
                            true,
                            $transactionInfo["txn_hash"],
                            $transactionInfo["signature"],
                            $transactionInfo["timestamp"]
                        );
                    }
                }

                $blockchainNull = "";
                $blocks_to_import[] = new Block(
                    $blockInfo['block_previous'],
                    $blockInfo['difficulty'],
                    $transactions,
                    $blockchainNull,
                    true,
                    $blockInfo['block_hash'],
                    $blockInfo['nonce'],
                    $blockInfo['timestamp_start_miner'],
                    $blockInfo['timestamp_end_miner'],
                    @unserialize($blockInfo['info'])
                );
            }

            //If we have blocks to import into the array, we pass them as the first parameter
            if (!empty($blocks_to_import)) {
                $bc = new Blockchain($blocks_to_import,null,null,false);
            }
        }

        //If the blockchain has blocks
        if ($bc->count() > 0) {
            $bc->difficulty = $bc->GetLastBlock()->difficulty;
            $bc->blocks_count_reset = $bc->GetLastBlock()->info['current_blocks_difficulty'];
            $bc->blocks_count_halving = $bc->GetLastBlock()->info['current_blocks_halving'];
        } else {
            $bc->difficulty = 1;
            $bc->blocks_count_reset = 1;
            $bc->blocks_count_halving = 1;
        }

        return $bc;

    }

    /**
     * @return bool
     */
    public function isValid() {
        foreach ($this->blocks as $i => $block) {
            $block = Tools::objectToObject($block,"Block");
            if (!$block->isValid())
                return false;

            if ($i != 0 && $this->blocks[$i - 1]->hash != $block->previous)
                return false;
        }
        return true;
    }
}
?>