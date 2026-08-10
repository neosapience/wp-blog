# 아티클 하단 작성자 프로필 박스 (KR)

- 시안: Figma `Marketing-Design` — 데스크톱 [17904:56284](https://www.figma.com/design/5gnSQqwIhC6gVMnltL7mtK/Marketing-Design?node-id=17904-56284), 모바일 [17904:56301](https://www.figma.com/design/5gnSQqwIhC6gVMnltL7mtK/Marketing-Design?node-id=17904-56301)
- 대상: 한국 블로그(https://typecast.ai/kr/learn) 싱글 포스트, 본문과 "관련 아티클" 사이

## 배경

KR 블로그의 실제 구성은 저장소만 봐서는 알 수 없어 라이브 HTML 로 확인했다(2026-08-10):

- 테마 GeneratePress + child, 싱글 포스트는 **Elementor Pro 템플릿**으로 조립되어 있다
- 플러그인: elementor, elementor-pro, luckywp-table-of-contents, post-views-counter, **simple-local-avatars**
- simple-local-avatars 덕에 `get_avatar()` 가 업로드된 프로필 사진을 반환한다(Gravatar 아님)
- Elementor **Author Box 위젯이 이미 템플릿에 있으나** `elementor-hidden-desktop/tablet/mobile` 로 전부 숨겨져 있다
- 필자의 WP "소개(description)" 필드는 비어 있다

즉 시안이 요구하는 데이터 중 새로 만들 것은 **"소속" 하나뿐**이다. 사진·이름·소개문은 이미 있다.

## 어디에 만드는가 — 편집기, 코드 아님

레이아웃과 스타일은 **Elementor 편집기**에 둔다. 코드로 옮기면 문구 하나 바꾸는 데도 개발자와 이미지 빌드·배포가 필요해진다. 이 박스는 마케팅 시안에서 나온, 앞으로도 계속 손볼 블록이라 그 비용이 크다.

앞선 breadcrumb·서체 작업이 코드로 간 것은 편집기로는 아예 불가능했기 때문이지(Yoast 필터, 폰트 파일) 저장소 관행 때문이 아니다. 이 박스는 다르다.

코드로 남기는 것은 편집기로 만들 수 없는 것 하나 — **"소속" 사용자 필드** — 뿐이다.

### Author Box 위젯을 쓰지 않는 이유

Elementor Author Box 위젯은 `이름 → 소개문`을 한 덩어리로 출력한다. 시안은 그 **사이**에 소속이 들어가고, 위젯 내부 순서는 편집기로도 바꿀 수 없다. 그래서 기본 위젯으로 조립한다. 숨겨진 기존 위젯은 **삭제**한다 — 숨은 채 남으면 나중에 어느 쪽이 진짜인지 알 수 없다.

## 구성

```
컨테이너 (배경 #F4F4F4, 라운드 20px)
├─ 이미지 위젯       ← 동적 태그 Author Profile Picture, 원형
└─ 컨테이너 (세로, gap 20px)
   ├─ 컨테이너 (세로, gap 4px)
   │  ├─ 제목 위젯   ← 동적 태그 Author Info → Display Name
   │  └─ 텍스트 위젯 ← 동적 태그 Author Meta → typecast_affiliation
   └─ 텍스트 위젯    ← 동적 태그 Author Info → Bio
```

## 값

| 항목 | 데스크톱 | 태블릿 | 모바일 |
|---|---|---|---|
| 배경 / 라운드 | `#F4F4F4` / 20px | 동일 | 동일 |
| 패딩 | 60px | 40px | 상하 40px, 좌우 24px |
| 배치 | 가로, 세로 가운데 | 가로, 세로 가운데 | 세로, 가운데 정렬 |
| 아바타 | 110px 원형 | 110px 원형 | 60px 원형 |
| 아바타↔텍스트 | 41px | 41px | 10px |
| 이름 | Pretendard SemiBold 20px `#000` | 동일 | Pretendard SemiBold 16px `#000` |
| 소속 | Pretendard SemiBold 14px `#838383` | 동일 | 동일 |
| 코멘트 | Pretendard Regular 20px `#000`, 좌측 정렬 | 동일 | Pretendard Regular 14px `#000`, 가운데 정렬 |
| 이름↔소속 | 4px | 4px | 4px |
| 소속↔코멘트 | 20px | 20px | 20px |

태블릿(768~1024px) 값은 시안에 없다. 데스크톱 배치를 유지하되 패딩만 줄인 것이며, 확인 후 조정할 수 있다.

시안 배경에는 텍스처 이미지가 10% 불투명도로 깔려 있으나 **넣지 않는다**. 화면에서 구분되지 않는데 이미지 요청만 늘어난다.

## 코드 — `docker/mu/author-affiliation.php`

WP `사용자 → 프로필` 화면에 "소속" 입력칸을 추가하고 사용자 메타 `typecast_affiliation` 에 저장한다. Elementor 의 Author Meta 동적 태그가 이 키를 읽는다.

- 출력용 마크업과 CSS 는 두지 않는다. 표시는 전적으로 편집기 몫이다.
- 필드는 KR·영문 이미지 양쪽에 들어간다. 라벨을 `소속 (Affiliation)` 으로 두어 영문 사이트에서도 뜻이 통하게 하고, 별도 분기는 두지 않는다. 조건 분기(로케일·URL 판별)는 어긋났을 때 "필드가 사라졌다"로만 보여 원인을 찾기 어렵고, 쓰이지 않는 입력칸 하나보다 위험하다. 실제 노출 여부는 각 사이트의 Elementor 템플릿이 정한다.
- 저장 시 `current_user_can('edit_user', $user_id)` 로 권한을 확인하고 nonce 를 검증한다.
- 값이 비면 메타를 저장하지 않고 삭제한다 — 빈 문자열이 남으면 편집기에서 빈 줄이 생긴다.

## 실제 적용 결과 (2026-08-10)

Elementor 쪽 설정은 DB 에만 남으므로 여기 적어 둔다. 템플릿은 **Single Post (post ID 6046)** 이고,
박스는 본문 위젯(`e789a45`) 바로 뒤, 오른쪽 컬럼(`1c161a69`)의 6번째 자식이다.

| 요소 ID | 종류 | 클래스 | 동적 태그 |
|---|---|---|---|
| `ta9f101` | 내부 섹션 | `tc-author` | — |
| `ta9f102` | 컬럼 | — | — |
| `ta9f103` | image | `tc-author-avatar` | `author-profile-picture` |
| `ta9f104` | 컬럼 | `tc-author-text` | — |
| `ta9f105` | heading | `tc-author-name` | `author-name` |
| `ta9f106` | heading | `tc-author-org` | `author-meta` (key `typecast_affiliation`) |
| `ta9f107` | text-editor | `tc-author-bio` | `author-info` (key `description`) |

작업하며 알게 된 것들:

- **섹션·컬럼의 CSS 클래스 컨트롤은 `css_classes`, 위젯은 `_css_classes` 다.** 이름이 다르다.
- **편집기 미리보기는 위젯 CSS 를 적용하지 않는다.** 기존의 정상 위젯 `tc-meta-label` 도 설정은
  12px/`#6B6B6B` 인데 편집기에서는 17px/`#222` 로 계산된다. 타이포그래피는 편집기에서 검증할 수
  없고 프론트엔드에서 봐야 한다.
- **값이 빈 동적 위젯은 렌더되지 않는다.** 소속·소개문이 비어도 빈 줄이나 여백이 남지 않는다.
- **아바타 원본은 96px 로 고정된다.** `author-profile-picture` 태그가 이미지 크기 설정을 무시한다
  (thumbnail·medium·large·full 모두 96×96 반환). 시안은 110px 이라 15% 확대된다. 사진이라 크게
  띄지는 않으나 고해상도 화면에서는 다소 부드럽다.

### CloudFront 캐시 — 편집 후 반드시 확인할 것

Elementor 는 저장 시 `wp-content/uploads/elementor/css/post-<id>.css` 를 다시 만들고 HTML 의
`?ver=` 를 올린다. 그런데 이 경로는 **CloudFront 가 쿼리스트링을 캐시 키에서 무시**하도록 되어
있어, `?ver=` 가 바뀌어도 옛 파일이 그대로 내려온다. 서로 다른 쿼리(`?cb=`·`?x=`·`?zz=`)로 요청해도
같은 캐시 객체가 나오는 것으로 확인했다(`age` 가 이어서 증가).

증상은 "마크업은 있는데 스타일만 없다"로 나타난다. 템플릿을 고친 뒤에는 무효화가 필요하다:

```
aws cloudfront create-invalidation --distribution-id <ID> \
  --paths "/kr/learn/wp-content/uploads/elementor/css/*"
```

## 콘텐츠 준비 (작업 후 필요)

박스는 데이터를 그릴 뿐이다. 아래가 비어 있으면 그 줄이 통째로 사라진다.

- 각 필자 프로필의 **"소개"** — 현재 전원 비어 있음
- 각 필자 프로필의 **"소속"** — 이번에 추가되는 칸. mu-plugin 이 배포되어야 필드가 생긴다

## 확인 방법

1. 프로필 화면에 "소속" 칸이 보이고, 입력·저장·재로드 후 값이 유지된다
2. 다른 사용자를 편집할 권한이 없는 계정은 남의 소속을 바꿀 수 없다
3. 아티클 하단 박스가 시안과 일치한다 — 데스크톱·태블릿·모바일 각각
4. 소개나 소속이 빈 필자의 글에서 레이아웃이 깨지지 않는다
5. 작업 전 Elementor 템플릿을 내보내 백업해 둔다

## 범위 밖

- 영문 블로그(/learn) 노출 — 코드는 공유되나 템플릿 작업은 하지 않는다
- 상단 메타 영역(작성자·게시일·최종 업데이트)과 "관련 아티클" 섹션
- 소개·소속 내용 작성
