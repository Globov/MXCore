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

        //Clear stop file of miners
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK);

        //Clear subprocess files
        for($i=0;$i<MINER_MAX_SUBPROCESS;$i++)
            @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$i);

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

        Display::_printer("Start minning block                      %G%txns%W%=" . count($transactions) . "             %G%threads%W%=" . MINER_MAX_SUBPROCESS);

        //Save transactions for this block
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO,@serialize($transactions));
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);

        //Get info to pass miner
        $lastBlock = $gossip->chaindata->GetLastBlock();
        $directoryProcessFile = Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR;

        $network = "mainnet";
        if ($gossip->isTestNet)
            $network = "testnet";

        //Start subprocess miners
        for ($i = 0; $i < MINER_MAX_SUBPROCESS; $i++) {
            $params = array(
                $lastBlock['block_hash'],
                $gossip->difficulty,
                $i,
                MINER_MAX_SUBPROCESS,
                $network
            );
            Subprocess::newProcess($directoryProcessFile,'miner',$params,$i);
        }
    }

}

?>