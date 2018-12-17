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

class Wallet {

    /**
     * Load or create new wallet
     *
     * @param $account
     * @param $password
     * @return array|mixed
     */
    public static function LoadOrCreate($account,$password) {

        //By default, the file we want to check is the name of the account
        $wallet_file = State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.$account.".dat";

        //If the wallet exists, we load it
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        } else {
            //There is no wallet so we generate the public and private key
            $keys = Pki::generateKeyPair($password);

            //If the account we want to create is different from the coinbase account, we will save the information with the name of the address file
            if ($account != "coinbase")
                $wallet_file = State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.Wallet::GetWalletAddressFromPubKey($keys['public']).".dat";

            //We keep the keys
            @file_put_contents($wallet_file, serialize($keys));

            return $keys;
        }
    }

    /**
     * Get coinbase info
     *
     * @return bool|mixed
     */
    public static function GetCoinbase() {
        $wallet_file = State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR."coinbase.dat";
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        }
        return false;
    }

    /**
     * Get wallet info
     *
     * @param $address
     * @return bool|mixed
     */
    public static function GetWallet($address) {
        $wallet_file = State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.$address.".dat";
        if (file_exists($wallet_file)) {
            return unserialize(@file_get_contents($wallet_file));
        }
        return false;
    }

    /**
     * Get Balance of wallet
     *
     * @param $address
     * @return int|mixed
     */
    public static function GetBalance($address) {

        //Instanciamos el puntero al chaindata
        $chaindata = new DB();

        //Obtenemos lo que ha recibido el usuario en esta cartera
        $totalReceived = $chaindata->db->querySingle("SELECT sum(amount) as TotalReceived FROM transactions WHERE wallet_to = '".$address."';");

        //Obtenemos lo que ha gastado el usuario (pendiente o no de tramitar)
        $totalSpended = $chaindata->db->querySingle("SELECT sum(amount) as TotalSpended FROM transactions WHERE wallet_from = '".$address."';");
        $totalSpendedPending = $chaindata->db->querySingle("SELECT sum(amount) as TotalSpended FROM transactions_pending WHERE wallet_from = '".$address."';");
        $totalSpendedPendingToSend = $chaindata->db->querySingle("SELECT sum(amount) as TotalSpended FROM transactions_pending_to_send WHERE wallet_from = '".$address."';");


        //Sumamos todo lo que el usuario ha gastado (pendiente de enviar o ya enviado)
        $total_spended = 0;
        if ($totalSpended != null)
            $total_spended += $totalSpended;
        if ($totalSpendedPending != null)
            $total_spended += $totalSpendedPending;
        if ($totalSpendedPendingToSend != null)
            $total_spended += $totalSpendedPendingToSend;

        return $totalReceived - $total_spended;
    }

    /**
     * Gets the wallet address of a public key
     *
     * @param $pubKey
     * @return string
     */
    public static function GetWalletAddressFromPubKey($pubKey) {
        return "VTx".md5($pubKey);
    }

    /**
     * Given a direction, get the wallet
     *
     * @param $address
     * @return string
     */
    public static function GetWalletAddressFromAddress($address) {
        if (strpos("VTx",$address) === false)
            return "VTx".$address;
        else
            return $address;
    }

    /**
     * Enviamos una transaccion
     *
     * @param $wallet_from
     * @param $wallet_from_password
     * @param $wallet_from_info
     * @param $wallet_to
     * @param $amount
     * @param $tx_fee
     * @return string
     */
    public static function SendTransaction($wallet_from,$wallet_from_password,$wallet_to,$amount,$tx_fee) {

        if ($tx_fee == 3 && $amount < 0.00014000)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;

        if ($tx_fee == 2 && $amount < 0.00009000)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;

        if ($tx_fee == 1 && $amount < 0.00000250)
            return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;

        if ($wallet_from == "coinbase") {
            $wallet_from_info = self::GetCoinbase();
            $wallet_from = self::GetWalletAddressFromPubKey($wallet_from_info['public']);
        } else {
            $wallet_from_info = self::GetWallet($wallet_from);
        }

        if ($wallet_to == "coinbase")
            $wallet_to = self::GetWalletAddressFromPubKey(self::GetCoinbase()['public']);

        // If have wallet from info
        if ($wallet_from_info !== false) {
            // Get current balance of wallet
            $currentBalance = self::GetBalance($wallet_from);

            // If have balance
            if ($currentBalance >= $amount) {

                if ($tx_fee == 3)
                    $amount = bcsub($amount,"0.00001400",8);
                else if ($tx_fee == 2)
                    $amount = bcsub($amount,"0.00000900",8);
                else if ($tx_fee == 1)
                    $amount = bcsub($amount,"0.00000250",8);

                //Make transaction and sign
                $transaction = new Transaction($wallet_from_info["public"],$wallet_to,$amount,$wallet_from_info["private"],$wallet_from_password,$tx_fee);

                // Check if transaction is valid
                if ($transaction->isValid()) {

                    //Instance the pointer to the chaindata
                    $chaindata = new DB();

                    //We add the pending transaction to send into our chaindata
                    $chaindata->addPendingTransactionToSend($transaction->message(),$transaction);

                    $return_message = "Transaction created successfully".PHP_EOL;
                    $return_message .= "TX: ".ColorsCLI::$FG_GREEN. $transaction->message().ColorsCLI::$FG_WHITE.PHP_EOL;
                    return $return_message;
                } else {
                    return "An error occurred while trying to create the transaction".PHP_EOL."The wallet_from password may be incorrect".PHP_EOL;
                }
            } else {
                return ColorsCLI::$FG_RED."Error".ColorsCLI::$FG_WHITE." There is not enough balance in the account".PHP_EOL;
            }
        } else {
            $return_message = "Could not find the ".ColorsCLI::$FG_RED."public/private key".ColorsCLI::$FG_WHITE." of wallet ".ColorsCLI::$FG_GREEN.$wallet_from.ColorsCLI::$FG_WHITE.PHP_EOL;
            $return_message .= "Please check that in the directory ".ColorsCLI::$FG_CYAN.State::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets".DIRECTORY_SEPARATOR.ColorsCLI::$FG_WHITE." there is the keystore of the wallet".PHP_EOL;
            return $return_message;
        }
    }

}
?>