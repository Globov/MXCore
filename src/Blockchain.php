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
    /**
     * Function that checks the difficulty of the network given the current block
     *
     * @param DB $chaindata
     * @param $height
     * @param $isTestNet
     *
     * @return array
     */
    public static function checkDifficulty(&$chaindata,$height = null,$isTestNet=false) {

        //Get last block or by height
        $currentBlock = ($height == null) ? $chaindata->GetLastBlock(false):$chaindata->GetBlockByHeight($height,false);

        //Initial difficulty
        if ($currentBlock['height'] == 0)
            return [5,1];

        // for first 5 blocks use difficulty 1
        if ($currentBlock['height'] < 5)
            return [5,1];

        // Limit of last blocks to check time
        $limit = 20;
        if ($currentBlock['height'] < 20)
            $limit = $currentBlock['height'] - 1;

        //Get limit check block
        $limitBlock = $chaindata->GetBlockByHeight($currentBlock['height']-$limit);

        //Get diff time
        $dateDiff = date_diff(
            date_create(date('Y-m-d H:i:s', $currentBlock['timestamp_end_miner'])),
            date_create(date('Y-m-d H:i:s', $limitBlock['timestamp_end_miner']))
        );
        $diffTime = (intval($dateDiff->format('%d'))*24*60*60) + (intval($dateDiff->format('%h'))*60*60) + (intval($dateDiff->format('%i'))*60) + intval($dateDiff->format('%s'));
        $avgTime = ceil($diffTime / $limit);

        //Default same difficulty
        $difficulty = $currentBlock['difficulty'];

        //Max 9 - Min 7
        $minAvg = 420;
        $maxAvg = 540;

        //If testnet Max 3 - Min 1
        if ($isTestNet) {
            $minAvg = 105;
            $maxAvg = 180;
        }

        // if lower than 1 min, increase by 5%
        if ($avgTime < $minAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "1.05",2);

        // if bigger than 3 min, decrease by 5%
        elseif ($avgTime > $maxAvg)
            $difficulty = bcmul(strval($currentBlock['difficulty']), "0.95",2);

        //MIn difficulty is 1
        if ($difficulty < 5)
            $difficulty = 5;

        return [$difficulty,$avgTime];
    }

    /**
     * Calc reward by block height
     *
     * @param $currentHeight
     * @param bool $isTestNet
     * @return string
     */
    public static function getRewardByHeight($currentHeight,$isTestNet=false) {

        //Testnet will always be 50
        if ($isTestNet)
            return number_format("50", 8, '.', '');

        // init reward Mainnet
        $reward = 50;

        //Get divisible num
        $divisible = floor($currentHeight / 250000);
        if ($divisible > 0) {

            //Can't divide by 0
            if ($divisible <= 0)
                $divisible = 1;

            // Get current reward
            $reward = ($reward / $divisible) / 2;
        }

        //Reward can't be less than
        if ($reward < 1)
            $reward = 0;

        return number_format($reward, 8, '.', '');
    }

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param Block $lastBlock
     * @param Block $blockMinedByPeer
     * @return bool
     */
    public static function isValidBlockMinedByPeer(&$chaindata,$lastBlock, $blockMinedByPeer) {

        if ($blockMinedByPeer == null)
            return "0x00000004";

        //If the previous block received by network refer to the last block of my blockchain
        if ($blockMinedByPeer->previous != $lastBlock['block_hash']) {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000003");
            return "0x00000003";
        }

        //If the block is valid
        if (!$blockMinedByPeer->isValid()) {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000002");
            return "0x00000002";
        }

        $isTestnet = false;
        if ($chaindata->GetNetwork() == "testnet")
            $isTestnet = true;

        //Get next block height
        $numBlock = $chaindata->GetNextBlockNum();

        //Check if rewarded transaction is valid, prevent hack money
        if ($blockMinedByPeer->isValidReward($numBlock,$isTestnet)) {

            //Add this block in pending block (DISPLAY)
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000000");

            //Propagate mined block to network
            Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer);

            //Add Block to blockchain
             if ($chaindata->addBlock($numBlock,$blockMinedByPeer)) {

                 if ($chaindata->GetConfig('isBootstrap') == 'on' && $chaindata->GetConfig('node_ip') == NODE_BOOTSTRAP)
                     Tools::SendMessageToDiscord($numBlock,$blockMinedByPeer);

                 return "0x00000000";
             } else {
                 return "ERROR NO SE HA PODIDO AGREGAR".$numBlock;
             }
        } else {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"0x00000001");
            return "0x00000001";
        }
    }


    /**
     * Calc total fees of pending transactions to add on new block
     *
     * @param $pendingTransactions
     * @return string
     */
    public static function GetFeesOfTransactions($pendingTransactions) {

        $totalFees = "0";
        foreach ($pendingTransactions as $txn) {
            $new_txn = new Transaction($txn['wallet_from_key'],$txn['wallet_to'], $txn['amount'], null,null, $txn['tx_fee'],true, $txn['txn_hash'], $txn['signature'], $txn['timestamp']);
            if ($new_txn->isValid()) {
                if ($txn['tx_fee'] == 3)
                    $totalFees = bcadd($totalFees,"0.00001400",8);
                else if ($txn['tx_fee'] == 2)
                    $totalFees = bcadd($totalFees,"0.00000900",8);
                else if ($txn['tx_fee'] == 1)
                    $totalFees = bcadd($totalFees,"0.00000250",8);
            }
        }
        return $totalFees;
    }

    /**
     * Check if block received by peer is valid
     * if it is valid, add the block to the temporary table so that the main process adds it to the blockchain
     *
     * @param DB $chaindata
     * @param Block $lastBlock
     * @param Block $blockMinedByPeer
     * @return bool
     */
    public static function isValidBlockMinedByPeerInSameHeight(&$chaindata,$lastBlock, $blockMinedByPeer) {

        //If dont have new block
        if ($blockMinedByPeer == null)
            return "0x00000004";

        //Check if new block is valid
        if (!$blockMinedByPeer->isValid()) {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"1x00000002");
            return "0x00000002";
        }

        //Default, no accept new block
        $acceptNewBlock = false;

        $numNewBlock = Tools::hex2dec($blockMinedByPeer->hash);
        $numLastBlock = Tools::hex2dec($lastBlock['block_hash']);

        //If new block is smallest than last block accept new block
        if (bccomp($numLastBlock, $numNewBlock) == 1)
            $acceptNewBlock = true;


        if ($acceptNewBlock)
            Tools::writeLog('ACCEPTED NEW BLOC');

        //Check if node is on testnet
        $isTestnet = false;
        if ($chaindata->GetNetwork() == "testnet")
            $isTestnet = true;

        //Check if rewarded transaction is valid, prevent hack money
        if ($blockMinedByPeer->isValidReward($lastBlock['height'],$isTestnet)) {

            Tools::writeLog('REWARD VALIDATED');

            if ($acceptNewBlock) {

                //Remove last block
                if ($chaindata->RemoveBlock($lastBlock['height'])) {

                    //AddBlock to blockchain
                    $chaindata->addBlock($lastBlock['height'],$blockMinedByPeer);

                    //Add this block in pending block (DISPLAY)
                    $chaindata->AddBlockToDisplay($blockMinedByPeer,"1x00000000");

                    Tools::writeLog('ADD NEW BLOCK in same height');

                    //Propagate mined block to network
                    Tools::sendBlockMinedToNetworkWithSubprocess($chaindata,$blockMinedByPeer);

                    Tools::writeLog('Propagated new block in same height');

                    return "0x00000000";
                } else
                    return "0x00000001";
            }
        } else {
            $chaindata->AddBlockToDisplay($blockMinedByPeer,"1x00000001");
            return "0x00000001";
        }
    }
}
?>