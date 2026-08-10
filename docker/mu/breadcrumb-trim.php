<?php
/**
 * Plugin Name: breadcrumb 을 시안 형태로 정리
 * Description: 시안(Figma 17917:61063)의 아티클 breadcrumb 은 `블로그 > 카테고리`
 *              두 단계이고 카테고리는 클릭 가능해야 한다(핸드오프 §3 P0).
 *
 * Yoast 는 (1) 마지막에 현재 글 제목을 붙이고 (2) 마지막 항목을 링크로 만들지
 * 않는다. 설정에는 두 동작을 끄는 옵션이 없어 필터로 처리한다.
 *
 * 이 필터는 BreadcrumbList 스키마에도 함께 적용된다. 현재 페이지를 트레일에서
 * 빼는 것은 구글이 허용하는 형태다.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 현재 글 항목부터 뒤를 전부 잘라낸다.
 *
 * 마지막 항목만 보면 안 된다: 분할 글(<!--nextpage-->)의 2페이지부터는 Yoast 가
 * 글 제목 뒤에 페이지 크럼을 붙여서, 현재 글이 마지막 항목이 아니다. 글 항목의
 * 위치를 찾아 그 지점에서 자르면 페이지 크럼도 함께 떨어져 나간다.
 */
add_filter('wpseo_breadcrumb_links', function ($links) {
    if (!is_singular('post') || !is_array($links) || count($links) < 2) {
        return $links;
    }

    $current = get_queried_object_id();

    foreach ($links as $index => $link) {
        if (isset($link['id']) && (int) $link['id'] === $current) {
            // 최소한 항목 하나는 남긴다 — 글 항목이 어떤 이유로 맨 앞이라면
            // 자르지 않고 그대로 두는 편이 안전하다.
            if ($index === 0) {
                return $links;
            }

            $kept = array_slice($links, 0, $index);

            // 시안의 트레일은 `블로그 > 카테고리` 두 단계다. 카테고리 크럼이 없는
            // 사이트에서는 자를 것이 없다 — 영문 블로그(/learn)가 그렇다. Yoast 의
            // Posts breadcrumb taxonomy 가 KR 은 Category, 영문은 None 이라
            // 트레일이 `Home > 글 제목` 이고, 여기서 글 항목을 자르면 홈 하나만
            // 남는다. 게다가 남은 홈이 마지막 항목이 되면서 Yoast 가 앵커를 빼
            // 클릭조차 안 된다(로컬에서 재현: 카테고리 있으면 `Home > Business`,
            // 없으면 링크 없는 `Home`).
            foreach ($kept as $item) {
                if (!empty($item['term_id'])) {
                    return $kept;
                }
            }

            return $links;
        }
    }

    return $links;
});

/**
 * 링크 없이 출력된 마지막 항목에 앵커를 씌운다.
 *
 * Yoast 는 마지막 항목을 wrapper 요소로만 감싼다. 그 항목이 카테고리라면 URL 을
 * 알 수 있으므로 클릭 가능하게 만든다. wrapper 태그는 wpseo_breadcrumb_single_link_wrapper
 * 필터로 바뀔 수 있으므로 고정하지 않고, 바깥 태그를 보존한 채 안쪽 텍스트만
 * 앵커로 교체한다.
 */
add_filter('wpseo_breadcrumb_single_link', function ($output, $link) {
    if (!is_singular('post')) {
        return $output;
    }

    // 이미 앵커가 있으면 손대지 않는다.
    if (strpos($output, '<a ') !== false) {
        return $output;
    }

    if (empty($link['term_id'])) {
        return $output;
    }

    $url = get_term_link((int) $link['term_id'], 'category');

    if (is_wp_error($url)) {
        return $output;
    }

    $anchor = '<a href="' . esc_url($url) . '">'
        . esc_html(isset($link['text']) ? $link['text'] : '') . '</a>';

    // <tag ...>내용</tag> 형태면 내용만 앵커로 바꾼다. 그 외 형태는 그대로 둔다.
    $replaced = preg_replace(
        '/^(<([a-z][a-z0-9]*)\b[^>]*>).*(<\/\2>)$/is',
        '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $anchor) . '$3',
        $output,
        1,
        $count
    );

    return ($count === 1 && $replaced !== null) ? $replaced : $output;
}, 10, 2);
