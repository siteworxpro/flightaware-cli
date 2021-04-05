FROM php:cli

RUN apt-get update && \
    apt-get install -y libxml2-dev

RUN docker-php-ext-install soap

ADD . /opt/flightaware
RUN chmod +x /opt/flightaware/fa-cli

WORKDIR /opt/flightaware

ENTRYPOINT ["./fa-cli"]

