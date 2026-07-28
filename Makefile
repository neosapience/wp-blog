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

# 세 태그를 한꺼번에 민다. build/build-global/build-korea 를 모두 돌린 뒤에만
# 성공하므로, 한쪽 리전만 올릴 때는 push-korea / push-global 을 쓴다.
push:
	@docker push ${name}:${tag}
	@docker push ${name}:${tag}-global
	@docker push ${name}:${tag}-korea

push-korea:
	@docker push ${name}:${tag}-korea

push-global:
	@docker push ${name}:${tag}-global

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