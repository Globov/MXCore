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

class PoW {
    /**
     * @param $message
     * @return string
     */
    public static function hash($message) {
        return hash('sha256',$message);
    }

    /**
     * Work test to find the hash that matches the current difficulty
     *
     * @param $message
     * @param $previous_hash
     * @param $difficulty
     * @param Blockchain $blockchain
     * @return bool|int
     */
    public static function findNonce($message,$previous_hash,$difficulty,&$blockchain) {

        //Instanciamos el chaindata
        $chaindata = new DB();

        if ($blockchain->count() == 0)
            $max_difficulty = "00000FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";
        else
            $max_difficulty = $blockchain->blocks[0]->info['max_difficulty'];

        $nonce = 0;
        while(!self::isValidNonce($message,$nonce,$difficulty,$max_difficulty)) {

            //We check if we have a mined block that refers to the previous_hash
            $peerMinedBlock = $chaindata->GetPeersMinedBlockByPrevious($previous_hash);

            //If we do not have block mined by a peer, we will continue to mine
            if ($peerMinedBlock === false) {
                //We increased the nonce to continue in the search to solve the problem
                ++$nonce;

            //If we have a block mined by a peer, we will validate it
            } else if (is_array($peerMinedBlock) && !empty($peerMinedBlock)) {
                //We load the mined block
                $blockMinedByPeer = Tools::objectToObject(@unserialize($peerMinedBlock['block']),"Block");
                if ($blockMinedByPeer->previous != $previous_hash) {
                    ++$nonce;
                } else {
                    if (!$blockMinedByPeer->isValid()) {
                        ++$nonce;
                    } else {
                        //The block mined by the peer is valid, so we must stop mining this block
                        $chaindata->RemovePeerMinedBlockByPrevious($previous_hash);

                        //We obtain the number of the block to be entered
                        $numBlock = $chaindata->GetNextBlockNum();

                        //We add the block to the chaindata and the blockchain
                        $chaindata->addBlock($numBlock,$blockMinedByPeer);

                        //We add the block to the blockchain and it will return us if the difficulty has been modified
                        $changedDifficulty = $blockchain->add($blockMinedByPeer);

                        Display::NewBlockCancelled($numBlock,$blockMinedByPeer);

                        //We add the block to the chaindata (DB)
                        if ($chaindata->addBlock($numBlock,$blockMinedByPeer)) {

                            //TODO REVISAR SISTEMA DIDIFUCLTAD
                            /*
                            //Si se ha modificado la dificultad, actualizamos el conteo en la chaindata
                            if ($changedDifficulty) {
                                if ($chaindata->DifficultyReset())
                                    return true;
                            }
                            */
                            return true;
                        }

                        //Cortamos la POW
                        return false;
                    }
                }
            }
            //We increased the nonce to continue in the search to solve the problem
            ++$nonce;
        }

        $chaindata->db->close();
        return $nonce;
    }

    /**
     * @param $message
     * @param $nonce
     * @param $difficulty
     * @param $maxDifficulty
     * @return bool
     */
    public static function isValidNonce($message,$nonce,$difficulty,$maxDifficulty) {

        $hash = hash('sha256', $message.$nonce);
        $targetHash = bcdiv(Tools::hex2dec($maxDifficulty),$difficulty);
        $hashValue = Tools::hex2dec(strtoupper($hash));

        $result = bccomp($targetHash,$hashValue);

        //Display::_printer('Hash num: '.$nonce.': '.strtoupper($hash));

        if ($result === 1 || $result === 0) {
            return true;
        }
        return false;
    }
}
?>