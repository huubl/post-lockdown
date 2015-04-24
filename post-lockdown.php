<?php
/**
 * Plugin Name: PostLockdown
 * Plugin URI: http://www.exleysmith.com
 * Description: Allows admins to prevent certain posts of any post type from being deleted by lower users
 * Version: 0.1.0
 * Author: Exley and Smith Ltd
 * Author URI: http://www.exleysmith.com
 * License: GPL2
 * Text Domain: post-lockdown
 * Domain Path: /languages
 */

PostLockdown::init();

class PostLockdown {

	public static $locked_post_ids = array();
	public static $cap = 'manage_options';

	public static function init() {

		self::$locked_post_ids = array_flip( get_option( 'postlockdown_locked_posts', array() ) );

		//add_action( 'before_delete_post', array( __CLASS__, 'before_delete_post_hook' ) );

		//add_filter( 'post_row_actions', array(__CLASS__, 'remove_trash_action' ), 10, 2 );
		//add_filter( 'page_row_actions', array(__CLASS__, 'remove_trash_action' ), 10, 2 );

		add_action( 'admin_menu', array(__CLASS__, 'add_options_page') );
		add_action( 'admin_init', array(__CLASS__, 'register_settings' ) );

		add_filter( 'user_has_cap', array(__CLASS__, 'filter_cap'), 10, 3 );

	}

	public static function filter_cap($allcaps, $cap, $args) {

		if ( $args[0] != 'delete_post' && $args[0] != 'publish_pages' && $args[0] != 'publish_posts' ) {
			return $allcaps;
		}

		//die(var_dump($cap));

		if ( !empty( $allcaps[ self::$cap ] ) ) {
			return $allcaps;
		}

		if ( isset( $args[2] ) ) {
			$post_id = $args[2];
		} else {
			global $post;
			$post_id = $post->ID;
		}

		if ( isset( self::$locked_post_ids[ $post_id ] ) ) {
			$allcaps[ $cap[0] ] = false;
		}

		return $allcaps;
	}

	public static function add_options_page() {

		add_options_page('post-lockdown', 'Post Lockdown', self::$cap, 'post-lockdown', array(__CLASS__, 'output_options_page') );

	}

	public static function register_settings() {

		register_setting( 'post-lockdown', 'postlockdown_locked_posts' );

	}

	public static function output_options_page() {

		$post_types = array();

		foreach( get_post_types( array(), 'objects' ) as $post_type ) {

			$posts = get_posts( array(
				'post_type' => $post_type->name,
				'posts_per_page' => -1
			) );

			if ( empty( $posts ) ) {
				continue;
			}

			$post_types[ $post_type->name ] = array( 'label' => $post_type->label, 'posts' => array() );

			foreach( $posts as $post ) {

				$selected = isset( self::$locked_post_ids[ $post->ID ] );

				$post_types[ $post_type->name ]['posts'][] = array(
					'ID' => $post->ID,
					'post_title' => $post->post_title,
					'selected' => $selected
				);

			}

		}

		include_once( __DIR__ . '/options-page.php' );

	}

	public static function before_delete_post_hook($post_id) {

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( in_array($post_id, self::$locked_post_ids ) ) {

			wp_die( __( 'Sorry, this post is unable to be deleted. Please contact one of the site administrators', 'post-lockdown' ) );
		}

		return true;
	}

	public static function remove_trash_action($actions, $post) {

		if ( isset( $actions['trash'] ) && isset( self::$locked_post_ids[ $post->ID ] ) ) {
			unset( $actions['trash'] );
		}

		return $actions;

	}

}