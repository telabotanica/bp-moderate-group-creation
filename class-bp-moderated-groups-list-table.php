<?php

/**
 * Naive extension of BP_Groups_List_Table; some code might be useless
 * @TODO review it properly
 */
class BP_Moderated_Groups_List_Table extends BP_Groups_List_Table {

	public function __construct() {
		parent::__construct();

		// remove parent's group type change bulk action
		remove_action( 'bp_groups_list_table_after_bulk_actions', array($this, 'add_group_type_bulk_change_select'));

		// remove group filter hiding the unpublished groups
		remove_filter('groups_get_groups', 'bp_mgc_filter_unpublished_groups');

		// add "author" column
		add_filter( 'bp_groups_admin_get_group_custom_column', array($this, 'column_content_author'), 10, 3 );
	}

	/**
	 * Set up items for display in the list table.
	 *
	 * Handles filtering of data, sorting, pagination, and any other data
	 * manipulation required prior to rendering.
	 *
	 * @since 1.7.0
	 */
	public function prepare_items() {
		
		$this->_column_headers = $this->get_column_info();

		// optional search term
		$search = null;
		if (! empty($_REQUEST['s'])) {
			$search = $_REQUEST['s'];
		}

		$per_page     = $this->get_items_per_page('messages_per_page', 20);
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count($search);

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		));

		$this->items = self::get_moderated_groups($per_page, $current_page, $search);
	}

	public static function record_count($search=null) {
		global $wpdb;

		$sql = "SELECT count(*)"
			. " FROM {$wpdb->prefix}bp_groups g"
			. " JOIN test_bp_groups_groupmeta gm_published ON ( g.id = gm_published.group_id)"
			. " WHERE gm_published.meta_key = 'published'"
			. " AND gm_published.meta_value = 0";

		// search
		if ($search !== null) {
			$search_like = esc_sql('%' . $search . '%');
			$clauses = array(
				"g.description LIKE '$search_like'",
				"g.name LIKE '$search_like'"
			);
			$sql .= " AND (" . implode(' OR ', $clauses) . ")";
		}

		return $wpdb->get_var($sql);
	}

	public static function get_moderated_groups($per_page = 20, $page_number = 1, $search=null) {
		global $wpdb;

		$sql = "SELECT g.*"
			. " FROM {$wpdb->prefix}bp_groups g"
			. " JOIN test_bp_groups_groupmeta gm_published ON ( g.id = gm_published.group_id)"
			. " WHERE gm_published.meta_key = 'published'"
			. " AND gm_published.meta_value = 0";

		// search
		if ($search !== null) {
			$search_like = esc_sql('%' . $search . '%');
			$clauses = array(
				"g.description LIKE '$search_like'",
				"g.name LIKE '$search_like'"
			);
			$sql .= " AND (" . implode(' OR ', $clauses) . ")";
		}
		// order
		if (! empty($_REQUEST['orderby'])) {
			$order_field = esc_sql($_REQUEST['orderby']);
			$sql .= ' ORDER BY ' . $order_field;
			$sql .= ! empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
		}
		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ($page_number - 1) * $per_page;

		$result = $wpdb->get_results($sql, 'ARRAY_A');

		return $result;
	}

	/**
	 * Remove filtering by group types
	 */
	public function get_views() {
	}

	/**
	 * Get bulk actions for single group row.
	 *
	 * @since 1.7.0
	 *
	 * @return array Key/value pairs for the bulk actions dropdown.
	 */
	public function get_bulk_actions() {

		/**
		 * Filters the list of bulk actions to display on a single group row.
		 *
		 * @since 1.7.0
		 *
		 * @param array $value Array of bulk actions to display.
		 */
		return apply_filters( 'bp_groups_list_table_get_bulk_actions', array(
			'activate' => __( 'Activate', 'buddypress' ),
			'delete' => __( 'Delete', 'buddypress' )
		) );
	}

	/**
	 * Get the table column titles.
	 *
	 * @since 1.7.0
	 *
	 * @see WP_List_Table::single_row_columns()
	 *
	 * @return array Array of column titles.
	 */
	public function get_columns() {

		/**
		 * Filters the titles for the columns for the groups list table.
		 *
		 * @since 2.0.0
		 *
		 * @param array $value Array of slugs and titles for the columns.
		 */
		return apply_filters( 'bp_groups_list_table_get_columns', array(
			'cb'          => '<input name type="checkbox" />',
			'name'     => _x( 'Name', 'Groups admin Group Name column header',               'buddypress' ),
			'description' => _x( 'Description', 'Groups admin Group Description column header', 'buddypress' ),
			'created_by' => _x( 'Created By', 'Groups admin Created By column header', 'bp-moderate-group-creation' ),
			'status'      => _x( 'Status', 'Groups admin Privacy Status column header',         'buddypress' ),
			'date_created' => _x( 'Creation Date', 'Groups admin Creation Date column header',       'bp-moderate-group-creation' )
		) );
	}

	/**
	 * Get the column names for sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'gid'         => array( 'gid', false ),
			'name'     => array( 'name', false ),
			'date_created' => array( 'date_created', false ),
		);
	}

	public function column_date_created( $item = array() ) {
		return $item['date_created'];
	}

	/**
	 * Name column, and "quick admin" rollover actions.
	 *
	 * Called "name" in the CSS so we can re-use some WP core CSS.
	 *
	 * @since 1.7.0
	 *
	 * @see WP_List_Table::single_row_columns()
	 *
	 * @param array $item A singular item (one full row).
	 */
	public function column_name( $item = array() ) {

		// Preorder items: Activate (| Edit) | Delete | View.
		$actions = array(
			'activate'   => '',
			//'edit'   => '',
			'delete' => '',
			'view'   => '',
		);

		// We need the group object for some BP functions.
		$item_obj = (object) $item;

		// Build actions URLs.
		$base_url   = bp_get_admin_url( 'admin.php?page=bp-groups-moderation&amp;gid=' . $item['id'] );
		$groups_admin_base_url   = bp_get_admin_url( 'admin.php?page=bp-groups&amp;gid=' . $item['id'] );
		$activate_url = wp_nonce_url( $base_url . "&amp;action=activate", 'bp-groups-activate' );
		$delete_url = wp_nonce_url( $base_url . "&amp;action=delete", 'bp-groups-delete' );
		$edit_url   = $groups_admin_base_url . '&amp;action=edit';
		$view_url   = bp_get_group_permalink( $item_obj );

		/**
		 * Filters the group name for a group's column content.
		 *
		 * @since 1.7.0
		 *
		 * @param string $value Name of the group being rendered.
		 * @param array  $item  Array for the current group item.
		 */
		$group_name = apply_filters_ref_array( 'bp_get_group_name', array( $item['name'] ), $item );

		// Rollover actions.
		// Activate.
		$actions['activate']   = sprintf( '<a href="%s">%s</a>', esc_url( $activate_url   ), __( 'Activate',   'buddypress' ) );

		// Edit.
		//$actions['edit']   = sprintf( '<a href="%s">%s</a>', esc_url( $edit_url   ), __( 'Edit',   'buddypress' ) );

		// Delete.
		$actions['delete'] = sprintf( '<a href="%s">%s</a>', esc_url( $delete_url ), __( 'Delete', 'buddypress' ) );

		// Visit.
		$actions['view']   = sprintf( '<a href="%s">%s</a>', esc_url( $view_url   ), __( 'View',   'buddypress' ) );

		/**
		 * Filters the actions that will be shown for the column content.
		 *
		 * @since 1.7.0
		 *
		 * @param array $value Array of actions to be displayed for the column content.
		 * @param array $item  The current group item in the loop.
		 */
		$actions = apply_filters( 'bp_groups_admin_comment_row_actions', array_filter( $actions ), $item );

		// Get group name and avatar.
		$avatar = '';

		if ( buddypress()->avatar->show_avatars ) {
			$avatar  = bp_core_fetch_avatar( array(
				'item_id'    => $item['id'],
				'object'     => 'group',
				'type'       => 'thumb',
				'avatar_dir' => 'group-avatars',
				'alt'        => sprintf( __( 'Group logo of %s', 'buddypress' ), $group_name ),
				'width'      => '32',
				'height'     => '32',
				'title'      => $group_name
			) );
		}

		$content = sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $edit_url ), $group_name );

		echo $avatar . ' ' . $content . ' ' . $this->row_actions( $actions );
	}

	/**
	 * Markup for the Author column.
	 *
	 * @since 2.7.0
	 *
	 * @param string $value       Empty string.
	 * @param string $column_name Name of the column being rendered.
	 * @param array  $item        The current group item in the loop.
	 */
	public function column_content_author( $retval = '', $column_name, $item ) {
		if ( 'created_by' !== $column_name ) {
			return $retval;
		}

		$author_id = $item['creator_id'];
		$author = new WP_User($author_id);

		echo sprintf(
			'<strong><a href="%1$s" class="edit">%2$s</a></strong><br>(%3$s)<br/>',
			bp_core_get_user_domain($author_id),
			bp_core_get_user_displayname($author_id),
			$author->user_login
		);

		/**
		 * Filters the markup for the Author column.
		 *
		 * @since 2.7.0
		 *
		 * @param string $retval Markup for the Author column.
		 * @parma array  $item   The current group item in the loop.
		 */
		echo apply_filters_ref_array( 'bp_groups_admin_get_author_column', array( $retval, $item ) );
	}
}