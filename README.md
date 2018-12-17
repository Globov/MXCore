# MXCore
PHP cryptocurrency

PoW sha256

# Requisites

- Apache web server
- PHP 7.0 or higher
- PHP Extensions:
  - php_sqlite3
  - php_bcmath
  - php_curl
  
# Plans
- Improve Explorer
- Make MX Console Client
- Make testnet
- Add more bootstrap nodes
- Add version system
- Add PoolMinning system
  
# Public Explorer MXCoin

https://blockchain.mataxetos.es/

# How run
- Clone repository on root website folder
- Navigate into bin folder
- Edit apache2.conf and change:

    <Directory /var/www/>
    
    ...
    
    AllowOverride None -> AllowOverride All
    
    ...
    
    </Directory>

For miner node:
  - ./node_miner.sh

For viewer node:
  - ./node_viewer.sh
  
# Discord server
https://discord.gg/WNhJZuQ

Anyone is welcome to contribute to MXCoin! 
If you have a fix or code change, feel free to submit it as a pull request directly to the "master" branch.

# Cryptocurrency under construction

# Donations
ETH: 0x33c6cea9136d30071c1b015cc9a2b4d1ad17848d

MXC: VTx31b9ad4ac95a8f4d4ba7f4c5bb908e20
