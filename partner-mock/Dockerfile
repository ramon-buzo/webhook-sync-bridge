FROM php:8.2-cli-alpine

RUN apk add --no-cache curl

COPY index.php /app/index.php

WORKDIR /app

EXPOSE 8082

CMD ["php", "-S", "0.0.0.0:8082", "index.php"]