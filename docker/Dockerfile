FROM wordpress:7.0.2-php8.5-apache
ARG SUBDIR=blog

# handoff A2: hide the PHP version from response headers (expose_php=Off).
COPY docker/security.ini /usr/local/etc/php/conf.d/security.ini

# handoff A1/A2: security response headers, server fingerprint removal and
# xmlrpc blocking. security.conf needs mod_headers. mod_rewrite is what
# WordPress's own .htaccess uses for pretty permalinks — the base image already
# enables both, so this is a guard against a base image that stops doing so
# rather than something we turn on ourselves.
RUN a2enmod headers rewrite
COPY docker/security.conf  /etc/apache2/conf-enabled/security.conf
COPY docker/wordpress.conf /etc/apache2/conf-enabled/wordpress.conf

# REST API 응답을 gzip 으로 내보낸다. 이유는 docker/compression.conf 참조.
COPY docker/compression.conf /etc/apache2/conf-enabled/compression.conf

# 업로드 이미지에 브라우저 캐시를 건다. 이유는 docker/caching.conf 참조.
# mod_expires 는 베이스 이미지가 이미 켜 두지만, 위 headers/rewrite 와 같은
# 이유로 명시해 둔다.
RUN a2enmod expires
COPY docker/caching.conf /etc/apache2/conf-enabled/caching.conf

# Pretendard Variable (동적 서브셋). 근거와 갱신 방법은 docker/fonts/README.md 참조.
#
# /opt/fonts 에 두는 이유: wp-content 아래로 COPY 하면 프로덕션의 EBS 마운트
# (/var/www/html/${SUBDIR})에 가려져 런타임에 사라진다. 마운트 바깥에 두고
# Apache Alias 로 노출한다.
# 경로에 버전을 넣는다. 응답이 immutable + max-age 1년이라, 같은 경로로
# 갱신하면 새 파일이 최대 1년간 사용자에게 도달하지 않는다. 서체를 올릴
# 때는 이 디렉터리 이름만 바꾸면 되고, 공개 URL 은 mu-plugin 이 여기서
# 읽어 만들기 때문에 따로 맞출 곳이 없다.
COPY docker/fonts/ /opt/fonts/1.3.9/
COPY docker/fonts.conf /etc/apache2/conf-enabled/fonts.conf
RUN sed -i "s|__SUBDIR__|${SUBDIR}|g" /etc/apache2/conf-enabled/fonts.conf

# must-use 플러그인 코드. 실행은 eks-recipe 가 ConfigMap 으로 얹는 로더
# (mu-plugins/000-typecast-loader.php)가 /opt/wp-mu/*.php 를 require 하는 방식이다.
#
# mu-plugins 디렉터리 자체는 EBS 마운트(/var/www/html/${SUBDIR}) 안이라 여기로
# COPY 해도 런타임에 가려진다. 그래서 코드는 마운트 바깥에 두고, 마운트 안에는
# 로더 파일 하나만 올린다. 새 플러그인을 추가할 때 이 디렉터리에 파일만 넣으면
# 되고 배포 YAML 은 건드리지 않는다.
COPY docker/mu/ /opt/wp-mu/

RUN echo 'ok' >> index.html
WORKDIR /var/www/html/${SUBDIR}
