composer dump-autoload --optimize &> /dev/null

rm -rf var/cache/_sp
mv var/cache/sp var/cache/_sp
rm -rf var/cache/_sp

php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console admin:entity clear_backend_cache

composer rebuild-entities all