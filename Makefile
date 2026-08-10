name := neosapience/wordpress
tag := 7.0.2-php8.5-apache

# 배포되는 태그에는 커밋 SHA 를 붙인다. 프로덕션(EKS)은 eks-recipe 의
# k8s/apps/wp-blog/overlays/korea/kustomization.yaml 의 images.newTag 로 이미지를
# 고르는데, ${tag}-korea 처럼 고정된 태그를 덮어쓰기만 하면 newTag 가 그대로라
# 배포가 조용히 나가지 않는다. 태그를 바꿔야 배포가 움직인다.
#
# 지금까지 이 단계를 손으로 했다. 빠뜨리기 쉽고, 빠뜨리면 "푸시했는데 왜 그대로지"가
# 된다. 그래서 push 타겟이 알아서 붙인다.
sha := $(shell git rev-parse --short HEAD 2>/dev/null)

# 이 Makefile 은 병렬로 돌 일이 없다. 그리고 -j 로 돌리면 guard-clean 과 build 가
# 동시에 시작해서, 검사가 끝나기 전에 빌드가 나갈 수 있다. 순서를 지키는 편이 낫다.
.NOTPARALLEL:

.PHONY: guard-clean build build-korea build-global push push-korea push-global \
        up up-korea down ps ls

# 푸시 전 검사. 두 가지를 본다.
#
# 하나, SHA 를 읽었는가. git 이 실패하면 $(shell) 은 빈 문자열을 돌려주고, 그대로 두면
# "${tag}-korea-" 처럼 꼬리가 빈 태그가 만들어져 올라간다.
#
# 둘, 작업 트리가 깨끗한가. 커밋되지 않은 변경이 있으면 SHA 가 실제로 빌드된 내용과
# 다르고, 그런 이미지가 배포되면 저장소를 봐도 무엇이 돌고 있는지 알 수 없다.
# git status 자체가 실패한 경우도 통과시키면 안 된다 — 출력이 비었다는 것과 명령이
# 실패했다는 것은 다르다.
guard-clean:
	@test -n "$(sha)" || { \
		echo "커밋 SHA 를 읽지 못했다. git 저장소 안에서 실행해야 한다."; \
		exit 1; \
	}
	@status="$$(git status --porcelain)" || { \
		echo "git status 가 실패했다. 트리 상태를 확인할 수 없다."; \
		exit 1; \
	}; \
	test -z "$$status" || { \
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
# 태그를 붙여 올리는 일은 별도 타겟으로 두지 않는다. 타겟이면 `make push-tags-korea`
# 로 직접 부를 수 있고, 그러면 guard-clean 과 build 를 건너뛰어 오래된 로컬 이미지에
# 현재 SHA 를 붙여 올리게 된다. 이 Makefile 이 막으려는 바로 그 상황이다.
# 리전이 둘뿐이라 네 줄을 두 번 적는 편이 안전하다.
#
# 마지막 줄에 eks-recipe 에 적을 값을 찍는다. 그 값을 옮겨 적는 것이 배포의 마지막 한 걸음이다.
push-korea: guard-clean build-korea
	@docker tag ${name}:${tag}-korea ${name}:${tag}-korea-${sha}
	@docker push ${name}:${tag}-korea-${sha}
	@docker push ${name}:${tag}-korea
	@echo "korea  newTag: ${tag}-korea-${sha}"

push-global: guard-clean build-global
	@docker tag ${name}:${tag}-global ${name}:${tag}-global-${sha}
	@docker push ${name}:${tag}-global-${sha}
	@docker push ${name}:${tag}-global
	@echo "global newTag: ${tag}-global-${sha}"

push: guard-clean build push-global push-korea
	@docker push ${name}:${tag}

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