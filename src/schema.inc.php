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


$dbversion = (isset($_CONFIG['dbversion'])) ? intval($_CONFIG['dbversion']):0;
if ($dbversion == 0) {

    $db->db->query("
    CREATE TABLE IF NOT EXISTS `blocks` (
      `height` int(200) unsigned NOT NULL,
      `block_previous` varchar(64) DEFAULT NULL,
      `block_hash` varchar(64) NOT NULL,
      `root_merkle` varchar(64) NOT NULL,
      `nonce` bigint(200) NOT NULL,
      `timestamp_start_miner` varchar(12) NOT NULL,
      `timestamp_end_miner` varchar(12) NOT NULL,
      `difficulty` varchar(255) NOT NULL,
      `version` varchar(10) NOT NULL,
      `info` text NOT NULL,
      PRIMARY KEY (`height`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `transactions` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `transactions_pending` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `transactions_pending_to_send` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `peers` (
      `id` int(11) NOT NULL,
      `ip` varchar(120) NOT NULL,
      `port` varchar(8) NOT NULL,
      PRIMARY KEY (`ip`,`port`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `mined_blocks_by_peers` (
      `previous_hash` varchar(64) NOT NULL,
      `block` blob NOT NULL,
      PRIMARY KEY (`previous_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    $db->db->query("
    CREATE TABLE IF NOT EXISTS `config` (
      `cfg` varchar(200) NOT NULL,
      `val` varchar(200) NOT NULL,
      PRIMARY KEY (`cfg`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("INSERT INTO config SET cfg='dbversion', val='1';");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 1) {
    $db->db->query("
    ALTER TABLE `peers`
    ADD COLUMN `id`  int(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST ,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`id`);
    ");

    $db->db->query("
    ALTER TABLE `transactions`
    ADD INDEX `wallet_from_to` (`wallet_from`, `wallet_to`) USING HASH;
    ");

    $db->db->query("
    ALTER TABLE `transactions_pending`
    ADD INDEX `wallet_from_to` (`wallet_from`, `wallet_to`) USING HASH;
    ");

    $db->db->query("
    ALTER TABLE `transactions_pending_to_send`
    ADD INDEX `wallet_from_to` (`wallet_from`, `wallet_to`) USING HASH;
    ");

    $db->db->query("
    ALTER TABLE `mined_blocks_by_peers`
    MODIFY COLUMN `block`  text NOT NULL AFTER `previous_hash`;
    ");


    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 2) {
    $db->db->query("
    CREATE TABLE `blocks_pending_by_peers` (
      `height` int(200) unsigned NOT NULL AUTO_INCREMENT,
      `status` varchar(10) NOT NULL,
      `block_previous` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `root_merkle` varchar(64) NOT NULL,
      `nonce` bigint(200) NOT NULL,
      `timestamp_start_miner` varchar(12) NOT NULL,
      `timestamp_end_miner` varchar(12) NOT NULL,
      `difficulty` varchar(255) NOT NULL,
      `version` varchar(10) NOT NULL,
      `info` text NOT NULL,
      PRIMARY KEY (`height`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    $db->db->query("
    CREATE TABLE `transactions_pending_by_peers` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`),
      KEY `wallet_from_to` (`wallet_from`,`wallet_to`) USING HASH
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    $db->db->query("DROP TABLE mined_blocks_by_peers");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 3) {

    //This hotfix only needed on mainnet
    if ($_CONFIG['network'] == 'mainnet') {

        //Get current height
        $currentHeight = $db->GetNextBlockNum();

        //If height is greater than bug blockchain height
        if ($currentHeight > 10559) {

            //Get difficulty of last block
            $lastDifficulty = $db->db->query("SELECT difficulty FROM blocks ORDER BY height DESC LIMIT 1;")->fetch_assoc();
            if (!empty($lastDifficulty)) {

                //if difficulty is 1
                if ($lastDifficulty['difficulty'] == 1) {

                    Display::_printer("Executing HOT FIX #2");

                    //Remove bugged transactions
                    $db->db->query("
                    DELETE FROM transactions WHERE block_hash IN (
                      SELECT block_hash FROM blocks WHERE height > 10559
                    );
                    ");

                    //Remove bugged blocks
                    $db->db->query("DELETE FROM blocks WHERE height > 10559");

                    Display::_printer("Finished HOT FIX #2");
                }
            }
        }
    }

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 4) {
    $db->db->query("
    ALTER TABLE `peers`
    ADD COLUMN `blacklist`  varchar(12) NULL AFTER `port`;
    ");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 5) {

    //Create new tmp table for blocks
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `blocks_announced` (
    `id`  int(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
    `block_hash`  varchar(128) NOT NULL ,
    PRIMARY KEY (`id`)
    )");

    //Rename table
    $db->db->query("RENAME TABLE blocks_pending_by_peers TO blocks_pending_to_display;");

    //Remove unused tabled
    $db->db->query("DROP TABLE transactions_pending_by_peers");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 6) {

    $db->db->query("ALTER TABLE `blocks` MODIFY COLUMN `nonce` varchar(200) NOT NULL AFTER `root_merkle`;");
    $db->db->query("ALTER TABLE `blocks_pending_to_display` MODIFY COLUMN `nonce` varchar(200) NOT NULL AFTER `root_merkle`;");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 7) {

    $db->db->query('
    ALTER TABLE `blocks`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`height`, `block_hash`);
    ');

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}


// update dbversion
if ($dbversion != $_CONFIG['dbversion']) {
    $db->SetConfig('dbversion',$dbversion);
}

Display::_printer("DB Schema updated");

?>