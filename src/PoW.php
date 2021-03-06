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
     * POW to find the hash that matches the current difficulty
     *
     * @param $idMiner
     * @param $message
     * @param $difficulty
     * @param $startNonce
     * @param $incrementNonce
     * @return mixed
     */
    public static function findNonce($idMiner,$message,$difficulty,$startNonce,$incrementNonce) {
        $max_difficulty = "0000FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";

        $nonce = "0";
        $nonce = bcadd($nonce,strval($startNonce));

        //Save current time
        $lastLogTime = time();

        //Can't start subprocess with mainthread
        if (!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK))
            die('MAINTHREAD NOT FOUND');

        $countIdle = 0;
        $countIdleLog = 0;
        $limitCount = 1000;

        while(!self::isValidNonce($message,$nonce,$difficulty,$max_difficulty)) {

            $countIdle++;
            $countIdleLog++;

            if ($countIdleLog == $limitCount) {
                $countIdleLog = 0;

                //We obtain the difference between first 100000 hashes time and this hash time
                $minedTime = date_diff(
                    date_create(date('Y-m-d H:i:s', $lastLogTime)),
                    date_create(date('Y-m-d H:i:s', time()))
                );
                $timeCheckedHashesSeconds = intval($minedTime->format('%s'));
                $timeCheckedHashesMinutes = intval($minedTime->format('%i'));
                if ($timeCheckedHashesSeconds > 0)
                    $timeCheckedHashesSeconds = $timeCheckedHashesSeconds + ($timeCheckedHashesMinutes * 60);

                $currentLimitCount = $limitCount;
                if ($timeCheckedHashesSeconds <= 0) {
                    $timeCheckedHashesSeconds = 1;
                    $limitCount *= 10;
                }

                $hashRateMiner = $currentLimitCount / $timeCheckedHashesSeconds;

                //Save current time
                $lastLogTime = time();

                Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$idMiner."_hashrate",$hashRateMiner);
                //Subprocess::writeLog("Miners has checked ".$nonce." - Current hash rate: " . $hashRateMiner);

            }

            //Check alive status every 1000 hashes
            if ($countIdle % 1000 == 0) {
                $countIdle = 0;

                //Update "pid" file every 1000 hashes
                Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$idMiner,time());

                //Check if MainThread is alive
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK)) {
                    $mainThreadTime = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK);
                    $minedTime = date_diff(
                        date_create(date('Y-m-d H:i:s', $mainThreadTime)),
                        date_create(date('Y-m-d H:i:s', time()))
                    );
                    $diffTime = $minedTime->format('%s');
                    if ($diffTime >= MINER_TIMEOUT_CLOSE)
                        die('MAINTHREAD NOT FOUND');
                }
                //Quit-Files
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING)) {
                    //Delete "pid" file
                    @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$startNonce);
                    die('STOP MINNING');
                }
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {
                    //Delete "pid" file
                    @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$startNonce);
                    die('BLOCK FOUND');
                }
                if (!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO)) {
                    //Delete "pid" file
                    @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$startNonce);
                    die('NO TX INFO');
                }
            }

            //We increased the nonce to continue in the search to solve the problem
            $nonce = bcadd($nonce,strval($incrementNonce));

        }
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
        if ($result === 1 || $result === 0)
            return true;
        return false;
    }
}
?>