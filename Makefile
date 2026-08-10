name := neosapience/wordpress
tag := 7.0.2-php8.5-apache

# 배포되는 태그에는 커밋 SHA 를 붙인다. 프로덕션(EKS)은 eks-recipe 의
# k8s/apps/wp-blog/overlays/korea/kustomization.yaml 의 images.newTag 로 이미지를
# 고르는데, ${tag}-korea 처럼 고정된 태그를 덮어쓰기만 하면 newTag 가 그대로라
# 배포가 조용히 나가지 않는다. 태그를 바꿔야 배포가 움직인다.
#
# 지금까지 이 단계를 손으로 했다. 빠뜨리기 쉽고, 빠뜨리면 "푸시했는데 왜 그대로지"가
# 된다. 그래서 push 타겟이 알아서 붙인다.
sha := $(shell git rev-parse --short HEAD)

# 커밋되지 않은 변경이 있으면 SHA 가 실제로 빌드된 내용과 다르다. 그런 이미지가
# 배포되면 저장소를 봐도 무엇이 돌고 있는지 알 수 없다. 푸시 전에 막는다.
guard-clean:
	@test -z "$$(git status --porcelain)" || { \
		echo "커밋되지 않은 변경이 있다. SHA($(sha))가 이미지 내용과 어긋난다."; \
		git status --short; \
		exit 1; \
	}

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
#
# 리전별 이미지는 SHA 태그와 고정 태그를 함께 올린다. 배포가 가리키는 것은 SHA
# 태그이고, 고정 태그는 "가장 최근 것"을 가리키는 편의용이다.
# 기본 이미지(${tag})는 배포에 쓰이지 않아 SHA 태그를 붙이지 않는다.
push: guard-clean build build-global build-korea
	@docker push ${name}:${tag}
	@$(MAKE) --no-print-directory push-tags-global
	@$(MAKE) --no-print-directory push-tags-korea

push-korea: guard-clean build-korea
	@$(MAKE) --no-print-directory push-tags-korea

push-global: guard-clean build-global
	@$(MAKE) --no-print-directory push-tags-global

# 태그를 붙여 올리고, eks-recipe 에 적을 값을 마지막에 찍어 준다.
# 그 값을 옮겨 적는 것이 배포의 마지막 한 걸음이다.
push-tags-korea:
	@docker tag ${name}:${tag}-korea ${name}:${tag}-korea-${sha}
	@docker push ${name}:${tag}-korea-${sha}
	@docker push ${name}:${tag}-korea
	@echo "korea  newTag: ${tag}-korea-${sha}"

push-tags-global:
	@docker tag ${name}:${tag}-global ${name}:${tag}-global-${sha}
	@docker push ${name}:${tag}-global-${sha}
	@docker push ${name}:${tag}-global
	@echo "global newTag: ${tag}-global-${sha}"

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