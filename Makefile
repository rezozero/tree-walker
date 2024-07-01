
test:
	php vendor/bin/phpcbf -p
	php vendor/bin/phpunit
	php vendor/bin/phpstan analyse -c phpstan.neon
