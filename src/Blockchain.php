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
     * @param $currentDifficulty
     *
     * @return bool
     */
    public static function checkDifficulty(&$chaindata,&$currentDifficulty) {

        $genesisBlock = $chaindata->GetGenesisBlock();
        $genesisBlockInfo = @unserialize($genesisBlock['info']);

        $lastBlock = $chaindata->GetLastBlock();
        $lastBlockInfo = @unserialize($lastBlock['info']);

        //Check current count blocks difficulty
        if ($lastBlockInfo['current_blocks_difficulty'] == $genesisBlockInfo['num_blocks_to_change_difficulty']) {

            $firstBlockToCheck = $chaindata->GetBlockByHeight($chaindata->GetNextBlockNum() - $genesisBlockInfo['num_blocks_to_change_difficulty']);
            $lastBlockToCheck = $chaindata->GetLastBlock();

            //We obtain the number of minutes in which the previous 2500 blocks have been mined
            $minutesMinedLast2500Blocks = round(abs($firstBlockToCheck['timestamp_end_miner'] - $lastBlockToCheck['timestamp_end_miner']) / 60,0);

            //$minutesMinedLast2016Blocks = round(abs($this->blocks[1]->timestamp - $this->blocks[(count($this->blocks) - 1)]->timestamp) / 60,0);

            if ($minutesMinedLast2500Blocks <= 0)
                $minutesMinedLast2500Blocks = 1;

            //We get the difficulty setting
            $adjustDifficulty = $genesisBlockInfo['time_expected_to_mine'] / $minutesMinedLast2500Blocks;

            //We readjusted the difficulty
            $currentDifficulty *= $adjustDifficulty;

            //If the difficulty is less than 1, we set it to 1
            //The difficulty can not be less than 1 because, if not the target of cut for validity, a hash would exceed the maximum hash
            if ($currentDifficulty < 1)
                $currentDifficulty = 1;

            return true;
        }
        return false;
    }
}
?>