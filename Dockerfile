FROM keboola/base-php56

MAINTAINER Vojtech Kurka <vokurka@keboola.com>

ENV APP_VERSION 1.2.1

RUN yum -y --enablerepo=epel,remi,remi-php56 upgrade
RUN yum -y --enablerepo=epel,remi,remi-php56 install \
	php-devel \
	php-pear

RUN yum update
RUN yum -y install make gcc libssh2 libssh2-devel
RUN printf "\n" | pecl install -f ssh2
RUN echo "extension=ssh2.so"  >> /etc/php.ini

WORKDIR /home

RUN git clone https://github.com/vokurka/keboola-silverpop-ex ./
RUN composer install --no-interaction
ENTRYPOINT php ./src/run.php --data=/data