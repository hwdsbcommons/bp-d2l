<?php

/**
 *
 */
function bp_d2l_lti_auth() {
	// bail if our special parameter isn't passed
	if ( empty( $_REQUEST['d2lauth'] ) ) {
		return;
	}

	// check if our D2L host is part of the passed service url
	// if not, bail!
	if ( strpos( $_REQUEST['lis_outcome_service_url'], constant( 'BP_D2L_HOST' ) ) === false ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		$user_email = $_REQUEST['lis_person_contact_email_primary'];
	
		// check if email is valid, if not bail!
		if ( ! is_email( $user_email ) ) {
			return;
		}
	
		// find the user
		$user = get_user_by( 'email', $user_email );

		// user exists!
		// log the user in
		if ( ! empty( $user ) ) {
			wp_set_auth_cookie( $user->ID, true, is_ssl() );

			// perhaps add a message with bp_core_add_message()?
		}
	}

	// redirect back home
	wp_safe_redirect( get_home_url() );
	die();
}
add_action( 'bp_actions', 'bp_d2l_lti_auth' );


/**
 * Run some hooks before the group loop renders.
 */
function bp_d2l_before_groups_loop() {
	// if not on a user page, stop now!
	if ( ! bp_is_user() ) {
		return;
	}

	// if not on our "courses" slug, stop now!
	if ( ! bp_is_current_action( constant( 'BP_D2L_MEMBER_SLUG' ) ) ) {
		return;
	}

	// manipulate all 'group' strings to 'course'
	add_filter( 'gettext',                  'bp_d2l_gettext',          10, 3 );

	// manipulate ajax loop
	add_filter( 'bp_ajax_querystring',      'bp_d2l_ajax_querystring', 10, 2 );

	// manipulate group permalink
	add_filter( 'bp_get_group_permalink',   'bp_d2l_group_to_course_link' );

	// manipulate group name
	add_filter( 'bp_get_group_name',        'bp_d2l_group_to_course_name' );

	// remove our group permalink filter so group buttons work in the groups loop
	/* @todo if we want group join buttons, reinstate this
	add_action( 'bp_directory_groups_item', create_function( '', "
		remove_filter( 'bp_get_group_permalink', 'bp_d2l_group_to_course_link' );
	" ) );
	*/

	// remove some group loop hooks
	// since a course is not a group we shouldn't show any group join buttons or
	// group-related plugins
	remove_all_filters( 'bp_directory_groups_item' );
	remove_all_filters( 'bp_directory_groups_actions' );
}
add_action( 'bp_before_groups_loop', 'bp_d2l_before_groups_loop' );

/**
 * Change all 'group' related strings to 'course'
 */
function bp_d2l_gettext( $translated, $untranslated, $domain ) {
	if ( $domain != 'buddypress' ) {
		return $translated;
	}

	switch ( $untranslated ) {
		case 'Private Group' :
			return __( 'Private Course', 'bp-d2l' );
			break;

		case 'Public Group' :
			return __( 'Public Course', 'bp-d2l' );
			break;

		case 'Hidden Group' :
			return __( 'Hidden Course', 'bp-d2l' );
			break;

		case 'Viewing group %1$s to %2$s (of %3$s groups)' :
			return __( 'Viewing course %1$s to %2$s (of %3$s courses)', 'bp-d2l' );
			break;

		case 'There were no groups found.' :
			if ( bp_is_my_profile() ) {
				return __( 'You are not a member of any group that is attached to a course.', 'bp-d2l' );
			} else {
				return __( 'This user is not a member of any group that is attached to a course.', 'bp-d2l' );
			}

			break;

		default :
			return $translated;
			break;
	}
}

/**
 * Alter the AJAX querystring when on a user's "Courses" page.
 *
 * This is so we can filter groups attached to courses.
 */
function bp_d2l_ajax_querystring( $qs, $object ) {
	if ( ! bp_is_user() || ! bp_is_current_action( constant( 'BP_D2L_MEMBER_SLUG' ) ) ) {
		return $qs;
	}

	parse_str( $qs, $query_args );

	$query_args['meta_query'] = array(
		array(
			'key'     => 'bp_d2l_course_id',
			'compare' => 'EXISTS', // only works in WP 3.5+
		)
	);

	return $query_args;
}

/**
 * Change a group's permalink to link to the D2L course.
 */
function bp_d2l_group_to_course_link( $retval ) {
	if ( ! bp_is_user() || ! bp_is_current_action( constant( 'BP_D2L_MEMBER_SLUG' ) ) ) {
		return $retval;
	}

	global $groups_template;

	return bp_d2l_get_course_link( $groups_template->group->id );
}

/**
 * Change a group's name to reflect the D2L course name.
 */
function bp_d2l_group_to_course_name( $retval ) {
	if ( ! bp_is_user() || ! bp_is_current_action( constant( 'BP_D2L_MEMBER_SLUG' ) ) ) {
		return $retval;
	}

	global $groups_template;

	return bp_d2l_get_course_name( $groups_template->group->id );
}

/**
 * When on a user's "Courses" page, fake component to be "groups".
 *
 * This is used so the select dropdown menu shows up.
 */
function bp_d2l_faux_groups_component( $retval, $component ) {
	if ( $component != 'groups' ) {
		return $retval;
	}

	if ( bp_is_user() && bp_is_current_action( constant( 'BP_D2L_MEMBER_SLUG' ) ) ) {
		return true;
	}

	return $retval;
}

/**
 * Add some inline CSS on a user's "Courses" page.
 */
function bp_d2l_user_courses_css() {
	if ( ! bp_is_user() || ! bp_is_current_action( constant( 'BP_D2L_MEMBER_SLUG' ) ) ) {
		return;
	}
?>
	<style type="text/css">
		.item-meta {display:none;}
	</style>
<?php
}
add_action( 'wp_head', 'bp_d2l_user_courses_css' );