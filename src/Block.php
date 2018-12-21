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

class Block {
    public $previous;
    public $nonce;
    public $hash;
    public $transactions;
    public $merkle;
    public $timestamp;
    public $timestamp_end;
    public $difficulty;
    public $info;

    /**
     * Block constructor.
     * @param $previous
     * @param $difficulty
     * @param array $transactions
     * @param Blockchain $blockchain
     * @param bool $mined
     * @param null $hash
     * @param int $nonce
     * @param null $timestamp
     * @param null $timestamp_end
     * @param null $info
     */
    public function __construct($previous,$difficulty,$transactions = array(),&$blockchain=null,$mined=false,$hash=null,$nonce=0,$timestamp=null,$timestamp_end=null,$merkle=null,$info = null) {

        $this->transactions = $transactions;
        $this->difficulty = $difficulty;

        //If block is mined
        if ($mined) {
            $this->previous = (strlen($previous) > 0) ? $previous : null;
            $this->hash = $hash;
            $this->nonce = $nonce;
            $this->timestamp = $timestamp;
            $this->timestamp_end = $timestamp_end;
            $this->merkle = $merkle;
            $this->info = $info;
        }
        else {
            $this->previous = $previous ? $previous->hash : null;

            $date = new DateTime();
            $this->timestamp = $date->getTimestamp();

            $this->mine($blockchain);

            $currentBlocksDifficulty = $blockchain->GetLastBlock()->info['current_blocks_difficulty']+1;
            if ($currentBlocksDifficulty > $blockchain->blocks[0]->info['num_blocks_to_change_difficulty']) {
                $currentBlocksDifficulty = 1;
            }

            $currentBlocksHalving = $blockchain->GetLastBlock()->info['current_blocks_halving']+1;
            if ($currentBlocksDifficulty > $blockchain->blocks[0]->info['num_blocks_to_halving']) {
                $currentBlocksDifficulty = 1;
            }

            //We establish the information of the blockchain
            $this->info = array(
                'current_blocks_difficulty' => $currentBlocksDifficulty,
                'current_blocks_halving' => $currentBlocksHalving,
                'max_difficulty' => '0000FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF',
                'num_blocks_to_change_difficulty' => 2016,
                'num_blocks_to_halving' => 250000,
                'time_expected_to_mine' => 20160
            );

            $date = new DateTime();
            $this->timestamp_end = $date->getTimestamp();
        }
    }

    /**
     * Generates the first block in the network
     *
     * @param $coinbase
     * @param $privKey
     * @param $amount
     * @return Block
     */
    public static function createGenesis($coinbase, $privKey, $amount, &$blockchain) {
        return new self(null,1, array(new Transaction(null,$coinbase,$amount,$privKey,"")), "", $blockchain);
    }


    /**
     * Function that prepares the creation of a block and mine
     * Group all transactions of the block + the previous hash
     * Mine the block
     * If during the course of the mining you obtain that another miner has created the block before checking if that block is valid
     * If it is valid, it will stop mining
     * If it is not valid, it will continue to undermine
     *
     * @param Blockchain $blockchain
     */
    public function mine(&$blockchain) {

        //We prepare the transactions that will go in the block
        $data = "";
        if (is_array($this->transactions) && !empty($this->transactions)) {
            //We go through all the transactions and add them to the block to be mined
            foreach ($this->transactions as $transaction) {
                $data = $transaction->message();
            }
        }

        //We add the hash of the previous block
        $data .= $this->previous;

        //We started mining
        $this->nonce = PoW::findNonce($data,$this->previous,$this->difficulty,$blockchain);
        if ($this->nonce !== false) {
            //Make hash and merkle for this block
            $this->hash = PoW::hash($data.$this->nonce);
            $this->merkle = PoW::hash($data.$this->nonce.$this->hash);
        }
        else {
            $this->hash = "";
            $this->merkle = "";
        }

    }

    /**
     * Check function if a block is valid or not
     * Check if all transactions in the block are valid
     * Check if the nonce corresponds to the content of all transactions + hash of the previous block
     *
     * @return bool
     */
    public function isValid() {

        $data = "";
        foreach ($this->transactions as $transaction) {
            $transaction = Tools::objectToObject($transaction,"Transaction");
            if ($transaction->isValid())
                $data = $transaction->message();
            else
                return false;
        }
        $data .= $this->previous;

        return PoW::isValidNonce($data,$this->nonce,$this->difficulty, $this->info['max_difficulty']);
    }
}
?>