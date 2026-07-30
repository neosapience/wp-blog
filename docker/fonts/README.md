# Pretendard Variable (동적 서브셋)

블로그 본문과 UI 에 쓰는 서체다.

## 왜 이 방식인가

- **콘텐츠에 맞춘 고정 서브셋을 쓸 수 없다.** 블로그는 글이 386개이고 계속
  늘어나므로, 지금 글자만 담은 서브셋을 만들어 두면 새 글의 글자가 폴백 폰트로
  떨어져 섞여 보인다.
- **unicode-range 동적 서브셋**은 브라우저가 그 페이지에 실제로 나온 글자의
  조각만 받는다. 전체 3MB 중 한 페이지당 수백 KB 수준.
- **CDN 대신 셀프 호스팅**: 같은 오리진이라 연결이 추가로 안 생기고, 외부
  장애에 영향받지 않는다. CDN 은 서브셋 없는 2MB 원본을 준다.
- **wp-content 밖**이라 EBS 마운트에 가려지지 않는다(mu-plugin 과 같은 함정).

## 출처

orioncactus/pretendard v1.3.9
`dist/web/variable/pretendardvariable-dynamic-subset.css` + woff2 조각 92개.
CSS 의 폰트 경로만 `./woff2/` 로 바꿨고 내용은 원본 그대로다.

## 공개 경로에 버전이 들어간다

```text
이미지    /opt/fonts/1.3.9/pretendard.css
공개 URL  <SUBDIR>/assets/fonts/1.3.9/pretendard.css
```

응답이 `Cache-Control: immutable, max-age=31536000` 이라 같은 경로로 갱신하면
새 파일이 **최대 1년간 사용자에게 도달하지 않는다.** 그래서 경로에 버전을 넣는다.

버전의 정본은 **이미지의 디렉터리 이름 하나**다. `mu/pretendard-font.php` 가
`/opt/fonts/*/pretendard.css` 를 찾아 그 디렉터리 이름으로 URL 을 만든다.
Apache `Alias` 도 버전을 모른다. 양쪽에 적어두면 어긋났을 때 404 가 나고,
화면에는 "폰트가 안 나온다"로만 보여 원인을 찾기 어렵다.

## 갱신

1. `docker/fonts/` 의 CSS 와 woff2 조각을 **함께** 새 버전으로 교체한다.
   `unicode-range` 구성이 버전마다 달라질 수 있어 CSS 만 바꾸면 깨진다.
2. `docker/Dockerfile` 의 `COPY docker/fonts/ /opt/fonts/<버전>/` 에서 디렉터리
   이름을 새 버전으로 바꾼다.

그 외에 맞출 곳은 없다. 공개 URL 은 2번에서 자동으로 따라온다.
