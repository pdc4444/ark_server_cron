FROM ubuntu:bionic


ENV TZ=US
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN apt-get update && apt-get install -y software-properties-common && add-apt-repository ppa:ondrej/php
RUN dpkg --add-architecture i386
RUN add-apt-repository multiverse
RUN apt-get update
RUN echo steam steam/license note '' | debconf-set-selections
RUN echo steam steam/question select "I AGREE" | debconf-set-selections
RUN apt-get install lib32gcc1 steamcmd curl -y
RUN apt-get install php7.3 -y
RUN apt-get install php7.3-xml -y
RUN apt-get install php7.3-readline -y
RUN apt install php7.3-dev -y
RUN apt-get install libzip-dev -y
RUN apt-get install git -y
RUN apt-get install unzip -y
RUN apt install php-pear -y
RUN pecl channel-update pecl.php.net
RUN pecl install zip
RUN echo 'extension=zip.so' >> /etc/php/7.3/cli/php.ini
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN git clone https://github.com/pdc4444/ark_server_cron.git
COPY ./ /ark_server_cron/
# RUN cd /ark_server_cron && composer install
# ENTRYPOINT [ "/ark_server_cron/bin/console"]
# CMD ["shell"]
CMD ["bash"]