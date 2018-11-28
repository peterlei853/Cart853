FROM thiagobarradas/lamp:php-7.2
MAINTAINER Thiago Barradas <th.barradas@gmail.com>

# VARIABLES
ENV OPENCART_VERSION=3.0.2.0
ENV ADMIN_USERNAME="admin" 
ENV ADMIN_EMAIL="admin-opencart@mailinator.com" 
ENV ADMIN_PASSWORD="Mudar123"

# GET OPENCART FILES
WORKDIR /var/www/html
RUN rm -f * && mkdir tmp
WORKDIR /var/www/html/tmp
RUN ls -l
RUN wget -O opencart-$OPENCART_VERSION.zip https://codeload.github.com/opencart/opencart/zip/$OPENCART_VERSION
RUN chmod -R 755 /app

# UNZIP OPENCART
RUN unzip opencart-$OPENCART_VERSION.zip
WORKDIR /var/www/html/tmp/opencart-$OPENCART_VERSION
RUN composer install
RUN cp -a upload/. /var/www/html
COPY config/config.php /var/www/html/_config.php
COPY config/admin-config.php /var/www/html/admin/_config.php

#install tools for dev
RUN apt-get update
RUN apt-get install -y openssh-server sudo bash bash-doc bash-completion \
                       util-linux pciutils usbutils coreutils binutils findutils grep \
                       build-essential libssl-dev
RUN apt-get install -y vim curl git

# Install language pack
RUN apt-get install -y locales
RUN locale-gen zh_TW zh_TW.UTF-8 zh_CN.UTF-8 en_US en_US.UTF-8 C.UTF-8
RUN dpkg-reconfigure locales
ENV LC_ALL=C.UTF-8 \
    LANGUAGE=en_US.UTF-8 \
    LANG=C.UTF-8

#login user
RUN mkdir /var/run/sshd
RUN echo 'root:root' |chpasswd
RUN sed -ri 's/^PermitRootLogin\s+.*/PermitRootLogin yes/' /etc/ssh/sshd_config
RUN useradd admin -s /bin/bash
RUN cp -r /etc/skel/. /home/admin
RUN mkdir -p /home/admin/.ssh
COPY ssh/* /home/admin/.ssh/
RUN cat /home/admin/.ssh/*.pub >> /home/admin/.ssh/authorized_keys
RUN echo 'admin:admin' |chpasswd
RUN echo "admin ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
RUN chown admin:admin -R /home/admin
RUN chmod 700 /home/admin/.ssh && chmod 600 /home/admin/.ssh/*

# CLEAN TMP FILES
RUN rm -rf tmp
RUN apt purge && apt-get autoremove -y && apt-get clean


# COPY SCRIPT TO SETUP OPENCART
COPY scripts/supervisord-zopencart.conf /etc/supervisor/conf.d/supervisord-zopencart.conf
COPY scripts/supervisord-sshd.conf /etc/supervisor/conf.d/supervisord-sshd.conf
COPY scripts/setup-opencart.sh /setup-opencart.sh

# EXPOSE AND RUN
RUN chmod -R 777 /var/www/html
WORKDIR /var/www/html
EXPOSE 80 8000
EXPOSE 22 8022
#CMD    ["/usr/sbin/sshd", "-D"]
CMD ["/run.sh"]
#CMD ["/usr/bin/supervisord"]