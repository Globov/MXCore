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

class Miner {

    /**
     * We mine the next block
     *
     * @param Gossip $gossip
     * @return bool
     */
    public static function MineNewBlock(&$gossip) {

        //Get Pending transactions
        $transactions_pending = $gossip->chaindata->GetAllPendingTransactions();

        //We calculate the commissions of the pending transactions
        $total_amount_to_miner = "0";
        foreach ($transactions_pending as $txn) {
            $new_txn = new Transaction($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], null,null, $txn['tx_fee'],true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);
            if ($new_txn->isValid()) {
                if ($txn['tx_fee'] == 3)
                    $total_amount_to_miner = bcadd($total_amount_to_miner,"0.00001400",8);
                else if ($txn['tx_fee'] == 2)
                    $total_amount_to_miner = bcadd($total_amount_to_miner,"0.00000900",8);
                else if ($txn['tx_fee'] == 1)
                    $total_amount_to_miner = bcadd($total_amount_to_miner,"0.00000250",8);
            }
        }

        //TODO - Implement halving system
        $total_amount_to_miner = bcadd($total_amount_to_miner,"50",8);

        //We created the mining reward txn + fees txns
        $tx = new Transaction(null,$gossip->coinbase, $total_amount_to_miner, $gossip->key->privKey,"","");

        //We take all pending transactions
        $transactions = array($tx);

        //We add the transactions to the blockchain to generate the block
        foreach ($transactions_pending as $txn) {
            $new_txn = new Transaction($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], null,null, $txn['tx_fee'],true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);
            if ($new_txn->isValid())
                $transactions[] = $new_txn;
        }

        Display::_printer("Start minning block with " . count($transactions) . " txns");

        //We create the new block with the hash of the previous block, the pending transactions, pointer to the blockchain
        $blockMined = new Block($gossip->state->blockchain->blocks[count($gossip->state->blockchain->blocks)-1],$gossip->state->blockchain->difficulty,$transactions,$gossip->state->blockchain);

        //At this point, we have mined the block or someone has mined it

        //We validate that the mined block is valid
        if (strlen($blockMined->hash) > 0 && $blockMined->isValid()) {

            //We warn the network that we have mined this block
            $gossip->sendBlockMinedToNetwork($blockMined);

            return true;

            /*
            //Check if other miner has mined this block
            $last_hash_block = $gossip->state->blockchain->GetLastBlock()->hash;
            $peerMinedBlock = $gossip->chaindata->GetPeersMinedBlockByPrevious($last_hash_block);

            //If haven't peer mined block
            if (!is_array($peerMinedBlock)) {
                //We add the block to the blockchain and it will return us if the difficulty has been modified
                $changedDifficulty = $gossip->state->blockchain->add($blockMined);

                //We get the number of the last block
                $numBlock = $gossip->chaindata->GetNextBlockNum();

                //We add the block to the chaindata (DB)
                if ($gossip->chaindata->addBlock($numBlock,$blockMined))
                    return true;
            }
            */
        }
        return false;
    }

}

?>