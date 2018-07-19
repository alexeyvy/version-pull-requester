FROM php:7.0-cli
COPY . /usr/src/version-pull-requester
WORKDIR /usr/src/version-pull-requester
RUN apt-get update && \
    apt-get install -y --no-install-recommends git zip unzip zlib1g-dev
RUN curl --silent --show-error https://getcomposer.org/installer | php
RUN php composer.phar install
CMD [ "php", "/usr/src/version-pull-requester/entrypoint.php" ]
