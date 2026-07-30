<?php
/**
 * Plugin Name: Pretendard 폰트 로드
 * Description: 이미지에 포함된 Pretendard Variable(동적 서브셋) CSS 를 프론트와
 *              편집기에 로드한다. 파일 실체는 /opt/fonts/<버전>/ 이며 Apache
 *              Alias 로 <SUBDIR>/assets/fonts 에 노출된다(docker/fonts.conf).
 *
 * 이 파일은 이미지의 /opt/wp-mu 에 있고, mu-plugins 에 얹힌 로더가 require 한다.
 * mu-plugins 디렉터리는 EBS 마운트 안이라 여기로 직접 COPY 할 수 없다.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 공개 URL. 경로에 서체 버전이 들어간다.
 *
 * 응답이 immutable + max-age 1년이므로 같은 경로로 갱신하면 새 파일이 최대
 * 1년간 도달하지 않는다. 버전은 이미지의 디렉터리 이름(/opt/fonts/<버전>/)이
 * 정본이고 여기서 읽어 쓴다 — 양쪽에 적어두면 어긋났을 때 404 가 되고,
 * 화면에는 "폰트가 안 나온다"로만 보여 원인을 찾기 어렵다.
 *
 * 서체를 올릴 때 할 일은 Dockerfile 의 COPY 대상 디렉터리 이름을 바꾸는 것뿐이다.
 */
function typecast_pretendard_url(): ?string {
    static $url = false;

    if ($url !== false) {
        return $url;
    }

    // 버전 디렉터리가 여러 개면 이름 순으로 마지막 것을 쓴다. 정상 상태는 한 개다.
    $found = glob('/opt/fonts/*/pretendard.css');

    if (!$found) {
        // 서체가 없는 이미지에서는 아무것도 큐에 넣지 않는다. 링크만 걸리고
        // 404 가 나는 것보다 낫다.
        return $url = null;
    }

    sort($found, SORT_NATURAL);
    $version = basename(dirname(end($found)));

    // home_url() 은 설치 서브디렉터리를 포함하므로 Alias 경로(/<SUBDIR>/assets/fonts)와
    // 맞는다. SUBDIR 은 빌드마다 다르다(기본 blog, 한국 kr/learn, 영문 learn).
    return $url = home_url("/assets/fonts/{$version}/pretendard.css");
}

function typecast_pretendard_enqueue(): void {
    $url = typecast_pretendard_url();

    if ($url === null) {
        return;
    }

    // ver 를 붙이지 않는다. 버전은 이미 경로에 들어 있다.
    wp_enqueue_style('pretendard', $url, [], null);
}

add_action('wp_enqueue_scripts', 'typecast_pretendard_enqueue', 5);

// Elementor 편집기 안에서도 같은 서체로 보이게 한다.
add_action('elementor/editor/after_enqueue_styles', 'typecast_pretendard_enqueue');
add_action('admin_enqueue_scripts', 'typecast_pretendard_enqueue');
