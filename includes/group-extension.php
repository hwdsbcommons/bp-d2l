<?php

class BP_D2L_Group_Extension extends BP_Group_Extension {
	public function __construct() {

		if ( ! method_exists( 'BP_Group_Extension', 'init' ) ) {
			return;
		}

		// should we enable the main 'Course' nav item?
		// we only enable it if a course is enabled and if a group member is logged in
		buddypress()->d2l->enable_nav_item = bp_is_group() && bp_d2l_get_course_id() && ( bp_current_user_can( 'bp_moderate' ) || groups_is_user_member( bp_loggedin_user_id(), bp_get_current_group_id() ) )? true : false;

		parent::init( array(
			'slug' => constant( 'BP_D2L_GROUP_SLUG' ),
		        'name' => __( 'Course', 'bp-d2l' ),
			'enable_nav_item' => buddypress()->d2l->enable_nav_item,
			'screens' => array(
				'edit' => $this->screen_args( array(
					'position' => 1
				) )
			)
		) );

		$this->setup_hooks();
	}

	protected function setup_hooks() {
		// force the 'Course' tab to use the D2L link
		add_action( 'bp_actions', array( $this, 'course_link' ) );

		// sync group membership actions
		// @todo incomplete
		if ( bp_d2l_should_sync_group_membership() ) {
			// enroll user into course
			add_action( 'groups_join_group',    array( $this, 'enroll' ),    10, 2 );
			//add_action( 'groups_accept_invite', array( $this, 'on_invite' ), 10, 2 );

			// unenroll user from course
			add_action( 'groups_leave_group',   array( $this, 'unenroll' ), 10, 2 );
			add_action( 'groups_ban_member',    array( $this, 'unenroll' ), 10, 2 );
			add_action( 'groups_remove_member', array( $this, 'unenroll' ), 10, 2 );

		}

	}

	/** SCREENS ************************************************************/

	/**
	 * The "Admin > Course" screen content.
	 */
	public function edit_screen( $group_id = NULL ) {
		//print_r(buddypress()->d2l->all_courses);
		//$course->Code.': '.$course->Name
		$d2l_course_id = bp_d2l_get_course_id();

		// no d2l course connected
		if ( empty( $d2l_course_id ) ) :

			// get all d2l course IDs that are connected to BP groups
			$d2l_courses_connected = self::get_bp_d2l_course_ids();
	?>

            <select name="d2l_course">
                <option value=""><?php _e( '---Select course---', 'bp-d2l' ); ?></option>

		<?php
			foreach ( buddypress()->d2l->all_courses as $course ) {
				// if course is already connected to a group, do not list this course
				if ( isset( $d2l_courses_connected[$course->Identifier] ) ) {
					continue;
				}

				$selected = $course->Identifier == $d2l_course_id ? ' selected="selected"' : '';

				// delimit the course ID and course name so we can save them separately
				$value = "{$course->Identifier}|{$course->Name}";

				echo '<option value="' . $value .'"' . $selected .'>' . $course->Name . '</option>' . PHP_EOL;
			}
		?>

            </select>

	<?php
		// d2l course already connected
		else :
			$course_link = '<a href="' . bp_d2l_get_course_link() . '" target="_blank">' . groups_get_groupmeta( bp_get_current_group_id(), 'bp_d2l_course_name' ) . '</a>';


			echo '<p>';
			printf( __( 'You have connected the course %s to this group.', 'bp-d2l' ), $course_link );
			echo '</p>';

			echo '<p>';
			_e( 'To resync course membership, click on the button below:', 'bp-d2l' );

			$resync_url = bp_get_groups_action_link( 'admin/' . constant( 'BP_D2L_GROUP_SLUG' ). '/resync-membership/', '', 'bp_d2l_resync' );
			echo '<br /><a class="button" href="' . $resync_url . '">' . __( 'Resync Membership', 'bp-d2l' ) .'</a></p>';

			_e( 'To delete this course connection or to select a new course, click on the button below:', 'bp-d2l' );
			echo '<input type="hidden" name="bp_d2l_delete" value="1" />';

		endif;
	}

	/**
	 * Logic for what happens on the "Admin > Course" screen.
	 *
	 * Need to override parent class' edit screen save to run some custom code
	 * before the content is displayed.
	 */
	public function call_edit_screen_save() {
		if( bp_is_action_variable( 'resync-membership', 1 ) ) {
			check_admin_referer( 'bp_d2l_resync' );

			self::sync_classlist();

			bp_core_add_message( 'Class membership has been updated.' );

			bp_core_redirect( bp_get_groups_action_link( 'admin/' . constant( 'BP_D2L_GROUP_SLUG' ). '/' ) );
		}

		// saving occurs here
		if ( ! empty( $_POST ) ) {
			$this->check_nonce( 'edit' );

			// if we're deleting this course connection, do it and stop!
			if ( ! empty( $_POST['bp_d2l_delete'] ) ) {
				$this->delete_connection();
				bp_core_add_message( 'Connection to the course has been deleted.' );
				bp_core_redirect( bp_get_groups_action_link( '' ) );
				return;
			}

			if ( empty( $_POST['d2l_course'] ) ) {
				return;
			}

			$d2l_course = explode( '|', $_POST['d2l_course'] );

			if ( empty( $d2l_course ) ) {
				return;
			}

			$group_id = bp_get_current_group_id();

			/** SAVE META ***************************************************/

			// save d2l course ID
			groups_update_groupmeta( $group_id, 'bp_d2l_course_id',   (int) $d2l_course[0] );

			// save d2l course name
			groups_update_groupmeta( $group_id, 'bp_d2l_course_name', strip_tags( $d2l_course[1] ) );

			/** MEMBER JOIN *************************************************/

			self::sync_classlist( $d2l_course[0] );

		// non-saving logic here
		} else {
			$d2l_course_id = bp_d2l_get_course_id();

			// we already have a d2l course connected! so stop the rest of this logic
			if( ! empty( $d2l_course_id ) ) {
				// modify the 'Save Changes' button text to 'Delete Connection'
				$this->screens['edit']['submit_text'] = __( 'Delete Connection', 'bp-d2l' );

				return;
			}

			// super admin gets to view all courses
			if ( is_super_admin() ) {
				$org_id = bp_d2l_get_org_id();
				$courseoffering_id = bp_d2l_get_courseoffering_id();

				// get all courses from D2L for super admins
				buddypress()->d2l->all_courses = bp_d2l_request( array(
					'action' => "/orgstructure/{$org_id}/descendants/?ouTypeId={$courseoffering_id}"
				) );

			// group admin only gets to view their courses
			} else {
				buddypress()->d2l->all_courses = self::get_all_d2l_courses_by_wp_user_id( bp_loggedin_user_id() );
			}
		}
	}

	/** ACTIONS ************************************************************/

	/**
	 * Change the 'Course' tab link to directly link to D2L course site.
	 */
	public function course_link() {
		if ( ! buddypress()->d2l->enable_nav_item )
			return;

		$group = groups_get_current_group();

		// global variable hackery!
		buddypress()->bp_options_nav[$group->slug][constant( 'BP_D2L_GROUP_SLUG' )]['link'] = bp_d2l_get_course_link();
	}

	/**
	 * Resyncs classlist with group.
	 *
	 * @todo Check to see if users are no longer enrolled in the course...
	 *
	 * @param int $course_id The D2L course ID to sync
	 * @param int $group_id The group ID to look for a course. This is used if
	 *  the course ID is not passed.
	 */
	protected static function sync_classlist( $course_id = 0, $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			$group_id = bp_get_current_group_id();
		}

		if ( empty( $group_id ) ) {
			return false;
		}

		if ( empty( $course_id ) ) {
			$course_id = bp_d2l_get_course_id( $group_id );
		}

		if ( empty( $course_id ) ) {
			return false;
		}

		// get full D2L classlist
		$classlist = bp_d2l_request( array(
			'action'    => "/{$course_id}/classlist/",
			'component' => 'le'

		) );

		if ( is_array( $classlist ) ) {
			// get array of D2L usernames
			$d2l_class_usernames = wp_list_pluck( $classlist, 'Username' );

			// cross-reference D2L usernames against WP's
			$user_ids = self::get_user_ids_from_d2l_usernames( $d2l_class_usernames );

			// we have some users! so join them to the group
			// this could be intensive...
			if ( ! empty( $user_ids ) ) {
				foreach( $user_ids as $user_id ) {
					groups_join_group( $group_id, $user_id );
				}
			}
		}
	}

	/**
	 * Deletes all D2L meta from a BP Group.
	 *
	 * @todo Should we delete all group members from a group?
	 */
	protected function delete_connection() {
		$course_id = bp_d2l_get_course_id();

		if ( ! empty( $course_id ) ) {
			groups_delete_groupmeta( bp_get_current_group_id(), 'bp_d2l_course_id'   );
			groups_delete_groupmeta( bp_get_current_group_id(), 'bp_d2l_course_name' );
		}
	}

	public function enroll( $group_id, $user_id ) {
		self::sync( $user_id, $group_id );
	}

	public function unenroll( $group_id, $user_id ) {
		self::sync( $user_id, $group_id, 'unenroll' );
	}

	/** HELPERS ************************************************************/

	/**
	 * Get all D2L course IDs that are connected to BP groups.
	 */
	public static function get_bp_d2l_course_ids( $exclude_current_group = true ) {
		global $bp, $wpdb;

		$query = "SELECT meta_value FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'bp_d2l_course_id'";

		// exclude current group so it is selected in the dropdown menu
		if ( $exclude_current_group ) {
			$query .= " AND group_id != " . bp_get_current_group_id();
		}

		$d2l_groups = $wpdb->get_results( $query );

		if ( empty( $d2l_groups ) ) {
			return array();
		}

		return array_flip( wp_list_pluck( $d2l_groups, 'meta_value' ) );
	}

	/**
	 * Cross-reference D2L usernames against WP users database and return
	 * WP user IDs on success.
	 */
	public static function get_user_ids_from_d2l_usernames( $usernames = array() ) {
		global $wpdb;

		if ( empty( $usernames ) )
			return array();

		$username_col = bp_is_username_compatibility_mode() ? 'user_login' : 'user_nicename';

		$in = implode( "','", $usernames );

		// WP_User_Query can't support multiple search terms without manipulating
		// 'pre_user_query' which is why we're using a direct DB query for now
		$query = "SELECT ID FROM {$wpdb->users} WHERE {$username_col} IN ('{$in}')";

		$user_ids = $wpdb->get_results( $query );

		return wp_list_pluck( $user_ids, 'ID' );
	}

	/**
	 * Get all D2L courses for a WordPress user based on WP user ID.
	 *
	 * @param int $user_id The WP user ID
	 * @param array $courses Only used internally by this method for recursion.
	 * @param mixed $bookmark Only used internally by this method for recursion.
	 * @param int $i Only used internally by this method for recursion.
	 *
	 * @return mixed Array of course information on success; boolean false on failure.
	 */
	public static function get_all_d2l_courses_by_wp_user_id( $user_id = 0, $courses = array(), $bookmark = false, $i = 0 ) {
		if ( empty( $user_id ) ) {
			return false;
		}

		$d2l_user_id = bp_d2l_get_d2l_user_id( $user_id );

		// no D2L user ID? stop now!
		if( empty( $d2l_user_id ) ) {
			return false;
		}

		// setup query
		// this only matters when we're using this method recursively
		$query = ! empty( $bookmark ) ? "?bookmark={$bookmark}" : '';

		// ping D2L
		$request = bp_d2l_request( array(
			'action' => "/enrollments/users/{$d2l_user_id}/orgUnits/{$query}"
		) );

		// populate our $courses array
		if ( ! empty( $request->Items ) ) {
			foreach ( $request->Items as $item ) {
				// we only want course offerings
				if ( $item->OrgUnit->Type->Name != 'Course Offering' ) {
					continue;
				}

				$courses[$i] = new stdClass;
				$courses[$i]->Identifier = $item->OrgUnit->Id;
				$courses[$i]->Name       = $item->OrgUnit->Name;

				++$i;
			}
		}

		// if there are more items, must recursively use this method again!
		// @todo This is untested...
		if ( ! empty( $request->PagingInfo->HasMoreItems ) ) {
			$courses = self::get_all_courses_for_user( $user_id, $courses, $request->PagingInfo->Bookmark, $i );
		}

		return $courses;
	}

	/**
	 * Synchronize a WordPress user with a D2L course.
	 */
	public static function sync( $user_id = 0, $group_id = 0, $type = 'enroll' ) {
		$d2l_course_id = bp_d2l_get_course_id( $group_id );

		// we do not have a D2L course connected to this group, so stop now!
		if( empty( $d2l_course_id ) ) {
			return false;
		}

		$d2l_course_role_id = bp_d2l_get_course_role_id( $group_id );
		$d2l_user_id        = bp_d2l_get_d2l_user_id( $user_id );

		// no D2L user ID? stop now!
		if ( ! $d2l_user_id ) {
			return false;
		}

		switch ( $type ) {
			case 'enroll' :
				$input = array(
					'OrgUnitId' => $d2l_course_id,
					'UserId'    => $d2l_user_id,
					'RoleId'    => $d2l_course_role_id
				);

				$args = array(
					'action' => '/enrollments/',
					'method' => 'POST',
					'input'  => json_encode( $input )
				);

				break;

			case 'unenroll' :
				$args = array(
					'action' => "/enrollments/orgUnits/{$d2l_course_id}/users/{$d2l_user_id}",
					'method' => 'DELETE'
				);

				break;
		}

		// ping D2L API
		$request = bp_d2l_request( $args );
	}

	/**
	 * Helper method to be used with the parent init method.
	 */
	protected function screen_args( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'slug' => constant( 'BP_D2L_GROUP_SLUG' ),
			'name' => __( 'Course', 'buddypress' )
		) );

		return $args;
	}

}

bp_register_group_extension( 'BP_D2L_Group_Extension' );