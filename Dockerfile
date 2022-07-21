FROM phpswoole/swoole:4.8-php8.0-alpine

WORKDIR /src

COPY composer.lock /src
COPY composer.json /src

RUN composer install

COPY . /src

EXPOSE 8080

CMD [ "php", "src/index.php" ]