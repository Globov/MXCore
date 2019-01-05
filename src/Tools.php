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

class Tools {
    /**
     * Transform a decimal number to hexadecimal
     * Using the php-bcmath package
     *
     * @param $number
     * @return mixed
     */
    public static function dec2hex($number)
    {
        $hexvalues = array('0','1','2','3','4','5','6','7',
            '8','9','A','B','C','D','E','F');
        $hexval = '';
        while($number != '0')
        {
            $hexval = $hexvalues[bcmod($number,'16')].$hexval;
            $number = bcdiv($number,'16',0);
        }
        return Tools::zeropad($hexval,60);
    }

    /**
     * Transform a hexadecimal number to a decimal
     * Using the php-bcmath package
     *
     * @param $number
     * @return mixed
     */
    public static function hex2dec($number)
    {
        $decvalues = array('0' => '0', '1' => '1', '2' => '2',
            '3' => '3', '4' => '4', '5' => '5',
            '6' => '6', '7' => '7', '8' => '8',
            '9' => '9', 'A' => '10', 'B' => '11',
            'C' => '12', 'D' => '13', 'E' => '14',
            'F' => '15');
        $decval = '0';
        $number = strrev($number);
        for($i = 0; $i < strlen($number); $i++)
        {
            $decval = bcadd(bcmul(bcpow('16',$i,0),$decvalues[$number{$i}]), $decval);
        }
        return $decval;
    }

    /**
     * Add zeros in front of a chain
     *
     * @param $num
     * @param $lim
     * @return mixed
     */
    public static function zeropad($num, $lim)
    {
        return (strlen($num) >= $lim) ? $num : self::zeropad("0" . $num, $lim);
    }

    /**
     * Transforms a serialized object into an instantiated object
     *
     * @param $instance
     * @param $className
     * @return mixed
     */
    public static function objectToObject($instance, $className) {
        return @unserialize(sprintf(
            'O:%d:"%s"%s',
            strlen($className),
            $className,
            strstr(strstr(serialize($instance), '"'), ':')
        ));
    }

    /**
     * Get ID from IP and PORT
     *
     * @param $ip
     * @param $port
     * @return bool|string
     */
    public static function GetIdFromIpAndPort($ip,$port) {
        return substr(PoW::hash($ip.$port),0,18);
    }

    /**
     * Send a POST message to a destination
     *
     * @param $url
     * @param $data
     * @param $timeout
     * @param null $username
     * @param null $password
     * @return mixed|string
     */

    public static function postContent($url, $data, $timeout = 20, $username = null, $password = null)
    {
        $postdata = http_build_query($data);
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => $timeout,
            )
        );

        if($username && $password)
            $opts['http']['header'] = ("Authorization: Basic " . @base64_encode("$username:$password"));

        $stream = @stream_context_create($opts);
        $contents = @file_get_contents($url, false, $stream);
        return @json_decode($contents);
    }

    /**
     * Write file with content
     * If file exist,delete
     *
     * @param $file
     * @param $content
     * @param $checkIfExistAndDelete
     */
    public static function writeFile($file,$content='',$checkIfExistAndDelete = false) {

        if ($checkIfExistAndDelete && @file_exists($file))
            @unlink($file);

        $fp = @fopen($file, 'w');
        @fwrite($fp, $content);
        @fclose($fp);
        @chmod($file, 0755);
    }

    /**
     * Write file with content
     * If file exist,delete
     *
     * @param $file
     * @param $content
     * @param $checkIfExistAndDelete
     */
    public static function writeLog($file,$content='',$checkIfExistAndDelete = false) {

        if ($checkIfExistAndDelete && @file_exists($file))
            @unlink($file);

        $fp = @fopen($file, 'a');
        @fwrite($fp, $content);
        @fclose($fp);
        @chmod($file, 0755);
    }

    /**
     * Clear TMP folder
     */
    public static function clearTmpFolder() {
        @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK);
    }

    /**
     * @param DB $chaindata
     * @param $blockMined
     */
    public static function sendBlockMinedToNetwork(&$chaindata,$blockMined) {
        $peers = $chaindata->GetAllPeers();
        foreach ($peers as $peer) {
            $infoToSend = array(
                'action'            => 'MINEDBLOCK',
                'hash_previous'     => $blockMined->previous,
                'block'             => @serialize($blockMined)
            );

            if ($peer["ip"] == NODE_BOOTSTRAP) {
                Tools::postContent('https://'.NODE_BOOTSTRAP.'/gossip.php', $infoToSend,30);
            }
            else if ($peer["ip"] == NODE_BOOTSTRAP_TESTNET) {
                Tools::postContent('https://'.NODE_BOOTSTRAP_TESTNET.'/gossip.php', $infoToSend,30);
            }
            else {
                Tools::postContent('http://' . $peer['ip'] . ':' . $peer['port'] . '/gossip.php', $infoToSend,30);
            }
        }
    }

    /**
     * @param DB $chaindata
     * @param Block $blockMined
     */
    public static function sendBlockMinedToNetworkWithSubprocess(&$chaindata,$blockMined) {

        //Write block cache for propagation subprocess
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_PROPAGATE_BLOCK,@serialize($blockMined));

        if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 3) {
            $mini_hash = substr($blockMined->hash,-12);
            $mini_hash_previous = substr($blockMined->previous,-12);

            Display::_debug("sendBlockMinedToNetworkWithSubprocess  %G%previous%W%=".$mini_hash_previous."  %G%hash%W%=".$mini_hash);
        }

        //Run subprocess propagation per peer
        $peers = $chaindata->GetAllPeers();
        $id = 0;
        foreach ($peers as $peer) {
            //Params for subprocess
            $params = array(
                $peer['ip'],
                $peer['port'],
                1
            );

            //Run subprocess propagation
            Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'propagate',$params,$id);

            $id++;
        }
    }

    /**
     * We create the base directories (if they did not exist)
     */
    public static function MakeDataDirectory() {

        Display::_printer("Data directory: %G%".Tools::GetBaseDir()."data".DIRECTORY_SEPARATOR);

        if (!@file_exists(Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets"))
            @mkdir(Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets",755, true);

        if (!@file_exists(Tools::GetBaseDir().DIRECTORY_SEPARATOR."tmp"))
            @mkdir(Tools::GetBaseDir().DIRECTORY_SEPARATOR."tmp",755, true);
    }

    /**
     * Get base directory
     *
     * @return mixed|string
     */
    public static function GetBaseDir() {
        $dir = __DIR__;
        $dir = str_replace('src',       "",$dir);
        $dir = str_replace('data',      "",$dir);
        $dir = str_replace('cli',       "",$dir);
        $dir = str_replace('bin',       "",$dir);
        $dir = str_replace('subprocess',"",$dir);
        return $dir;
    }

    /**
     * Send a CURL POST message to a destination
     *
     * @param $url
     * @param $data
     * @param $timeout
     * @param null $username
     * @param null $password
     * @return mixed|string
     */
    public static function postContentV1($url, $data, $timeout = 60, $username = null, $password = null)
    {
        try {

            $postdata = http_build_query($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $server_output = json_decode(curl_exec($ch));

            curl_close ($ch);
            return $server_output;
        } catch (Exception $e) {
            return "error";
        }
    }
}
?>
