name := neosapience/wordpress
tag := 5.9.3-php8.1-apache

build:
	@docker build . -f ./docker/Dockerfile -t ${name}:${tag} --platform linux/amd64

push:
	@docker push ${name}:${tag}