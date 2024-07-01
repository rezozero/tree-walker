
test:
	php vendor/bin/phpcbf -p
	XDEBUG_MODE=coverage vendor/bin/phpunit -v
	php vendor/bin/phpstan analyse -c phpstan.neon
