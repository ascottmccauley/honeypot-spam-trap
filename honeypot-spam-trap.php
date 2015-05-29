<?php
/*
Plugin Name: Honeypot Spam Trap
Plugin URI: 
Description: Adds several (hidden) decoy fields to the comment form and registration form; Renames the correct fields daily; Automatically blacklist/spam IPs that fill out those hidden fields
Version: 0.1
Author: AScottMcCauley
Author URI: http://ascottmccauley.com
*/

defined( 'ABSPATH' ) or die( 'Plugin file cannot be accessed directly.' );

class HoneypotSpamTrap {

	protected $fields;
	
	public function __construct() {}
	
	// Set fields and setup actions for comments and registration forms
	public function setup() {
		$this->fields = array( 'First Name', 'Last Name', 'Email 2', 'Address', 'Address2', 'City', 'State', 'Zipcode', 'Telephone', 'Phone' );
		
		// Add trap to comment form
		add_filter( 'comment_form_defaults', array( $this, 'add_comment_trap' ) );
		add_action( 'pre_comment_on_post', array( $this, 'unhash_comment_fields' ) );
		add_action( 'comment_post', array( $this, 'check_comment_trap' ) );
		
		// Add trap to login form
		add_action( 'login_form', array( $this, 'add_login_trap' ) );
		add_filter( 'authenticate', array( $this, 'check_login_trap' ), 10, 3 );
		
		// Add trap to registration form
		add_action( 'register_form', array( $this, 'add_login_trap' ) );
		add_filter( 'registration_errors', array( $this, 'check_login_trap' ) );
		
		// Add inline style to hide fields
		add_action( 'wp_enqueue_scripts', array( $this, 'add_hide_css' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'add_hide_css' ) );
	}
	
	public function get_decoy_fields( ) {
		$hash = wp_create_nonce( 'spam_trap' );
		$decoy_fields = '';
		foreach ( $this->fields as $field ) {
			$name = strtolower( str_replace( ' ', '', $field ) ) . $hash;
			$decoy_fields .= '<label class="hide" for="' . $name .'">' . ucwords( $field ) . ' *</label><input class="hide" name="' . $name . '" type="text" autocomplete="off">';
		}
		return $decoy_fields;
	}
	
	// Add decoy fields to the comment form
	public function add_comment_trap( $args ) {
		if ( ! is_user_logged_in() ) {
			$hash = wp_create_nonce( 'spam_trap' );
			
			// reverse order to place decoys at front of form.
			$args['fields'] = array_reverse( $args['fields'], true );
			$args['fields']['spamtrap'] = $this->get_decoy_fields();
			
			// reverse back so the correct fields are in the right order with the decoys at the top of the form
			$args['fields'] = array_reverse( $args['fields'], true );
			
			// Add $hash to author and email fields
			$args['fields']['author'] = str_replace( 'name="author"', 'name="author' . $hash . '"', $args['fields']['author'] );
			$args['fields']['email'] = str_replace( 'name="email"', 'name="email' . $hash . '"', $args['fields']['email'] );
		}
		
		return $args;
	}
	
	// Remove the hash string from the correct comment fields before submitting
	public function unhash_comment_fields ( $commentdata ) {
		$hash = wp_create_nonce( 'spam_trap' );
		
		if( isset( $_POST['author' . $hash] ) ) {
			$_POST['author'] = sanitize_text_field( $_POST['author' . $hash] );
		}
		if( isset( $_POST['email' . $hash] ) ) {
			$_POST['email'] = sanitize_email( $_POST['email' . $hash]  );
		}
	}
	
	// Check the comment form and flag as spam if necessary
	public function check_comment_trap( $comment_id, $approved = 'null' ) {
		// first check http_referer
		$siteURL = str_ireplace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );
		if ( isset( $_SERVER['HTTP_REFERER'] ) && !stripos( $_SERVER['HTTP_REFERER'], $siteURL ) ) {
			wp_die( 'There was an error submitting your comment.', 'Error' );
			exit;
		} else {
			if ( $approved != 'spam' || is_user_logged_in() ) { // No need to check twice
				$hash = wp_create_nonce( 'spam_trap' );
				foreach ( $this->fields as $field ) {
					$name = strtolower( str_replace( ' ', '', $field ) ) . $hash;
					// if any decoy field has been filled out mark it as spam
					if ( isset ( $_POST[$name] ) ) {
						if( $_POST[$name] != '' ) {
							wp_spam_comment( $comment_id );
							exit;
						}
					}
				}
			}
		}
	}	
	
	// Add decoy fields to the login and register form
	public function add_login_trap( ) {
		echo $this->get_decoy_fields();
	}
	
	// Check the register form and throw up a non-descript error
	public function check_login_trap( $user ) {
		// first check http_referer
		$siteURL = str_ireplace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );
		if ( isset( $_SERVER['HTTP_REFERER'] ) && ! stripos( $_SERVER['HTTP_REFERER'], $siteURL ) ) {
			$user = new WP_Error( 'loginError',  'There is an error with the url' );
		} else {
		
			$hash = wp_create_nonce( 'spam_trap' );
			foreach ( $this->fields as $field ) {
				$name = strtolower( str_replace( ' ', '', $field ) ) . $hash;
				if( isset( $_POST[$name] ) ) {
					$user = new WP_Error( 'loginError',  'There is an error with the submissino' );
				}
			}
		}
		return $user;
	}
	
	public function add_hide_css( ) {
		echo '<style>.hide{display:none !important;}</style>';
	}
	
}

function add_spam_trap() {
	$spamtrap = new HoneypotSpamTrap();
	$spamtrap->setup();
}
add_action( 'init', 'add_spam_trap' );
