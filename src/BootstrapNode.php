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

class BootstrapNode {

    public static $ip = "blockchain.mataxetos.es";

    /**
     *
     * We get the last block of the BootstrapNode
     *
     * @param DB $chaindata
     * @return int|mixed
     */
    public static function GetPeers(&$chaindata) {
        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'GETPEERS'
        );

        $infoPOST = Tools::postContent('https://' . self::$ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the last block of the BootstrapNode
     *
     * @param DB $chaindata
     * @return int|mixed
     */
    public static function GetPendingTransactions(&$chaindata) {
        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'GETPENDINGTRANSACTIONS'
        );

        $infoPOST = Tools::postContent('https://' . self::$ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the last block of the BootstrapNode
     *
     * @param DB $chaindata
     * @return int
     */
    public static function GetLastBlockNum(&$chaindata) {
        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'LASTBLOCKNUM'
        );

        $infoPOST = Tools::postContent('https://' . self::$ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We obtain the GENESIS block from the BootstrapNode
     *
     * @param DB $chaindata
     * @return mixed
     */
    public static function GetGenesisBlock(&$chaindata) {
        $bootstrapNode = $chaindata->GetBootstrapNode();
        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'GETGENESIS'
        );
        $infoPOST = Tools::postContent('https://' . self::$ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * We get the next 100 blocks given a current height
     *
     * @param DB $chaindata
     * @param int $lastBlockOnLocalBlockChain
     * @return mixed
     */
    public static function SyncNextBlocksFrom(&$chaindata, $lastBlockOnLocalBlockChain) {
        $bootstrapNode = $chaindata->GetBootstrapNode();

        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'SYNCBLOCKS',
            'from' => $lastBlockOnLocalBlockChain
        );
        $infoPOST = Tools::postContent('https://' . self::$ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }

    /**
     *
     * Returns the information of the BootstrapNode blockchain
     *
     * @param DB $chaindata
     * @return mixed
     */
    public static function GetInfoBlockchain(&$chaindata) {
        $bootstrapNode = $chaindata->GetBootstrapNode();

        //Nos comunicamos con el BOOTSTRAP_NODE
        $infoToSend = array(
            'action' => 'SYNCBLOCKCHAININFO',
        );
        $infoPOST = Tools::postContent('https://' . self::$ip . '/gossip.php', $infoToSend);
        if ($infoPOST->status == 1)
            return $infoPOST->result;
        else
            return 0;
    }
}
?>