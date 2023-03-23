# Windows devsetup commands

This works only when executed from OUTSIDE the container!

## Server commands

- Server SSH `winpty docker-compose -f devsetup/docker-compose.yml exec webserver bash`

## Symfony rebuild assets and cache

    winpty docker-compose -f devsetup/docker-compose.yml exec webserver php bin/console assets:install --symlink web
    winpty docker-compose -f devsetup/docker-compose.yml exec webserver php bin/console assetic:dump --env=dev --no-debug
    winpty docker-compose -f devsetup/docker-compose.yml exec webserver php bin/console cache:clear --env=dev --no-debug
    winpty docker-compose -f devsetup/docker-compose.yml exec webserver chmod -R 0777 /var/www/html/var
    
## Frontend rebuild

    winpty docker-compose -f devsetup/docker-compose.yml exec webserver npm install --prefix src/ScommerceBusinessBundle/Resources/public/frontend/
    winpty docker-compose -f devsetup/docker-compose.yml exec webserver npm run production --prefix src/ScommerceBusinessBundle/Resources/public/frontend/
    
If you want to continuously watch for file changes run

    winpty docker-compose -f devsetup/docker-compose.yml exec webserver npm run watch --prefix src/ScommerceBusinessBundle/Resources/public/frontend/     
    
## xDebug setup

To setup xdebug follow https://devilbox.readthedocs.io/en/latest/intermediate/configure-php-xdebug/windows/phpstorm.html#configure-php-xdebug-win-phpstorm

Set xdebug remote_host in devsetup/lamp/config/php/php.ini and execute `cd devsetup && winpty docker-compose up -d --build --force-recreate && cd ..`

## Performance issues
If you are experiencing performance issues on windows add following changes (one by one as not sure all are necessary :)):
- Go to Hyper-V Manager -> Settings and disable Enhanced session mode
- Go to Hyper-V Manager -> Settings and increase Storage migrations to 4
- Open BIOS and disable power something

## Other windows issues

If you encounter `bash: ./scripts/rebuild-frontend.sh: /bin/bash^M: bad interpreter: No such file or directory` when running sh scripts from inside container run:

    sed -i -e 's/\r$//' scripts/*
    
It is cause by windows line endings...