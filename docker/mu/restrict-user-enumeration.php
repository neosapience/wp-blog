<?php
/**
 * Plugin Name: Restrict REST user enumeration
 * Description: Hides the REST users collection from anonymous callers. Logged-in
 *              users keep the full API. Shipped in the image at /opt/wp-mu and
 *              require'd by the mu-plugins loader.
 * Author:      neosapience
 *
 * WordPress answers GET /wp-json/wp/v2/users for anonymous callers with every
 * author that has published a post — login slugs included. That is a ready-made
 * username list for credential stuffing. See wp-blog#2.
 *
 * Scope, honestly: this closes one path, not the leak. The same slugs still come
 * out of oEmbed (author_name and author_url for any post URL, no auth needed) and
 * of /author/<slug>/ vs a bogus slug answering 301 vs 404. Usernames are not
 * secrets in WordPress; the real defence for credential stuffing is on the login
 * side — attempt limiting, 2FA, strong passwords. This filter is a cheap
 * narrowing, not a fix, and it must not cost anything on the authenticated side.
 *
 * That is why the gate is is_user_logged_in() and not a capability. Gating on
 * list_users (an admin-level cap) broke a core screen for everyone below admin:
 * the block editor resolves the post author through this collection, so editors
 * and authors saw "(No author)" in the sidebar of every post while the stored
 * author was fine. Ten of our users are in that group and fifteen are admins,
 * which is why it went unnoticed. Blocking anonymous callers is the whole point;
 * blocking logged-in ones bought nothing.
 *
 * This has to live in WordPress rather than Apache: only WordPress knows whether
 * a request is actually authenticated. An Apache rule keyed on the
 * wordpress_logged_in_* cookie is forgeable (a bare
 * "Cookie: wordpress_logged_in_x=1" walked straight past it). That attempt is
 * documented in docker/security.conf in the wp-blog image.
 *
 * It cannot sit in wp-content/mu-plugins inside the image either: wp-content lives
 * under the EBS mount at /var/www/html/kr/learn, so anything COPY'd there during
 * build is masked at runtime by the existing volume. The code therefore ships at
 * /opt/wp-mu (outside the mount) and eks-recipe mounts one loader file into
 * mu-plugins that require's everything in that directory.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('rest_endpoints', function ($endpoints) {
    // Anyone signed in keeps the full API — admin screens, the block editor's
    // author field and Site Kit all read this collection.
    if (is_user_logged_in()) {
        return $endpoints;
    }

    // Only the readable handlers come off. Dropping the whole route also removes
    // the POST/PUT/PATCH/DELETE handlers registered on the same paths, which
    // turns "you are not allowed" (401/403 from the route's own permission
    // check) into "no such route" (404) and takes real capabilities with it —
    // editing your own profile via PUT /wp/v2/users/<id> is one of them.
    //
    // /wp/v2/users/me is a separate route and is deliberately untouched: the
    // block editor requests it on every load to resolve the current user.
    $routes = ['/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)'];

    foreach ($routes as $route) {
        if (empty($endpoints[$route]) || !is_array($endpoints[$route])) {
            continue;
        }

        foreach ($endpoints[$route] as $index => $handler) {
            if (!is_int($index) || !isset($handler['methods'])) {
                continue;
            }

            // This filter runs before WP_REST_Server::get_routes() normalises
            // 'methods', so it is still whatever register_rest_route() was
            // given: 'GET', 'POST, PUT, PATCH', an array, or the normalised
            // ['GET' => true] shape if something already touched it.
            $raw = $handler['methods'];

            if (is_array($raw)) {
                $methods = array_keys($raw) === range(0, count($raw) - 1)
                    ? $raw
                    : array_keys($raw);
            } else {
                $methods = explode(',', (string) $raw);
            }

            // Take the read methods out of the handler instead of dropping the
            // handler. register_rest_route() allows one handler to serve several
            // methods ('GET, POST' is legal), so removing the handler because it
            // answers GET would take its write methods with it. Core currently
            // registers GET separately on these routes, but a plugin adding a
            // mixed handler must not silently lose its writes.
            $kept = [];

            foreach ($methods as $method) {
                $method = strtoupper(trim((string) $method));

                // WP_REST_Server treats HEAD as part of a readable handler.
                if ($method === '' || $method === 'GET' || $method === 'HEAD') {
                    continue;
                }

                $kept[] = $method;
            }

            if (!$kept) {
                // Read-only handler — nothing left to keep.
                unset($endpoints[$route][$index]);
            } elseif (count($kept) !== count($methods)) {
                $endpoints[$route][$index]['methods'] = implode(', ', $kept);
            }
        }

        // A route left with only its shared args and no handlers would make
        // WP_REST_Server emit notices. Remove it outright in that case.
        if (!array_filter(array_keys($endpoints[$route]), 'is_int')) {
            unset($endpoints[$route]);
        }
    }

    return $endpoints;
});
