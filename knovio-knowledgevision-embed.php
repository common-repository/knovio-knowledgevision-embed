<?php
/**
 * @package knovio-knowledgevision-embed
 */

/*
Plugin Name:    Knovio-KnowledgeVision Embed
Plugin URI:     http://www.knowledgevision.com/plugins/wordpress/knovio-knowledgevision-embed/
Description:    This free plugin makes it easy to embed any Knovio or KnowledgeVision online presentation onto a WordPress page or post.
Version:        1.0.0
Author:         KnowledgeVision Team
Author URI:     http://knowledgevision.com/
License:        GPLv2 or later
Text Domain:    knovio-knowledgevision-embed
*/

/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2016 KnowledgeVision Systems, Inc.
*/


/*
 * If this file is called directly, abort.
 */
defined( 'ABSPATH' ) or die();

/*
 * For developers only. Set 'KK_EMBED_DEBUG' to true to use dev environment (else - false)
 */
define( 'KK_EMBED_DEBUG', false );
if(KK_EMBED_DEBUG){
    define( 'KK_EMBED_ENDPOINT', 'http://dev-view.knowledgevision.com/service/oembed' );
    define( 'KK_EMBED_FORMAT', 'https://dev-view.knowledgevision.com/presentation/*' );
} else {
    define( 'KK_EMBED_ENDPOINT', 'http://view.knowledgevision.com/service/oembed' );
    define( 'KK_EMBED_FORMAT', 'https://view.knowledgevision.com/presentation/*' );
}

define( 'KK_EMBED_PLUGIN_NAME', 'Knovio-KnowledgeVision Embed' );
define( 'KK_EMBED_VERSION', '1.0' );
define( 'KK_EMBED_MINIMUM_WP_VERSION', '3.0' );
define( 'KK_EMBED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, 'kk_embed_plugin_activation' );
register_deactivation_hook( __FILE__, 'kk_embed_plugin_deactivation' );

function kk_embed_plugin_activation() {
    if ( version_compare( $GLOBALS['wp_version'], KK_EMBED_MINIMUM_WP_VERSION, '<' ) ) {

        $message = '<p><strong>'
            . sprintf(esc_html__( '%s %s requires WordPress %s or higher.' , 'knovio-knowledgevision-embed'), KK_EMBED_PLUGIN_NAME, KK_EMBED_VERSION, KK_EMBED_MINIMUM_WP_VERSION )
            . '</strong></p>'
            . '<p>'
            . __('Please <a href="https://codex.wordpress.org/Upgrading_WordPress" target="_blank">upgrade WordPress</a> to a current version.', 'knovio-knowledgevision-embed')
            . '</p>';
        ?>
        <!doctype html>
        <html>
            <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <style>
                    * { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                    p { margin-top: 1em; font-size: 12px; }
                </style>
            <body>
                <p><?= $message; ?></p>
            </body>
        </html>
        <?php

        $plugins = get_option( 'active_plugins' );
        $kk_embed_plugin = plugin_basename( KK_EMBED_PLUGIN_DIR . 'knovio-knowledgevision-embed.php' );
        $update  = false;

        foreach ( $plugins as $i => $plugin ) {
            if ( $plugin === $kk_embed_plugin ) {
                $plugins[$i] = false;
                $update = true;
            }
        }

        if ( $update ) {
            update_option( 'active_plugins', array_filter( $plugins ) );
        }

        exit;
    }
}

function kk_embed_plugin_deactivation() {
    wp_oembed_remove_provider( KK_EMBED_FORMAT );
}

/*
 * Add KK Embed provider.
 */
wp_oembed_add_provider( KK_EMBED_FORMAT, KK_EMBED_ENDPOINT );

/*
 * KK Embed styles for embedded presentation
 */
add_action( 'wp_head', function ()
{
    echo '<style type="text/css">'
        . '.KnowledgeVisionEmbeddedContent { border: 1px solid lightgrey; }'
        . '</style>';
} );

/*
 * Refreshing the oEmbed HTML, on single post loads (only once).
 * We set a fixed re-cache time (current) and when a single post is loaded, we compare it to the time the oEmbed HTML was last cached.
 * If the last cache time is before the re-cache time, then we need to regenerate it.
 */
add_filter( 'oembed_ttl', function( $ttl, $url, $attr, $post_ID )
{
    // Only do this on posts
    if( $post_ID ) {
        global $wp_embed;

        // oEmbeds cached before this time, will be re-cached:
        $recache_time = date( 'Y-m-d H:i:s', time() );

        // Get the time when oEmbed HTML was last cached (based on the WP_Embed class)
        $key_suffix = md5( $url . serialize( $attr ) );
        $cachekey = '_oembed_' . $key_suffix;
        $cachekey_time = '_oembed_time_' . $key_suffix;
        $cache_time = get_post_meta( $post_ID, $cachekey_time, true );

        // Get the cached HTML
        $cache_html = get_post_meta( $post_ID, $cachekey, true );

        // Check if we need to regenerate the oEmbed HTML:
        if( $cache_time < strtotime( $recache_time )             // cache time check
            && false !== strpos( $cache_html, '{{unknown}}' )
            && strpos( $url, 'view.knowledgevision.com' )
            && !do_action( 'wpse_do_cleanup' )                  // just run this once
            && $wp_embed->usecache ) {

            // What we need to skip the oEmbed cache part
            $wp_embed->usecache = false;
            $ttl = 0;
            // House-cleaning
            do_action( 'wpse_do_cleanup' );
        }
    }
    return $ttl;
}, 10, 4 );

/*
 * Set the usecache attribute back to true.
 */
add_filter( 'embed_oembed_discover', function( $discover )
{
    if( did_action( 'wpse_do_cleanup' ) === 1 ) {
        global $wp_embed;
        $wp_embed->usecache = true;
    }
    return $discover;
} );
