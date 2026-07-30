<?php
/**
 * Plugin Name: breadcrumb 을 시안 형태로 정리
 * Description: 시안(Figma 17917:61063)의 breadcrumb 은 `블로그 > 카테고리` 두 단계이고
 *              카테고리는 클릭 가능해야 한다(핸드오프 §3 P0).
 *
 * Yoast 는 (1) 마지막에 현재 글 제목을 붙이고 (2) 마지막 항목을 링크로 만들지
 * 않는다. 설정에는 두 동작을 끄는 옵션이 없어 필터로 처리한다.
 *
 * 1) 글 제목 항목을 제거한다.
 * 2) 그러면 카테고리가 마지막이 되어 링크를 잃으므로, 링크가 남도록 항목을
 *    다시 구성한다. Yoast 는 마지막 원소만 링크 없이 출력하므로, 카테고리
 *    뒤에 화면에 그려지지 않는 빈 항목을 두는 방식은 쓰지 않았다 — 대신
 *    wpseo_breadcrumb_single_link 로 마지막 항목에도 앵커를 씌운다.
 *
 * 이 필터는 BreadcrumbList 스키마에도 함께 적용된다. 현재 페이지를 트레일에서
 * 빼는 것은 구글이 허용하는 형태다.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 마지막 항목(현재 글 제목)을 떼어낸다.
 */
add_filter('wpseo_breadcrumb_links', function ($links) {
    if (!is_singular('post') || !is_array($links) || count($links) < 2) {
        return $links;
    }

    $last = end($links);
    reset($links);

    if (isset($last['id']) && (int) $last['id'] === get_queried_object_id()) {
        array_pop($links);
    }

    return $links;
});

/**
 * 링크 없이 출력된 마지막 항목에 앵커를 씌운다.
 *
 * Yoast 는 마지막 항목을 <span class="breadcrumb_last"> 로만 감싼다. 그 항목이
 * 카테고리라면 URL 을 알 수 있으므로 클릭 가능하게 만든다.
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

    $text = isset($link['text']) ? $link['text'] : '';

    return '<span class="breadcrumb_last"><a href="' . esc_url($url) . '">'
        . esc_html($text) . '</a></span>';
}, 10, 2);
