oauth2 server


composer require silex/silex:~1.3


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

# server example
mkdir  OAuth2ServerExamples
# 엔터티
mkdir Entities
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/AccessTokenEntity.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/AccessTokenEntity.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/AuthCodeEntity.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/ClientEntity.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/RefreshTokenEntity.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/ScopeEntity.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Entities/UserEntity.php

# 레파지토리
mkdir Repositories
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/AccessTokenRepository.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/AccessTokenRepository.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/AuthCodeRepository.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/ClientRepository.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/RefreshTokenRepository.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/ScopeRepository.php
wget https://raw.githubusercontent.com/thephpleague/oauth2-server/master/examples/src/Repositories/UserRepository.php




# 40 client test
curl -X "POST" "http://localhost:4444/client_credentials.php/access_token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "Accept: 1.0" \
    --data-urlencode "grant_type=client_credentials" \
    --data-urlencode "client_id=myawesomeapp" \
    --data-urlencode "client_secret=abc123" \
    --data-urlencode "scope=basic email"