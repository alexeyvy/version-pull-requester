# Building
docker build -t version-pull-requester .

# Running
docker run -it --rm --name running-version-pull-requester version-pull-requester

# Running tests
docker run -it --rm --name running-version-pull-requester version-pull-requester vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php test
