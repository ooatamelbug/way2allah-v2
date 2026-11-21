# Way2Allah local production-like validation image (disposable). Apache prefork + PHP 8.3 as php-cgi via
# mod_fcgid (FastCGI, port 8081) and via mod_cgid + Action (plain CGI via mod_cgi, port 8082). No application code is baked in.
FROM ubuntu:24.04
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
      apache2 libapache2-mod-fcgid php8.3-cgi php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
      php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-sqlite3 procps curl ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && a2dismod -f mpm_event && a2enmod mpm_prefork fcgid cgi actions rewrite headers \
    && a2dissite 000-default
COPY conf/php-lab.ini /etc/php/8.3/cgi/conf.d/99-lab.ini
COPY conf/php-lab.ini /etc/php/8.3/cli/conf.d/99-lab.ini
COPY conf/lab.conf /etc/apache2/sites-available/lab.conf
COPY conf/php-fcgi-wrapper /usr/local/bin/php-fcgi-wrapper
# PHP runs as the account user (as cPanel suEXEC does): uid 1000 'w2a'; the monitor/"cron" runs as the same user.
RUN (userdel -r ubuntu 2>/dev/null || true) && useradd -u 1000 -m -s /bin/sh w2a && sed -i 's/=www-data/=w2a/' /etc/apache2/envvars && chown -R w2a:w2a /var/lib/apache2/fcgid
RUN chmod 755 /usr/local/bin/php-fcgi-wrapper && a2ensite lab && sed -i 's/^Listen 80$/# Listen 80/' /etc/apache2/ports.conf
CMD ["apache2ctl", "-D", "FOREGROUND"]
