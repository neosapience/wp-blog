#!/usr/bin/env bash
# REST Basic 인증 헤더가 PHP 까지 도달하는지 진단한다.
#
# 배경: 블로그 도구가 신원 확인을 POST + "X-HTTP-Method-Override: GET" 으로
# 보내는데(쓰기 방지 목적) 401 rest_not_logged_in 이 계속 온다. 401 만으로는
# "헤더가 중간(CloudFront/Apache)에서 떨어졌다"와 "자격증명이 틀렸다"를 구분할
# 수 없지만, WordPress 는 두 경우에 서로 다른 에러 코드를 돌려준다:
#
#   rest_not_logged_in   Basic 자격증명이 PHP 에 아예 도달하지 않았다
#                        (헤더가 벗겨졌거나, 애플리케이션 패스워드 기능 자체가
#                        꺼져 있어 WP 가 헤더를 읽지 않은 경우)
#   invalid_email        헤더는 도달했고, WP 가 자격증명을 검사까지 했다
#   invalid_username     (모르는 계정 / 틀린 패스워드라는 뜻이므로, 일부러
#   incorrect_password   가짜 자격증명을 보내면 이 코드가 나오는 것 자체가
#                        "헤더 전달 OK"의 증거가 된다)
#
# 그래서 이 스크립트는 실제 비밀 없이도 (가짜 자격증명으로) 메서드별 헤더
# 전달 여부를 판정할 수 있다. 실제 계정으로 최종 확인하려면 WP_USER /
# WP_APP_PASSWORD 환경변수를 주면 된다.
#
# 참고 — CloudFront 의 문서화된 기본 동작은 이 진단의 배경 지식이다:
#   * GET/HEAD: Authorization 헤더는 cache policy 의 캐시 키에 포함시키지
#     않는 한 오리진으로 전달되지 않는다 (origin request policy 로는 불가).
#   * DELETE/PATCH/POST/PUT: Authorization 을 항상 오리진으로 전달한다.
# 즉 기본 설정이라면 POST 가 오히려 통과하는 메서드다. POST 까지
# rest_not_logged_in 이면 헤더 문제가 아니라 오리진 쪽에서 애플리케이션
# 패스워드가 비활성인 경우(대표적으로 is_ssl() == false)를 의심해야 하고,
# 1번 프로브가 그 경우를 자격증명 없이 잡아낸다.
#
# 사용:
#   ./tools/check-auth-header.sh                          # 가짜 자격증명으로 전달 여부만 판정
#   WP_USER='you@example.com' WP_APP_PASSWORD='xxxx ...' ./tools/check-auth-header.sh
#   BASE='https://typecast.ai/kr/learn' ./tools/check-auth-header.sh   # KR 블로그 대상

set -euo pipefail

BASE="${BASE:-https://typecast.ai/learn}"
WP_USER="${WP_USER:-probe@example.com}"
WP_APP_PASSWORD="${WP_APP_PASSWORD:-aaaa bbbb cccc dddd eeee ffff}"
ROUTE="$BASE/wp-json/wp/v2/users/me?context=edit"

# 응답에서 WP 에러 코드(없으면 HTTP 상태)를 뽑는다.
extract_code() {
    grep -o '"code":"[^"]*"' <<<"$1" | head -1 | cut -d'"' -f4
}

run() { # run <label> <varname> [curl args...]
    local label="$1" var="$2"; shift 2
    local out http body code
    out=$(curl -sS -w $'\n%{http_code}' "$@" "$ROUTE") || { echo "  $label: curl 실패"; printf -v "$var" 'ERROR'; return; }
    http=${out##*$'\n'}
    body=${out%$'\n'*}
    code=$(extract_code "$body")
    printf '  %-28s HTTP %s  code=%s\n' "$label" "$http" "${code:-<none>}"
    printf -v "$var" '%s' "${code:-http_$http}"
}

echo "대상: $ROUTE"
echo

echo "1) 애플리케이션 패스워드 기능이 켜져 있는가 (익명, 자격증명 불필요)"
index=$(curl -sS "$BASE/wp-json/")
if grep -q 'application-passwords' <<<"$index"; then
    echo "  OK — wp-json 인덱스의 authentication 에 application-passwords 가 있다."
    app_pw_ok=1
else
    echo "  없음 — 오리진에서 wp_is_application_passwords_available() 이 false 다."
    echo "  이 경우 WP 는 어떤 메서드든 Basic 헤더를 읽지도 않으므로 모든 요청이"
    echo "  rest_not_logged_in 이 된다. 대표 원인: 오리진 입장에서 is_ssl() 이"
    echo "  false (TLS 가 CloudFront/ALB 에서 끝나고 X-Forwarded-Proto 가 pod 까지"
    echo "  전달·반영되지 않음). eks-recipe 의 인그레스/서비스 설정을 볼 것."
    app_pw_ok=0
fi
echo

echo "2) 메서드별 Authorization 전달 (자격증명: $WP_USER)"
run "GET"                        get_code    -u "$WP_USER:$WP_APP_PASSWORD"
run "POST + Method-Override:GET" post_code   -u "$WP_USER:$WP_APP_PASSWORD" -X POST -H 'X-HTTP-Method-Override: GET'
echo

echo "판정:"
verdict() { # verdict <label> <code>
    case "$2" in
        invalid_email|invalid_username|incorrect_password)
            echo "  $1: Authorization 이 PHP 까지 도달했다 (WP 가 자격증명을 검사함)." ;;
        rest_not_logged_in)
            if [ "$app_pw_ok" = 1 ]; then
                echo "  $1: 기능은 켜져 있는데 자격증명이 무시됐다 — 이 메서드에서 헤더가 중간에 떨어진다."
            else
                echo "  $1: 애플리케이션 패스워드 비활성이라 헤더 전달 여부와 무관하게 익명 처리됐다 (1번 참조)."
            fi ;;
        application_passwords_disabled|application_passwords_disabled_for_user)
            echo "  $1: 헤더는 도달했으나 이 사이트/계정에서 애플리케이션 패스워드가 막혀 있다." ;;
        http_200|"")
            echo "  $1: 200 — 인증 성공 (실제 자격증명을 준 경우)." ;;
        *)
            echo "  $1: 예상 밖의 코드 '$2' — 응답 본문을 직접 볼 것." ;;
    esac
}
verdict "GET " "$get_code"
verdict "POST" "$post_code"
