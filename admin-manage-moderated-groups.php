<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-bp-moderated-groups-list-table.php';

function bp_mgc_admin_manage_moderated_groups() {

	// Adds a subpage to the groups management page
	$hook = add_submenu_page(
		'bp-groups',
		__('Groups awaiting moderation', 'bp-moderate-group-creation'),
		__('Groups awaiting moderation', 'bp-moderate-group-creation'),
		'manage_options',
		'bp-groups-moderation',
		'bp_mgc_moderate_groups'
	);

	// Hook into early actions to load custom CSS and our init handler.
	add_action( "load-$hook", 'bp_mgc_moderate_groups_admin_load' );
}
add_action('admin_menu', 'bp_mgc_admin_manage_moderated_groups');



/**
 * Select the appropriate Moderate Groups admin screen, and output it.
 *
 * @since 1.7.0
 */
function bp_mgc_moderate_groups() {
	// Decide whether to load the index or edit screen.
	$doaction = bp_admin_list_table_current_bulk_action();

	// Display the group deletion confirmation screen.
	if ( 'delete' == $doaction && ! empty( $_GET['gid'] ) ) {
		bp_groups_admin_delete();
	} else {
		// Otherwise, display the groups index screen.
		bp_mgc_moderate_groups_index();
	}
}

/**
 * Display the Moderate Groups admin index screen.
 *
 * This screen contains a list of all BuddyPress groups.
 *
 * @since 1.7.0
 *
 * @global BP_Groups_List_Table $bp_groups_list_table Moderate Group screen list table.
 * @global string $plugin_page Currently viewed plugin page.
 */
function bp_mgc_moderate_groups_index() {
	global $plugin_page, $bp_groups_list_table;

	$messages = array();

	// If the user has just made a change to a group, build status messages.
	if ( ! empty( $_REQUEST['deleted'] ) ) {
		$deleted  = ! empty( $_REQUEST['deleted'] ) ? (int) $_REQUEST['deleted'] : 0;

		if ( $deleted > 0 ) {
			$messages[] = sprintf( _n( '%s group has been permanently deleted.', '%s groups have been permanently deleted.', $deleted, 'buddypress' ), number_format_i18n( $deleted ) );
		}
	}

	// If the user has activated a group, build status messages.
	if ( ! empty( $_REQUEST['activated'] ) ) {
		$activated  = ! empty($_REQUEST['activated']) ? (int) $_REQUEST['activated'] : 0;

		if ($activated > 0) {
			$messages[] = sprintf(_n( '%s group has been activated.', '%s groups have been activated.', $activated, 'buddypress'), number_format_i18n($activated));
		}
	}

	// Prepare the group items for display.
	$bp_groups_list_table->prepare_items();

	/**
	 * Fires before the display of messages for the edit form.
	 *
	 * Useful for plugins to modify the messages before display.
	 *
	 * @since 1.7.0
	 *
	 * @param array $messages Array of messages to be displayed.
	 */
	do_action( 'bp_groups_admin_index', $messages ); ?>

	<div class="wrap">
		<h1>
			<?php _e( 'Groups awaiting moderation', 'bp-moderate-group-creation' ); ?>

			<?php if ( !empty( $_REQUEST['s'] ) ) : ?>
				<span class="subtitle"><?php printf( __( 'Search results for &#8220;%s&#8221;', 'buddypress' ), wp_html_excerpt( esc_html( stripslashes( $_REQUEST['s'] ) ), 50 ) ); ?></span>
			<?php endif; ?>
		</h1>

		<?php // If the user has just made a change to an group, display the status messages. ?>
		<?php if ( !empty( $messages ) ) : ?>
			<div id="moderated" class="<?php echo ( ! empty( $_REQUEST['error'] ) ) ? 'error' : 'updated'; ?>"><p><?php echo implode( "<br/>\n", $messages ); ?></p></div>
		<?php endif; ?>

		<?php // Display each group on its own row. ?>
		<?php $bp_groups_list_table->views(); ?>

		<form id="bp-groups-form" action="" method="get">
			<?php $bp_groups_list_table->search_box( __( 'Search groups awaiting moderation', 'bp-moderate-group-creation' ), 'bp-groups' ); ?>
			<input type="hidden" name="page" value="<?php echo esc_attr( $plugin_page ); ?>" />
			<?php $bp_groups_list_table->display(); ?>
		</form>

	</div>

<?php
}


function bp_mgc_admin_notice_moderation_success() {
}

/**
 * Set up the Moderate Groups admin page.
 *
 * Loaded before the page is rendered, this function does all initial setup,
 * including: processing form requests, registering contextual help, and
 * setting up screen options.
 *
 * @since 1.7.0
 *
 * @global BP_Groups_List_Table $bp_groups_list_table Groups screen list table.
 */
function bp_mgc_moderate_groups_admin_load() {
	global $bp_groups_list_table;

	// Build redirection URL.
	$redirect_to = remove_query_arg( array( 'action', 'action2', 'gid', 'deleted', 'error', 'updated', 'success_new', 'error_new', 'success_modified', 'error_modified' ), $_SERVER['REQUEST_URI'] );

	$doaction   = bp_admin_list_table_current_bulk_action();
	$min        = bp_core_get_minified_asset_suffix();
	//var_dump($doaction); exit;

	/**
	 * Fires at top of groups admin page.
	 *
	 * @since 1.7.0
	 *
	 * @param string $doaction Current $_GET action being performed in admin screen.
	 */
	do_action( 'bp_groups_admin_load', $doaction );

	// Edit screen.
	if ( 'do_delete' == $doaction && ! empty( $_GET['gid'] ) ) {

		check_admin_referer( 'bp-groups-delete' );

		$group_ids = wp_parse_id_list( $_GET['gid'] );

		$count = 0;
		foreach ( $group_ids as $group_id ) {
			if ( groups_delete_group( $group_id ) ) {
				$count++;
			}
		}

		$redirect_to = add_query_arg( 'deleted', $count, $redirect_to );

		bp_core_redirect( $redirect_to );

	} elseif ('activate' == $doaction && ! empty($_GET['gid'])) {

		$count = 0;
		// Activate the group(s) : set the published state to "1"
		if (! empty($_GET['gid'])) {
			$group_ids = $_GET['gid'];
			// homogeneization
			if (!is_array($group_ids)) {
				$group_ids = array($group_ids);
			}
			//var_dump($group_ids);

			foreach($group_ids as $group_id) {
				//echo "<br>Validation du groupe $group_id !";
				bp_mgc_group_set_published_state($group_id, "1");
				// send notification to group creator
				$group = groups_get_group(array('group_id' => $group_id));
				bp_notifications_add_notification(array(
					'user_id'           => $group->creator_id,
					'item_id'           => $group_id,
					'component_name'    => 'bp-moderate-group-creation',
					'component_action'  => 'bp_mgc_group_activated',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				));
				$count++;
			}
		}

		$redirect_to = add_query_arg('activated', $count, $redirect_to);
		bp_core_redirect( $redirect_to );

	} elseif ( 'edit' == $doaction && ! empty( $_GET['gid'] ) ) {
		// Columns screen option.
		add_screen_option( 'layout_columns', array( 'default' => 2, 'max' => 2, ) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-group-edit-overview',
			'title'   => __( 'Overview', 'buddypress' ),
			'content' =>
				'<p>' . __( 'This page is a convenient way to edit the details associated with one of your groups.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'The Name and Description box is fixed in place, but you can reposition all the other boxes using drag and drop, and can minimize or expand them by clicking the title bar of each box. Use the Screen Options tab to hide or unhide, or to choose a 1- or 2-column layout for this screen.', 'buddypress' ) . '</p>'
		) );

		// Help panel - sidebar links.
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'buddypress' ) . '</strong></p>' .
			'<p><a href="https://buddypress.org/support">' . __( 'Support Forums', 'buddypress' ) . '</a></p>'
		);

		// Register metaboxes for the edit screen.
		add_meta_box( 'submitdiv', _x( 'Save', 'group admin edit screen', 'buddypress' ), 'bp_groups_admin_edit_metabox_status', get_current_screen()->id, 'side', 'high' );
		add_meta_box( 'bp_group_settings', _x( 'Settings', 'group admin edit screen', 'buddypress' ), 'bp_groups_admin_edit_metabox_settings', get_current_screen()->id, 'side', 'core' );
		add_meta_box( 'bp_group_add_members', _x( 'Add New Members', 'group admin edit screen', 'buddypress' ), 'bp_groups_admin_edit_metabox_add_new_members', get_current_screen()->id, 'normal', 'core' );
		add_meta_box( 'bp_group_members', _x( 'Manage Members', 'group admin edit screen', 'buddypress' ), 'bp_groups_admin_edit_metabox_members', get_current_screen()->id, 'normal', 'core' );

		// Group Type metabox. Only added if group types have been registered.
		$group_types = bp_groups_get_group_types();
		if ( ! empty( $group_types ) ) {
			add_meta_box(
				'bp_groups_admin_group_type',
				_x( 'Group Type', 'groups admin edit screen', 'buddypress' ),
				'bp_groups_admin_edit_metabox_group_type',
				get_current_screen()->id,
				'side',
				'core'
			);
		}

		/**
		 * Fires after the registration of all of the default group meta boxes.
		 *
		 * @since 1.7.0
		 */
		do_action( 'bp_groups_admin_meta_boxes' );

		// Enqueue JavaScript files.
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'dashboard' );

	// Index screen.
	} else {
		// Create the Groups screen list table.
		$bp_groups_list_table = new BP_Moderated_Groups_List_Table();

		// The per_page screen option.
		add_screen_option( 'per_page', array( 'label' => _x( 'Groups', 'Groups per page (screen options)', 'buddypress' )) );

		// Help panel - overview text.
		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-groups-overview',
			'title'   => __( 'Overview', 'buddypress' ),
			'content' =>
				'<p>' . __( 'You can manage groups much like you can manage comments and other content. This screen is customizable in the same ways as other management screens, and you can act on groups by using the on-hover action links or the Bulk Actions.', 'buddypress' ) . '</p>',
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-groups-overview-actions',
			'title'   => __( 'Group Actions', 'buddypress' ),
			'content' =>
				'<p>' . __( 'Clicking "Visit" will take you to the group&#8217;s public page. Use this link to see what the group looks like on the front end of your site.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'Clicking "Edit" will take you to a Dashboard panel where you can manage various details about the group, such as its name and description, its members, and other settings.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'If you click "Delete" under a specific group, or select a number of groups and then choose Delete from the Bulk Actions menu, you will be led to a page where you&#8217;ll be asked to confirm the permanent deletion of the group(s).', 'buddypress' ) . '</p>',
		) );

		// Help panel - sidebar links.
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'buddypress' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://buddypress.org/support/">Support Forums</a>', 'buddypress' ) . '</p>'
		);

		// Add accessible hidden heading and text for Groups screen pagination.
		if ( bp_get_major_wp_version() >= 4.4 ) {
			get_current_screen()->set_screen_reader_content( array(
				/* translators: accessibility text */
				'heading_pagination' => __( 'Groups list navigation', 'buddypress' ),
			) );
		}
	}

	$bp = buddypress();

	// Enqueue CSS and JavaScript.
	wp_enqueue_script( 'bp_groups_admin_js', $bp->plugin_url . "bp-groups/admin/js/admin{$min}.js", array( 'jquery', 'wp-ajax-response', 'jquery-ui-autocomplete' ), bp_get_version(), true );
	wp_localize_script( 'bp_groups_admin_js', 'BP_Group_Admin', array(
		'add_member_placeholder' => __( 'Start typing a username to add a new member.', 'buddypress' ),
		'warn_on_leave'          => __( 'If you leave this page, you will lose any unsaved changes you have made to the group.', 'buddypress' ),
	) );
	wp_enqueue_style( 'bp_groups_admin_css', $bp->plugin_url . "bp-groups/admin/css/admin{$min}.css", array(), bp_get_version() );

	wp_style_add_data( 'bp_groups_admin_css', 'rtl', true );
	if ( $min ) {
		wp_style_add_data( 'bp_groups_admin_css', 'suffix', $min );
	}


	if ( $doaction && 'save' == $doaction ) {
		// Get group ID.
		$group_id = isset( $_REQUEST['gid'] ) ? (int) $_REQUEST['gid'] : '';

		$redirect_to = add_query_arg( array(
			'gid'    => (int) $group_id,
			'action' => 'edit'
		), $redirect_to );

		// Check this is a valid form submission.
		check_admin_referer( 'edit-group_' . $group_id );

		// Get the group from the database.
		$group = groups_get_group( $group_id );

		// If the group doesn't exist, just redirect back to the index.
		if ( empty( $group->slug ) ) {
			wp_redirect( $redirect_to );
			exit;
		}

		// Check the form for the updated properties.
		// Store errors.
		$error = 0;
		$success_new = $error_new = $success_modified = $error_modified = array();

		// Group name and description are handled with
		// groups_edit_base_group_details().
		if ( !groups_edit_base_group_details( $group_id, $_POST['bp-groups-name'], $_POST['bp-groups-description'], 0 ) ) {
			$error = $group_id;

			// Using negative integers for different error messages... eek!
			if ( empty( $_POST['bp-groups-name'] ) && empty( $_POST['bp-groups-description'] ) ) {
				$error = -3;
			} elseif ( empty( $_POST['bp-groups-name'] ) ) {
				$error = -1;
			} elseif ( empty( $_POST['bp-groups-description'] ) ) {
				$error = -2;
			}
		}

		// Enable discussion forum.
		$enable_forum   = ( isset( $_POST['group-show-forum'] ) ) ? 1 : 0;

		/**
		 * Filters the allowed status values for the group.
		 *
		 * @since 1.0.2
		 *
		 * @param array $value Array of allowed group statuses.
		 */
		$allowed_status = apply_filters( 'groups_allowed_status', array( 'public', 'private', 'hidden' ) );
		$status         = ( in_array( $_POST['group-status'], (array) $allowed_status ) ) ? $_POST['group-status'] : 'public';

		/**
		 * Filters the allowed invite status values for the group.
		 *
		 * @since 1.5.0
		 *
		 * @param array $value Array of allowed invite statuses.
		 */
		$allowed_invite_status = apply_filters( 'groups_allowed_invite_status', array( 'members', 'mods', 'admins' ) );
		$invite_status	       = in_array( $_POST['group-invite-status'], (array) $allowed_invite_status ) ? $_POST['group-invite-status'] : 'members';

		if ( !groups_edit_group_settings( $group_id, $enable_forum, $status, $invite_status ) ) {
			$error = $group_id;
		}

		// Process new members.
		$user_names = array();

		if ( ! empty( $_POST['bp-groups-new-members'] ) ) {
			$user_names = array_merge( $user_names, explode( ',', $_POST['bp-groups-new-members'] ) );
		}

		if ( ! empty( $user_names ) ) {

			foreach( array_values( $user_names ) as $user_name ) {
				$un = trim( $user_name );

				// Make sure the user exists before attempting
				// to add to the group.
				$user = get_user_by( 'slug', $un );

				if ( empty( $user ) ) {
					$error_new[] = $un;
				} else {
					if ( ! groups_join_group( $group_id, $user->ID ) ) {
						$error_new[]   = $un;
					} else {
						$success_new[] = $un;
					}
				}
			}
		}

		// Process member role changes.
		if ( ! empty( $_POST['bp-groups-role'] ) && ! empty( $_POST['bp-groups-existing-role'] ) ) {

			// Before processing anything, make sure you're not
			// attempting to remove the all user admins.
			$admin_count = 0;
			foreach ( (array) $_POST['bp-groups-role'] as $new_role ) {
				if ( 'admin' == $new_role ) {
					$admin_count++;
					break;
				}
			}

			if ( ! $admin_count ) {

				$redirect_to = add_query_arg( 'no_admins', 1, $redirect_to );
				$error = $group_id;

			} else {

				// Process only those users who have had their roles changed.
				foreach ( (array) $_POST['bp-groups-role'] as $user_id => $new_role ) {
					$user_id = (int) $user_id;

					$existing_role = isset( $_POST['bp-groups-existing-role'][$user_id] ) ? $_POST['bp-groups-existing-role'][$user_id] : '';

					if ( $existing_role != $new_role ) {
						$result = false;

						switch ( $new_role ) {
							case 'mod' :
								// Admin to mod is a demotion. Demote to
								// member, then fall through.
								if ( 'admin' == $existing_role ) {
									groups_demote_member( $user_id, $group_id );
								}

							case 'admin' :
								// If the user was banned, we must
								// unban first.
								if ( 'banned' == $existing_role ) {
									groups_unban_member( $user_id, $group_id );
								}

								// At this point, each existing_role
								// is a member, so promote.
								$result = groups_promote_member( $user_id, $group_id, $new_role );

								break;

							case 'member' :

								if ( 'admin' == $existing_role || 'mod' == $existing_role ) {
									$result = groups_demote_member( $user_id, $group_id );
								} elseif ( 'banned' == $existing_role ) {
									$result = groups_unban_member( $user_id, $group_id );
								}

								break;

							case 'banned' :

								$result = groups_ban_member( $user_id, $group_id );

								break;

							case 'remove' :

								$result = groups_remove_member( $user_id, $group_id );

								break;
						}

						// Store the success or failure.
						if ( $result ) {
							$success_modified[] = $user_id;
						} else {
							$error_modified[]   = $user_id;
						}
					}
				}
			}
		}

		/**
		 * Fires before redirect so plugins can do something first on save action.
		 *
		 * @since 1.6.0
		 *
		 * @param int $group_id ID of the group being edited.
		 */
		do_action( 'bp_group_admin_edit_after', $group_id );

		// Create the redirect URL.
		if ( $error ) {
			// This means there was an error updating group details.
			$redirect_to = add_query_arg( 'error', (int) $error, $redirect_to );
		} else {
			// Group details were update successfully.
			$redirect_to = add_query_arg( 'updated', 1, $redirect_to );
		}

		if ( !empty( $success_new ) ) {
			$success_new = implode( ',', array_filter( $success_new, 'urlencode' ) );
			$redirect_to = add_query_arg( 'success_new', $success_new, $redirect_to );
		}

		if ( !empty( $error_new ) ) {
			$error_new = implode( ',', array_filter( $error_new, 'urlencode' ) );
			$redirect_to = add_query_arg( 'error_new', $error_new, $redirect_to );
		}

		if ( !empty( $success_modified ) ) {
			$success_modified = implode( ',', array_filter( $success_modified, 'urlencode' ) );
			$redirect_to = add_query_arg( 'success_modified', $success_modified, $redirect_to );
		}

		if ( !empty( $error_modified ) ) {
			$error_modified = implode( ',', array_filter( $error_modified, 'urlencode' ) );
			$redirect_to = add_query_arg( 'error_modified', $error_modified, $redirect_to );
		}

		/**
		 * Filters the URL to redirect to after successfully editing a group.
		 *
		 * @since 1.7.0
		 *
		 * @param string $redirect_to URL to redirect user to.
		 */
		wp_redirect( apply_filters( 'bp_group_admin_edit_redirect', $redirect_to ) );
		exit;


	// If a referrer and a nonce is supplied, but no action, redirect back.
	} elseif ( ! empty( $_GET['_wp_http_referer'] ) ) {
		wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), stripslashes( $_SERVER['REQUEST_URI'] ) ) );
		exit;
	}
}