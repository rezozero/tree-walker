
test:
	php vendor/bin/phpcbf -p
	php vendor/bin/phpstan analyse -c phpstan.neon -l max src
