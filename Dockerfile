#VERSION 1.0.0
FROM keboola/base
MAINTAINER Vojtech Kurka <vokurka@keboola.com>
ENV APP_VERSION 1.1.0

# Image setup
WORKDIR /tmp
RUN rpm -Uvh https://mirror.webtatic.com/yum/el6/latest.rpm
RUN rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm
RUN yum -y --enablerepo=epel,remi,remi-php56 upgrade
RUN yum -y --enablerepo=epel,remi,remi-php56 install \
	git \
	php \
	php-cli \
	php-common \
	php-mbstring \
	php-pdo \
	php-xml \
	php-devel \
	php-pear
RUN echo "date.timezone=UTC" >> /etc/php.ini
RUN echo "memory_limit = -1" >> /etc/php.ini
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

RUN yum update
RUN yum -y install make gcc libssh2 libssh2-devel
RUN printf "\n" | pecl install -f ssh2
RUN echo "extension=ssh2.so"  > /etc/php.ini

WORKDIR /home

RUN git clone https://github.com/vokurka/keboola-silverpop-ex ./
RUN composer install --no-interaction
ENTRYPOINT php ./src/run.php --data=/data