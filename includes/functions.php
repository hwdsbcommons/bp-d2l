<?php

/**
 * Ping the D2L API.
 *
 * Wrapper for {@link BP_D2L_API::__construct()}.
 *
 * The $args parameter is the same as the $args parameter in
 * {@link BP_D2L_API::__construct()}.
 *
 * @param array $args
 */
function bp_d2l_request( $args = array() ) {
	// include D2L bridge if it doesn't already exist
	if ( ! class_exists( 'BP_D2L_Auth' ) ) {
		include 'd2l.php';
	}

	// setup our D2L auth object if it doesn't already exist
	if ( empty( buddypress()->d2l->auth ) ) {
		buddypress()->d2l->auth = new BP_D2L_Auth( buddypress()->d2l->credentials() );
	}

	return BP_D2L_API::init( $args, buddypress()->d2l->auth->getOpContext() )->call_d2l();
}

/**
 * Returns the D2L course ID for a BP group if available.
 *
 * @param int $group_id The BuddyPress group ID you want to check
 * @return mixed Integer of D2L course ID on success; boolean false on failure.
 */
function bp_d2l_get_course_id( $group_id = 0 ) {
	// use current group ID, if a group ID isn't passed
	if ( $group_id == 0 ) {
		$group_id = bp_get_current_group_id();
	}
	
	return groups_get_groupmeta( $group_id, 'bp_d2l_course_id' );
}

/**
 * Returns the D2L course name for a BP group if available.
 *
 * @param int $group_id The BuddyPress group ID you want to check
 * @return mixed String of D2L course name on success; boolean false on failure.
 */
function bp_d2l_get_course_name( $group_id = 0 ) {
	// use current group ID, if a group ID isn't passed
	if ( $group_id == 0 ) {
		$group_id = bp_get_current_group_id();
	}
	
	return groups_get_groupmeta( $group_id, 'bp_d2l_course_name' );
}

/**
 * Returns the D2L course link ID for a BP group if available.
 *
 * @param int $group_id The BuddyPress group ID to grab the D2L course link for.
 * @return mixed String of D2L course link on success; boolean false on failure.
 */
function bp_d2l_get_course_link( $group_id = 0 ) {
	return 'https://' . constant( 'BP_D2L_HOST' ) . '/d2l/home/' . bp_d2l_get_course_id( $group_id );
}

/**
 * Returns the D2L course role ID for a BP group if available.
 *
 * @todo THIS NEEDS TO BE COMPLETED
 *
 * @param int $group_id The BuddyPress group ID you want to check
 * @return mixed Integer of D2L course ID on success; boolean false on failure.
 */
function bp_d2l_get_course_role_id( $group_id = 0 ) {
	// use current group ID, if a group ID isn't passed
	if ( $group_id == 0 ) {
		$group_id = bp_get_current_group_id();
	}
	
	return groups_get_groupmeta( $group_id, 'bp_d2l_course_role_id' );
}

/**
 * Get the organization ID for the D2L install.
 */
function bp_d2l_get_org_id() {
	if ( ! defined( 'BP_D2L_ORG_ID' ) ) {
		_doing_it_wrong( "bp_d2l_get_org_id()", "'BP_D2L_ORG_ID' must be defined before using this function.  See documentation in " . __FILE__, null );
		return 0;
	}

	return (int) constant( 'BP_D2L_ORG_ID' );
}

/**
 * Get the course offering organization unit type ID for the D2L install.
 */
function bp_d2l_get_courseoffering_id() {
	if ( ! defined( 'BP_D2L_COURSEOFFERING_ID' ) ) {
		_doing_it_wrong( "bp_d2l_get_courseoffering_id()", "'BP_D2L_COURSEOFFERING_ID' must be defined before using this function.  See documentation in " . __FILE__, null );
		return 0;
	}

	return (int) constant( 'BP_D2L_COURSEOFFERING_ID' );
}

/**
 * Try to get the D2L user ID from a WordPress user ID.
 *
 * The second parameter - $type - is used to determine what D2L field to use
 * to find the WordPress user in D2L.  D2L only accepts two parameters to
 * search for users:
 * - orgDefinedId
 * - userName
 *
 * $type defaults to 'username', which will use the D2L 'userName' parameter.
 * For 'userName' to work, both D2L and WP will need to be setup so usernames
 * are the same in both systems.
 *
 * $type also supports 'id', which will use the D2L 'orgDefinedId' parameter.
 * Since 'orgDefinedId' is variable on different systems, you will need to
 * calculate the D2L user ID yourself by overriding the
 * 'bp_d2l_get_d2l_user_id' filter.
 *
 * @see http://docs.valence.desire2learn.com/res/user.html#get--d2l-api-lp-%28D2LVERSION-version%29-users-
 *
 * @param int $user_id The WordPress user ID
 * @param str $type The type to look up against D2L. See phpDoc for more info.
 */
function bp_d2l_get_d2l_user_id( $user_id = 0, $type = 'username' ) {
	if ( empty( $user_id ) )
		return false;

	// try to see if we've already cached the D2L user ID from before
	$d2l_id = get_user_meta( $user_id, 'bp_d2l_user_id', true );

	// no cached value, so we have to query for it
	if ( empty( $d2l_id ) ) {

		// search D2L Users API with WP username
		if ( $type == 'username' ) {
			$user = get_userdata( $user_id );
	
			$username = bp_is_username_compatibility_mode() ? $user->user_login : $user->user_nicename;
	
			// ping D2L's API
			$request = bp_d2l_request( array(
				'action' => "/users/?userName={$username}"
			) );
	
			// we got a user record!
			if ( ! empty( $request->UserId ) ) {
				$d2l_id = $request->UserId;
				
				// let's cache the user ID to prevent pinging the API again
				update_user_meta( $user_id, 'bp_d2l_user_id', $d2l_id );
			}
		}

	}

	return apply_filters( 'bp_d2l_get_d2l_user_id', $d2l_id, $user_id, $type );
}

/** TEMPLATE ************************************************************/

/**
 * Get the D2L template directory (bundled with the plugin).
 *
 * @uses apply_filters()
 * @return string
 */
function bp_d2l_get_template_directory() {
	return apply_filters( 'bp_d2l_get_template_directory', constant( 'BP_D2L_DIR' ) . '/templates' );
}

/** TOGGLERS ************************************************************/

/**
 * Should we sync group membership with D2L course enrollment?
 *
 * Note: We only sync when a user joins or leaves a group.  So this isn't
 * a full-fledged sync.  We only sync on those actions only.
 */
function bp_d2l_should_sync_group_membership() {
	return (bool) apply_filters( 'bp_d2l_should_sync_group_membership', false );
}
