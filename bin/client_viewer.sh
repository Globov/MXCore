ip=$(curl ident.me)
chmod -R 777 ../
php client.php -user USER -ip $ip -port 80