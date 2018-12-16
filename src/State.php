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

class State {

    public $name;

    /** @var Blockchain $object */
    public $blockchain;
    public $directory;
    /** @var DB $object */
    public $chaindata;

    /**
     * State constructor.
     * @param $name
     * @param $bc
     * @param $chaindata
     */
    public function __construct($name, $bc, &$chaindata)
    {
        $this->name = $name;
        $this->blockchain = $bc;
        $this->chaindata = $chaindata;
    }

    /**
     * We create the base directories (if they did not exist)
     */
    public static function MakeDataDirectory() {

        Display::_printer("Data directory: %G%".State::GetBaseDir()."data".DIRECTORY_SEPARATOR);

        if (!@file_exists(State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets"))
            @mkdir(State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets",777, true);
    }

    /**
     * @return mixed|string
     */
    public static function GetBaseDir() {
        $dir = __DIR__;
        $dir = str_replace('src',"",$dir);
        $dir = str_replace('data',"",$dir);
        $dir = str_replace('bin',"",$dir);

        return $dir;
    }
}


?>