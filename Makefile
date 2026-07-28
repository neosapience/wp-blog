name := neosapience/wordpress
tag := 7.0.2-php8.5-apache

build:
	@docker build . \
		-f ./docker/Dockerfile \
		-t ${name}:${tag} \
		--platform linux/amd64

build-korea:
	@docker build . \
		-f ./docker/Dockerfile \
		-t ${name}:${tag}-korea \
		--build-arg SUBDIR=kr/learn \
		--platform linux/amd64

build-global:
	@docker build . \
		-f ./docker/Dockerfile \
		-t ${name}:${tag}-global \
		--build-arg SUBDIR=learn \
		--platform linux/amd64

push:
	@docker push ${name}:${tag}
	@docker push ${name}:${tag}-global
	@docker push ${name}:${tag}-korea

up:
	@docker-compose up -d

up-korea:
	@docker-compose -f docker-compose.yaml -f docker-compose.korea.yaml up -d

down:
	@docker-compose down

ps:
	@docker-compose ps

ls:
	@docker images ${name}