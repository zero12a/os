oauth2 server

from https://github.com/thephpleague/oauth2-server


# 10 install lib
composer require league/oauth2-server
composer require slim/slim "^3.0"

# 20 down examble server source 
(https://github.com/thephpleague/oauth2-server/tree/master/examples/public)
auth_code.php		
implicit.php		
password.php		
api.php			
client_credentials.php	
middleware_use.php
refresh_token.php

# 30 restart docker

# 40 start oauth server
php -S localhost:4444
