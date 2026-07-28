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

# 각 push 는 대응하는 build 에 의존한다. 의존이 없으면 오래된 로컬 이미지를
# 그대로 게시할 수 있고, 태그만 보고는 최신 여부를 알 수 없다.
# 한쪽 리전만 올릴 때는 push-korea / push-global 을 쓴다.
push: build build-global build-korea
	@docker push ${name}:${tag}
	@docker push ${name}:${tag}-global
	@docker push ${name}:${tag}-korea

push-korea: build-korea
	@docker push ${name}:${tag}-korea

push-global: build-global
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