
test:
	php vendor/bin/rector
	php vendor/bin/php-cs-fixer fix --ansi -vvv
	php vendor/bin/phpstan analyse -c phpstan.neon
	php vendor/bin/phpunit
