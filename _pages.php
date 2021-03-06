<?php
/*
Plugin Name: _Pages
Author: Takayuki Miyauchi
Plugin URI: https://github.com/miya0001/_pages
Description: A WordPress shotcode plugin which displays child pages.
Version: nightly
Author URI: http://miya.io/
*/

require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );

add_action( 'init', '_pages_activate_updater' );

function _pages_activate_updater() {
	$plugin_slug = plugin_basename( __FILE__ );
	$gh_user = 'miya0001';
	$gh_repo = '_pages';
	// Activate automatic update.
	new Miya\WP\GH_Auto_Updater( $plugin_slug, $gh_user, $gh_repo );
}

class _Pages
{
	const version = "nightly";
	const default_thumbnail_size = 'post-thumbnail';
	const default_col = 3;

	function __construct()
	{
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	public static function get_instance()
	{
		static $instance;
		if ( ! $instance ) {
			$instance = new _Pages();
		}
		return $instance;
	}

	function plugins_loaded()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_shortcode( "pages", array( $this, "shortcode" ) );
	}

	function init()
	{
		add_post_type_support( 'page', 'excerpt' );
	}

	/**
	 * The callback function for the shortcode.
	 *
	 * @param $p array The parameters which are passed from the shortcode attributes.
	 *
	 * @return string The HTML output for the shortcode.
	 */
	public function shortcode( $p )
	{
		$default = apply_filters( '_pages_defaults', array(
			'id' => get_the_ID(),
			'size' => self::default_thumbnail_size,
			'col' => self::default_col,
		) );

		$p = shortcode_atts( $default, $p, '_pages' );

		$query = array(
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_parent' => $p['id'],
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'nopaging' => true,
		);

		/*
		 * Filter the query args for the get_posts()
		 *
		 * @since none
		 * @param array $args Query args. See http://codex.wordpress.org/Class_Reference/WP_Query#Parameters.
		 */
		$query = apply_filters( '_pages_query', $query, $p );

		return $this->display( $query, intval( $p['col'] ), $p['size'] );
	}

	/**
	 * Displays posts with the specific arguments.
	 *
	 * @param array $query
	 * @param int $col
	 * @param string $size
	 *
	 * @return string
	 */
	public function display( $query, $col = self::default_col, $size = self::default_thumbnail_size )
	{
		global $post;

		$html = '';

		$pages = get_posts( $query );

		/**
		 * @var $post \WP_Post
		 */
		foreach ( $pages as $post ) {
			setup_postdata( $post );

			/*
			 * Filter the $post data.
			 *
			 * @since none
			 * @param object $post Post data.
			 */
			$post = apply_filters( '_pages_object', $post );
			$url = get_permalink( $post->ID );
			$img = get_the_post_thumbnail( $post->ID, $size );

			$title = apply_filters( '_pages_title', $post->post_title, $post, $query );
			$excerpt = apply_filters( '_pages_excerpt', $this->get_excerpt( $post ), $post, $query );

			$tpl = apply_filters( '_pages_template', $this->get_template(), $post, $query );

			$tpl = str_replace( '%post_id%', intval( $post->ID ), $tpl );
			$tpl = str_replace( '%post_title%', esc_html( $title ), $tpl );
			$tpl = str_replace( '%post_url%', esc_url( $url ), $tpl );
			$tpl = str_replace( '%post_thumbnail%', $img, $tpl );
			$tpl = str_replace( '%thumbnail_size%', esc_attr( $size ), $tpl );
			$tpl = str_replace( '%post_excerpt%', $excerpt, $tpl );

			$html .= $tpl;
		}

		wp_reset_postdata();

		wp_enqueue_script(
			'underscore-pages',
			plugins_url( 'js/script.min.js', __FILE__ ),
			array(),
			self::version,
			true
		);

		return sprintf(
			'<div class="underscore-pages col-%d">%s</div>',
			esc_attr( $col ),
			$html
		);
	}

	/**
	 * Get the excerpt from the post.
	 *
	 * @param $post \WP_Post The post object.
	 *
	 * @return string The post excerpt.
	 */
	private function get_excerpt( $post )
	{
		$line = array();

		$excerpt = trim( $post->post_excerpt );
		if ( $excerpt ) {
			$line[] = esc_html( $excerpt );
		}

		if ( 'post' === $post->post_type ) {
			$line[] = get_the_date();
		}

		if ( $line ) {
			return '<div class="post-excerpt">' . join( '<br />', $line ) . '</div>';
		} else {
			return '';
		}
	}

	/**
	 * Returns the HTML template for the shortcode.
	 *
	 * @return string The html template.
	 */
	private function get_template()
	{
		$html = '<section class="item page-%post_id% thumbnail-size-%thumbnail_size%">';
		$html .= '<a class="item-wrap" href="%post_url%">';
		$html .= '<div class="post-thumbnail">%post_thumbnail%</div>';
		$html .= '<div class="post-content">';
		$html .= '<h3 class="post-title">%post_title%</h3>';
		$html .= '%post_excerpt%';
		$html .= '</div>';
		$html .= '</a>';
		$html .= '</section>';

		return $html;
	}
}

_Pages::get_instance();
