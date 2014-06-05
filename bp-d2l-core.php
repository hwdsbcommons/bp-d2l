<?php
/**
 * BP D2L Core
 *
 * @package BP_D2L
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core class for BP D2L.
 *
 * Extends the {@link BP_Component} class.
 *
 * @package BP_D2L
 * @subpackage Classes
 */
class BP_D2L extends BP_Component {

	/**
	 * Constructor.
	 *
	 * @global obj $bp BuddyPress instance
	 */
	function __construct() {
		// let's start the show!
		parent::start(
			'd2l',
			__( 'Desire2Learn', 'bp-d2l' ),
			constant( 'BP_D2L_DIR' ) . '/includes'
		);

		// constants
		$this->constants();

		// includes
		$this->includes();

		// register our component as an active component in BP
		buddypress()->active_components[$this->id] = '1';
	}

	protected function constants() {
		if ( ! defined( 'BP_D2L_MEMBER_SLUG' ) ) {
			define( 'BP_D2L_MEMBER_SLUG', 'courses' );
		}

		if ( ! defined( 'BP_D2L_GROUP_SLUG' ) ) {
			define( 'BP_D2L_GROUP_SLUG', 'course' );
		}
	}

	/**
	 * Includes.
	 *
	 * @since 1.0
	 *
	 * @todo Only allow this on the frontend?
	 */
	public function includes( $includes = array() ) {
		if ( bp_is_active( 'groups' ) ) {
			require $this->path . '/functions.php';
			require $this->path . '/group-extension.php';
			require $this->path . '/hooks.php';
		}
	}

	/**
	 * Setup profile / BuddyBar navigation
	 *
	 * @since 1.0
	 */
	function setup_nav( $main_nav = array(), $sub_nav = array() ) {

		// BuddyBar compatibility
		//$domain = bp_displayed_user_domain() ? bp_displayed_user_domain() : bp_loggedin_user_domain();

		// Need to change the user ID, so if we're not on a member page, $counts variable is still calculated
		//$user_id = bp_is_user() ? bp_displayed_user_id() : bp_loggedin_user_id();
		//$counts  = bp_follow_total_follow_counts( array( 'user_id' => $user_id ) );

		/** FOLLOWERS NAV ************************************************/

		bp_core_new_nav_item( array(
			//'name'                => sprintf( __( 'Courses <span>%d</span>', 'bp-d2l' ), 0 ),
			'name'                => __( 'Courses', 'bp-d2l' ),
			'slug'                => constant( 'BP_D2L_MEMBER_SLUG' ),
			'position'            => apply_filters( 'bp_d2l_nav_position', 71 ),
			'screen_function'     => array( $this, 'member_screen' ),
			'default_subnav_slug' => constant( 'BP_D2L_MEMBER_SLUG' ),
			'item_css_id'         => 'groups-courses'
		) );

		do_action( 'bp_d2l_setup_nav' );
	}

	/**
	 * Set up WP Toolbar
	 *
	 * @global obj $bp BuddyPress instance
	 */
	function setup_admin_bar( $wp_admin_nav = array() ) {

		// Menus for logged in user
		if ( bp_loggedin_user_id() ) {

			$d2l_root = 'https://' . constant( 'BP_D2L_HOST' ) . '/d2l/lms/';
			$org_id   = bp_d2l_get_org_id();

			// Main nav - Courses
			$wp_admin_nav['parent'] = array(
				'parent' => buddypress()->my_account_menu_id,
				'id'     => 'my-account-' . $this->id,
				'title'  => __( 'Courses', 'bp-d2l' ),
				'href'   => trailingslashit( bp_loggedin_user_domain() . constant( 'BP_D2L_MEMBER_SLUG' ) )
			);

			// Subnav - Course Pager
			$wp_admin_nav['course-pager'] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-pager',
				'title'  => __( 'Course Pager', 'bp-d2l' ),
				'href'   => $d2l_root . "pager/messageList.d2l?ou={$org_id}"
			);

			// Subnav - Course Email
			$wp_admin_nav['course-email'] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-email',
				'title'  => __( 'Course Email', 'bp-d2l' ),
				'href'   => $d2l_root . "email/frame.d2l?ou={$org_id}"
			);
		}

		parent::setup_admin_bar( apply_filters( 'bp_d2l_toolbar', $wp_admin_nav ) );
	}

	/**
	 * Logic for a member's "Courses" screen.
	 */
	public function member_screen() {
		// run some hooks after the member header is rendered
		add_action( 'bp_after_member_header',  array( $this, 'run_later_hooks' ) );

		// add our hook to inject content into BP
		add_action( 'bp_template_content', create_function( '', "
			bp_get_template_part( 'members/single/courses' );
		" ) );

		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Hooks that run later on a user's "Courses" page.
	 *
	 * Right after the member header is rendered.
	 */
	public function run_later_hooks() {
		// on a user's "Courses" page, fake component to be a group
		// this is so the ajax dropdown filter shows up
		add_action( 'bp_before_member_body', create_function( '', "
			add_filter( 'bp_is_current_component', 'bp_d2l_faux_groups_component', 10, 2 );
		" ) );

		// this tells BP to look for templates in our plugin directory last
		// when the template isn't found in the parent / child theme
		//
		// we only have to run this before theme compat does its object buffering
		// this is so we don't have to do unnecessary file checks at the beginning
		bp_register_template_stack( 'bp_d2l_get_template_directory', 14 );
	}

	public function credentials() {
		$constants = array(
			'APPKEY',
			'APPID',
			'HOST',
			'USERID',
			'USERKEY'
		);

		foreach ( $constants as $constant ) {
			if ( ! defined( 'BP_D2L_' . $constant ) ) {
				_doing_it_wrong(
					'BP_D2L::credentials()',
					sprintf( __( 'The "%s" constant needs to be defined in order for BP D2L to run', 'bp-d2l' ), $constant )
				);

				return false;
			}
		}

		return array(
			'appkey'  => constant( 'BP_D2L_APPKEY' ),
			'appid'   => constant( 'BP_D2L_APPID' ),
			'host'    => constant( 'BP_D2L_HOST' ),
			'userid'  => constant( 'BP_D2L_USERID' ),
			'userkey' => constant( 'BP_D2L_USERKEY' )
		);
	}
}

/**
 * Loads the D2L component.
 *
 * @since 1.0
 */
function bp_d2l_loader() {
	buddypress()->d2l = new BP_D2L;
}
add_action( 'bp_loaded', 'bp_d2l_loader' );
