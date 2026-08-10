<?php
/**
 * Plugin Name: 작성자 소속 필드
 * Description: 사용자 프로필에 "소속" 입력칸을 추가한다. 아티클 하단 작성자 박스
 *              (시안 17904:56284 / 모바일 17904:56301)의 이름 아래 회색 줄에 쓰인다.
 *
 * 이 파일에 마크업도 CSS 도 없는 것은 의도적이다. 박스의 배치와 스타일은 Elementor
 * 싱글 포스트 템플릿에 있다. 그래야 문구나 간격을 고칠 때 배포 없이 편집기에서
 * 처리할 수 있다. 코드로 가져온 것은 편집기로 만들 수 없는 것 하나 — 저장할 필드가
 * 워드프레스에 없다는 것 — 뿐이다.
 *
 * 사진·이름·소개문은 이미 있다. 사진은 simple-local-avatars 가 get_avatar() 로
 * 돌려주고, 이름은 display_name, 소개문은 기본 description 필드다. 소속만 없다.
 *
 * 편집기 쪽에서는 Elementor 의 Author Meta 동적 태그에 아래 메타 키를 넣어 읽는다.
 *
 * 이 파일은 이미지의 /opt/wp-mu 에 있고, mu-plugins 에 얹힌 로더가 require 한다.
 * mu-plugins 디렉터리는 EBS 마운트 안이라 여기로 직접 COPY 할 수 없다.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 사용자 메타 키. Elementor 동적 태그에 손으로 입력하는 값이라 바꾸면 편집기 설정도
 * 함께 고쳐야 한다 — 한쪽만 바꾸면 화면에서는 "소속이 안 나온다"로만 보인다.
 */
const TYPECAST_AFFILIATION_META_KEY = 'typecast_affiliation';

/**
 * 소속 최대 길이(자).
 *
 * 시안에서 소속은 이름 아래 한 줄이고 데스크톱에서는 LinkedIn 과 나란히 놓인다.
 * 길면 줄이 접히면서 박스가 늘어난다. 화면이 감당할 만한 선에서 자른다.
 *
 * 입력칸의 maxlength 와 저장 시 검사가 이 상수를 함께 쓴다. 두 곳에 숫자를 적어
 * 두면 한쪽만 고쳤을 때 "브라우저는 막는데 서버는 통과"하거나 그 반대가 된다.
 */
const TYPECAST_AFFILIATION_MAX_LENGTH = 100;

/**
 * 프로필 화면에 입력칸을 그린다.
 *
 * 라벨을 한글·영문 병기로 둔다. 이 이미지는 한국(kr/learn)과 영문(learn) 양쪽에
 * 쓰이고, 필드 자체는 어느 쪽에 있어도 해가 없다 — 실제로 화면에 나갈지는 각
 * 사이트의 Elementor 템플릿이 정한다. 로케일이나 URL 로 분기하지 않는 이유는,
 * 판별이 어긋나면 입력칸이 조용히 사라지고 그 증상만으로는 원인을 찾기 어렵기
 * 때문이다. 쓰이지 않는 입력칸 하나가 훨씬 싸다.
 */
function typecast_affiliation_render_field(WP_User $user): void {
    // 남의 프로필을 볼 수만 있고 고칠 수 없는 경우까지 입력칸을 보여줄 필요는 없다.
    if (!current_user_can('edit_user', $user->ID)) {
        return;
    }

    $value = get_user_meta($user->ID, TYPECAST_AFFILIATION_META_KEY, true);
    ?>
    <h2>블로그 작성자 정보</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>
                <label for="typecast_affiliation">소속 (Affiliation)</label>
            </th>
            <td>
                <?php wp_nonce_field('typecast_affiliation_' . $user->ID, 'typecast_affiliation_nonce'); ?>
                <input
                    type="text"
                    id="typecast_affiliation"
                    name="typecast_affiliation"
                    value="<?php echo esc_attr($value); ?>"
                    class="regular-text"
                    maxlength="<?php echo esc_attr((string) TYPECAST_AFFILIATION_MAX_LENGTH); ?>"
                />
                <p class="description">
                    아티클 하단 작성자 박스에서 이름 아래에 표시됩니다. 예: 네오사피엔스 콘텐츠팀
                </p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('show_user_profile', 'typecast_affiliation_render_field');
add_action('edit_user_profile', 'typecast_affiliation_render_field');

/**
 * 저장한다.
 *
 * 이 훅은 프로필 화면 말고도 사용자를 갱신하는 다른 경로에서 실행될 수 있다. 그런
 * 호출에는 우리 필드도 nonce 도 없으므로, 값이 안 왔다고 메타를 지우면 안 된다 —
 * 남의 코드가 사용자를 저장할 때마다 소속이 지워진다. nonce 가 없으면, 그리고 nonce 를
 * 통과했더라도 우리 입력칸이 요청에 없으면 그대로 둔다. 지우는 것은 입력칸이 실제로
 * 비어서 왔을 때뿐이다.
 */
function typecast_affiliation_save(int $user_id): void {
    if (!isset($_POST['typecast_affiliation_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['typecast_affiliation_nonce']));

    if (!wp_verify_nonce($nonce, 'typecast_affiliation_' . $user_id)) {
        return;
    }

    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    // 필드가 아예 안 온 요청은 손대지 않는다. nonce 를 통과했더라도 우리 입력칸이
    // 없는 폼이라면 "비웠다"가 아니라 "이 폼은 소속을 다루지 않는다"는 뜻이다.
    // 여기서 지우면 그런 저장 경로가 생길 때마다 값이 조용히 사라진다.
    if (!isset($_POST['typecast_affiliation']) || !is_string($_POST['typecast_affiliation'])) {
        return;
    }

    $value = trim(sanitize_text_field(wp_unslash($_POST['typecast_affiliation'])));

    // maxlength 는 브라우저에서만 막는다. 조작한 요청이나 다른 저장 경로로 들어온
    // 긴 값은 여기서 자른다. mb_substr 은 mbstring 이 없어도 워드프레스가
    // wp-includes/compat.php 에서 폴리필하므로 한글이 중간에서 깨지지 않는다.
    if (mb_strlen($value) > TYPECAST_AFFILIATION_MAX_LENGTH) {
        $value = trim(mb_substr($value, 0, TYPECAST_AFFILIATION_MAX_LENGTH));
    }

    if ($value === '') {
        // 빈 문자열을 남기지 않고 지운다. 남겨 두면 Elementor 가 "값이 있다"고 보고
        // 빈 줄과 그 위아래 여백을 그대로 그린다.
        delete_user_meta($user_id, TYPECAST_AFFILIATION_META_KEY);

        return;
    }

    update_user_meta($user_id, TYPECAST_AFFILIATION_META_KEY, $value);
}

add_action('personal_options_update', 'typecast_affiliation_save');
add_action('edit_user_profile_update', 'typecast_affiliation_save');
