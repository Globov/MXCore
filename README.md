# MXCore
PHP cryptocurrency

- PoW sha256
- Total coins: 56,240,067 MXC
- Blocks every 8 min (average)
- Halving: every 250000 blocks decrease the reward by half

# Requisites

- Open ports for p2p connection
- Apache web server
- OpenSSL
- MySQL Server
- PHP 7.0 or higher
- PHP Extensions:
  - php_mysqli
  - php_bcmath
  - php_curl
  
# TODO
- ~~- Migrate form SQLite to MySQL~~
- ~~- Add Merkle Tree~~
- ~~- Improve Explorer~~
- Make MX Console Client
- ~~- Make testnet~~
- ~~- Multithread~~
- Add version system to make hardforks
  
# Public Explorer MXCoin (MAINNET)

https://blockchain.mataxetos.es/

# Public Explorer MXCoin (TESTNET)
https://testnet.mataxetos.es/

# How run
- Clone repository on root website folder
- Create a MySQL database UTF8
- Edit CONFIG.php and set MySQL info & PHP Run command
- Edit apache2.conf (Default: /etc/apache2/apache2.conf) and change:
```
    <Directory /var/www/>
    ...
    AllowOverride None -> AllowOverride All
    ...
    </Directory>
```

- Navigate into bin folder

For miner node:
```
./node_miner.sh
```

For viewer node:
```
./node_viewer.sh
```
  
# CLIENT available arguments
|ARGUMENT   	|Description   	|
|---	|---	|
|user   	|Set the node user name   	|
|ip   	|Set the IP that the node will use   	|
|port   	|Set the port that the node will use   	|
|mine   	|Activate mining mode   	|
|testnet   	|Connect to TESTNET network   	|

Examples of use:
```
php client.php -u USER1 -ip 0.0.0.0 -port 8080
php client.php -u USER1 -ip 0.0.0.0 -port 8080 -mine
php client.php -u USER1 -ip 0.0.0.0 -port 8080 -mine -testnet
```

# Discord server
https://discord.gg/WNhJZuQ

Anyone is welcome to contribute to MXCoin! 
If you have a fix or code change, feel free to submit it as a pull request directly to the "master" branch.

# Cryptocurrency under construction

# Donations
ETH: 0x33c6cea9136d30071c1b015cc9a2b4d1ad17848d

MXC: VTx31b9ad4ac95a8f4d4ba7f4c5bb908e20
