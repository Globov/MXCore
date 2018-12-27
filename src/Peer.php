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

class Peer {

    /**
     * @param DB $chaindata
     * @param $nextBlocksToSyncFromPeer
     */
    public static function SyncBlocks(&$chaindata,$nextBlocksToSyncFromPeer,$currentBlocks,$totalBlocks) {
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
                '',
                '',
                '',
                '',
                true,
                $object->block_hash,
                $object->nonce,
                $object->timestamp_start_miner,
                $object->timestamp_end_miner,
                $object->root_merkle,
                $infoBlock
            );

            //If the block is valid and the previous one refers to the last block of the local blockchain
            if ($block->isValid()) {

                //We add the block to the chaindata and blockchain
                $chaindata->addBlock($object->height,$block);

                $blocksSynced++;
            } else {
                Display::_printer("BLOCK: " . $block->hash . " NO VALID");
                break;
            }
        }
        Display::_printer("%Y%Imported%W% new blocks headers              %G%count%W%=".$blocksSynced."             %G%current%W%=".$currentBlocks."   %G%total%W%=".$totalBlocks);
    }

    /**
     *
     * We get the next 100 blocks given a current height
     *
     * @param DB $chaindata
     * @param int $lastBlockOnLocalBlockChain
     * @param bool $isTestNet
     * @return mixed
     */
    public static function SyncNextBlocksFrom(&$chaindata, $lastBlockOnLocalBlockChain,$isTestNet=false) {

        if ($isTestNet) {
            $ip = NODE_BOOTSTRAP_TESTNET;
            $port = NODE_BOOSTRAP_PORT_TESTNET;
        } else {
            $ip = NODE_BOOTSTRAP;
            $port = NODE_BOOSTRAP_PORT;
        }

        $bootstrapNode = $chaindata->GetBootstrapNode();

        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'SYNCBLOCKS',
            'from' => $lastBlockOnLocalBlockChain
        );
        $infoPOST = Tools::postContent('https://' . $ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

}