<?php
/**
 * @package YD_Network-Wide-WPML
 * @author Yann Dubois
 * @version 0.1.4
 */

/*
 Plugin Name: YD Network Wide WPML
 Plugin URI: http://www.yann.com/en/wp-plugins/yd-network-wide-wpml
 Description: Make WPML network wide. Make sitewide tags multilanguage. | Funded by <a href="http://www.eurospreed.com">Eurospreed</a>
 Version: 0.1.4
 Author: Yann Dubois
 Author URI: http://www.yann.com/
 License: GPL2
 */

/**
 * @copyright 2010  Yann Dubois  ( email : yann _at_ abc.fr )
 *
 *  Original development of this plugin was kindly funded by http://www.abc.fr
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 Revision 0.1.0:
 - Original beta release
 Revision 0.1.1:
 - Keeps bound on translated posts
 - Option page to get rid of bottom link
 Revision 0.1.2:
 - Bugfixes
 Revision 0.1.3:
 - Bugfix: deactivate WPML filters before replicating article, otherwise Categories and Tags get duplicated
 - Deactivated debug code (for blog ID 2)
 Revision 0.1.4:
 - Bugfix: no backlinkware text at all.
 
 TODO:
 hook for article modification (check if language has changed!)
 
 */

include_once( 'inc/yd-widget-framework.inc.php' );

/**
 * 
 * Just fill up necessary settings in the configuration array
 * to create a new custom plugin instance...
 * 
 */
$junk = new YD_Plugin( 
	array(
		'name' 				=> 'YD Network Wide WPML',
		'version'			=> '0.1.3',
		'has_option_page'	=> true,
		'has_shortcode'		=> false,
		'has_widget'		=> false,
		'widget_class'		=> '',
		'has_cron'			=> false,
		'crontab'			=> array(
			'daily'			=> array( 'YD_MiscWidget', 'daily_update' ),
			'hourly'		=> array( 'YD_MiscWidget', 'hourly_update' )
		),
		'has_stylesheet'	=> false,
		'stylesheet_file'	=> 'css/yd.css',
		'has_translation'	=> false,
		'translation_domain'=> '', // must be copied in the widget class!!!
		'translations'		=> array(
			array( 'English', 'Yann Dubois', 'http://www.yann.com/' ),
			array( 'French', 'Yann Dubois', 'http://www.yann.com/' )
		),		
		'initial_funding'	=> array( 'Wellcom', 'http://www.wellcom.fr' ),
		'additional_funding'=> array(),
		'form_blocks'		=> array(
			//'block1' => array( 
			//	'test'	=> 'text' 
			//)
		),
		'option_field_labels'=>array(
			//	'test'	=> 'Test label'
		),
		'option_defaults'	=> array(
			//	'test'		=> 'whatever'
		),
		'form_add_actions'	=> array(
			//	'Manually run hourly process'	=> array( 'YD_MiscWidget', 'hourly_update' ),
			//	'Check latest'					=> array( 'YD_MiscWidget', 'check_update' )
		),
		'has_cache'			=> false,
		'option_page_text'	=> 'Welcome to the Network Wide WPML Plugin settings page.',
		'backlinkware_text' => '',
		'plugin_file'		=> __FILE__		
 	)
);

add_action( 'wp_insert_post', array( 'YD_NetworkWideWPML', 'sync' ), 100, 2 );
add_action( 'save_post', array( 'YD_NetworkWideWPML', 'deactivate' ), 9, 2);

/**
 * TODO: check if other action hooks are necessary
 * list of hooks from WordPress MU Sitewide Tags Pages hereunder
 *
add_action('save_post', 'sitewide_tags_post', 10, 2);
add_action('delete_post', 'sitewide_tags_post_delete');
/* complete blog actions ($blog_id != 0) *
add_action('delete_blog', 'sitewide_tags_remove_posts', 10, 1);
add_action('archive_blog', 'sitewide_tags_remove_posts', 10, 1);
add_action('deactivate_blog', 'sitewide_tags_remove_posts', 10, 1);
add_action('make_spam_blog', 'sitewide_tags_remove_posts', 10, 1);
add_action('mature_blog', 'sitewide_tags_remove_posts', 10, 1);
/* single post actions ($blog_id == 0) *
add_action("transition_post_status", 'sitewide_tags_remove_posts');
add_action('update_option_blog_public', 'sitewide_tags_public_blog_update', 10, 2);
**/

/**
 * 
 * You must specify a unique class name
 * to avoid collision with other plugins...
 * 
 */
class YD_NetworkWideWPML {    

	function sync( $post_id, $post ) {
		$debug = false;
		global $wpdb;
	
		if( !get_sitewide_tags_option( 'tags_blog_enabled' ) )
			return;
	
		$tags_blog_id = get_sitewide_tags_option( 'tags_blog_id' );
		if( !$tags_blog_id || $wpdb->blogid == $tags_blog_id )
			return;
	
		$allowed_post_types = apply_filters( 'sitewide_tags_allowed_post_types', array( 'post' => true ) );
		if ( !$allowed_post_types[$post->post_type] ) 
			return;
	
		$post_blog_id = $wpdb->blogid;
		$blog_status = get_blog_status($post_blog_id, "public");
		if ( $blog_status != 1 && ( $blog_status != 0 || get_sitewide_tags_option( 'tags_blog_public') == 1 || get_sitewide_tags_option( 'tags_blog_pub_check') == 0 ) )
			return;
	
		if( false && 2 == $post_blog_id ) $debug = true;
		if( $debug ) echo 'post_blog_id: ' . $post_blog_id . '<br/>';
		if( $debug ) echo 'ID: ' . $post->ID . ' - ' . $post_id . '<br/>';

		// select translation record of new post on original blog
		$query = "
			SELECT 
				element_type, 
				element_id, 
				trid, 
				language_code, 
				source_language_code 
			FROM
				$wpdb->prefix" . "icl_translations
			WHERE
				element_type = 'post_post'
			AND	element_id = $post_id
		";
		$icl_tr = $wpdb->get_row( $query, ARRAY_A ); // translation record
		if( $debug ) echo $query . '<br/>';
		if( $debug ) var_dump( $icl_tr );
	
		if( $icl_tr ) {
			$permalink = get_permalink( $post_id );

			// find matching replicated post ID on tags blog
			switch_to_blog( $tags_blog_id );
			$query = "
				SELECT
					post_id
				FROM
					$wpdb->postmeta
				WHERE
					meta_key = 'permalink'
				AND	meta_value = '$permalink'
			";
			$rep_p = $wpdb->get_row( $query, ARRAY_A ); // replicated post
			if( $debug ) echo $query . '<br/>';
			$replicate_post_id = $rep_p['post_id'];
			if( $debug ) echo 'replicate post id: ' . $replicate_post_id . '<br/>';
			
			if( $replicate_post_id ) {

				// Check on original blog if post is a translation
				// this will return an array of all available translations URL
				restore_current_blog();
				$query = "
					SELECT
						b.element_id
					FROM
						$wpdb->prefix"."icl_translations AS a,
						$wpdb->prefix"."icl_translations AS b
					WHERE
						a.element_type = 'post_post'
					AND a.element_id = $post_id
					AND b.element_type = 'post_post'
					AND b.trid = a.trid
				";
				$orig_tr = $wpdb->get_col( $query ); // original translations
				if( $debug ) echo $query . '<br/>';
				if( $debug ) var_dump( $orig_tr );
				if( $orig_tr ) {
					foreach( $orig_tr as &$p ) $p = get_permalink( $p );
				}
				if( $debug ) var_dump( $orig_tr );
				switch_to_blog( $tags_blog_id );
				
				// if so, get trid of any already replicated translation on tags blog
				if( count( $orig_tr ) > 1 ) {
					$trlist = "'" . join( "','", $orig_tr ) . "'";
					$query = "
						SELECT
							t.trid
						FROM
							$wpdb->prefix"."icl_translations AS t,
							$wpdb->postmeta AS m
						WHERE
							t.element_type = 'post_post'
						AND t.element_id = m.post_id
						AND m.meta_key = 'permalink'
						AND m.meta_value IN ( $trlist )
						LIMIT 1
					";
					$trid = $wpdb->get_var( $query );
					if( $debug ) echo $query . '<br/>';
					if( $debug ) echo "existing trid: $trid<br/>";
					if( !$trid ) $trid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");
					if( $debug ) echo "final trid: $trid<br/>";
				} else {
					// this is dangerous but I don't see any other way to get a new trid :(
					$trid = 1 + $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations");
					if( $debug ) echo "new trid: $trid<br/>";
				}
				
				if( $replicate_post_id && $trid && $icl_tr['language_code'] ) {
					// insert appropriate translation data on tags blog
					$query = "
						INSERT INTO
							$wpdb->prefix" . "icl_translations
						(
							element_type,
							element_id,
							trid,
							language_code,
							source_language_code
						) VALUES (
							'post_post',
							$replicate_post_id,
							$trid,
							'" . $icl_tr['language_code'] . "',
							'" . $icl_tr['source_language_code'] . "'
						)
					";
					$wpdb->query( $query );
					if( $debug ) echo $query . '<br/>';
				} else {
					if( $debug ) echo 'Incomplete query: missing some data.<br/>';
					if( $debug ) echo $query . '<br/>';
				}
				
				//TODO: Check if post has tags or categories,
				// Manage tags and categories translation info
				
			} // / if( $replicate_post_id )
			
			restore_current_blog();
			
		} // / if( $icl_tr ) [ie. there is a translation record on original blog]
		 
	} // / function sync()

	function deactivate() {

		global $wpdb;
		global $sitepress;
		global $wp_filter;
		$tag = 'wp_head';
		$debug = false;
		
		$post_blog_id = $wpdb->blogid;
		if( false && 2 == $post_blog_id ) $debug = true;
		if( $debug ) echo "deactivate<br/>\n";

		foreach( $wp_filter as $tag => $val ) {

			if( false || 
				$tag == 'posts_join' || 
				$tag == 'posts_where' || 
				$tag == 'getarchives_join' || 
				$tag == 'getarchives_where' ||
				$tag == 'get_terms' ||
				$tag == 'list_terms_exclusions' ||
				//$tag == 'term_links-category' ||
				//$tag == 'get_term' ||
				//$tag == 'get_pages' ||
				false
			) {
				foreach( $wp_filter[ $tag ] as $priority => $list ) {
					foreach( $list as $c => $f ) {
						//echo $c . ' - ';
						if( is_array( $f['function'] ) ) {
							//echo $c . ' - array<br/>';
							if( get_class( $f['function'][0] ) == get_class( $sitepress ) ) {
								//echo $tag . ' - ' . $f['function'][1] . ' - ' . $priority . '<br/>';
								$rem = array( &$sitepress, $f['function'][1] );
								$res = remove_filter( $tag, $rem, $priority );
								//echo "remove_action( '$tag', '$f' )<br/>";
								//echo 'res : ' . $res . '<br/>';
							}
						}
					}
				}
			}
		}
	} // / function deactivate()
	
} // / class YD_NetworkWideWPML
?>