
test:
	php vendor/bin/php-cs-fixer fix --ansi -vvv
	php vendor/bin/phpunit
	php vendor/bin/phpstan analyse -c phpstan.neon
