<?php
/*
 * @wordpress-plugin
 * Plugin Name:       BP Moderate Group Creation
 * Plugin URI:        https://github.com/telabotanica/bp-moderate-group-creation
 * GitHub Plugin URI: https://github.com/telabotanica/bp-moderate-group-creation
 * Description:       A BuddyPress plugin that allows an admin to moderate groups creation 
 * Version:           0.1 dev
 * Author:            Tela Botanica
 * Author URI:        https://github.com/telabotanica
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bp-moderate-group-creation
 * Domain Path:       /languages
 */

// @TODO check that it doesn't conflict with any existing BP plugin
const GROUPMETA_PUBLISHED_STATE = "published";


add_action('bp_include', 'bp_mgc_init');

function bp_mgc_init() {
	// Admin settings
	//require_once __DIR__ . '/admin-bp-settings.php';
	// Admin management of moderated groups
	require_once __DIR__ . '/admin-manage-moderated-groups.php';
}

/**
 * For each existing group, adds a groupmeta value "1" for key "published", so
 * that those groups stay available as if nothing happened
 */
function bp_mgc_add_groupmeta() {
	global $wpdb;
	$sql = "INSERT INTO " . $wpdb->prefix . "bp_groups_groupmeta"
		. " SELECT NULL, id, '" . GROUPMETA_PUBLISHED_STATE . "', '1'"
		. " FROM " . $wpdb->prefix . "bp_groups;";
	$wpdb->query($sql);
}
register_activation_hook(__FILE__, 'bp_mgc_add_groupmeta');

/**
 * For each existing group, removes the groupmeta tuple for key "published", to
 * revert database to the state it had before activating the plugin
 */
function bp_mgc_remove_groupmeta() {
	global $wpdb;
	$wpdb->delete($wpdb->prefix . 'bp_groups_groupmeta', array(
		'meta_key' => GROUPMETA_PUBLISHED_STATE
	));
}
register_deactivation_hook(__FILE__, 'bp_mgc_remove_groupmeta');


/**
 * Unpublishes the given group, unless the current user is WP admin
 */
function bp_mgc_unpublish_group_after_create($group_id) {
	$published_state = "0";
	if (is_super_admin()) {
		$published_state = "1";
	}
	bp_mgc_group_set_published_state($group_id, $published_state);
	// notify all super-admins
	if (! is_super_admin()) {
		// get_super_admins() doesn't work : doesn't return the user_login
		// => wtf ? method below works
		$args = array(
			'role' => 'Administrator'
		);
		$users = get_users($args);
		foreach ($users as $user) {
			bp_notifications_add_notification(array(
				'user_id'           => $user->ID,
				'item_id'           => $group_id,
				'component_name'    => 'bp-moderate-group-creation',
				'component_action'  => 'bp_mgc_group_awaiting_moderation',
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			));
		}
	}
}
// if not all steps are completed, the group exists anyway; using
// "groups_created_group" instead of "groups_group_create_complete"
add_action('groups_created_group', 'bp_mgc_unpublish_group_after_create');


/**
 * Excludes groups having a groupmeta value "0" for key "published" from the
 * groups list / total count
 */
function bp_mgc_filter_unpublished_groups($array) {
	// It's necessary to leave the groups array unfiltered when performing admin
	// actions (?page) other than listing the groups (?action)
	// @TODO not reliable ? - find a better way to achieve this !!
	if (is_super_admin()
		&& ! empty($_REQUEST['page'])
		&& ! empty($_REQUEST['action'])
	) {
		return $array;
	} else {
		$newarray = array('groups' => array());
		//echo "<pre>"; var_dump($array); echo "</pre>"; exit;
		foreach ($array['groups'] as $k => &$g) {
			$published = groups_get_groupmeta($g->id, GROUPMETA_PUBLISHED_STATE);
			//echo "Statut pour groupe [" . $g->id . "] (" . $g->name . ") : "; var_dump($published); echo "<br/>";
			if ($published === "1") {
				//unset($array['groups'][$k]);
				$newarray['groups'][] = $g;
			}
		}
		$newarray['total'] = count($newarray['groups']);
		return $newarray; 
	}
}; 
add_filter('groups_get_groups', 'bp_mgc_filter_unpublished_groups', 10, 1);


// define the bp_get_total_group_count callback 
function bp_mgc_filter_unpublished_groups_from_total_group_count($groups_get_total_group_count) {
	var_dump($groups_get_total_group_count); exit;
	// make filter magic happen here... 
	return $groups_get_total_group_count; 
}; 

// Doesn't change the count displayed in "WP admin" => "Groups" page...
// add_filter('bp_get_total_group_count', 'bp_mgc_filter_unpublished_groups_from_total_group_count', 10, 1 ); 


/**
 * Prevents groups having a groupmeta value "0" for key "published" from being
 * displayed
 * @TODO modify template instead of doing this, which might break everything !
 */
/*function bp_mgc_filter_unpublished_group($group) { 
	//echo "<pre>"; var_dump($group); echo "</pre>";
	$published = groups_get_groupmeta($group->id, GROUPMETA_PUBLISHED_STATE);
	//echo "Statut pour groupe [" . $group->id . "] (" . $group->name . ") : "; var_dump($published); echo "<br/>";
	//exit;
	if ($published === "0") {
		$group = null;
	}
	return $group; 
};*/ 
// Breaks group creation ! Find another way !
//add_filter( 'groups_get_group', 'bp_mgc_filter_unpublished_group', 10, 1 ); 


/**
 * Sets the "published" state of the given group (published / unpublished)
 * 
 * @param type $group_id
 * @param string $published_state "0" for unpublished, "1" for published
 */
function bp_mgc_group_set_published_state($group_id, $published_state) {
	groups_update_groupmeta($group_id, GROUPMETA_PUBLISHED_STATE, $published_state);
}

/**
 * Registers a new "component" for notifications :
 * 
 * Taken from :
 * https://webdevstudios.com/2015/10/06/buddypress-adding-custom-notifications/
 */
function bp_mgc_custom_filter_notifications_get_registered_components($component_names = array()) {
	// Force $component_names to be an array
	if (! is_array($component_names)) {
		$component_names = array();
	}

	// Add 'custom' component to registered components array
	array_push($component_names, 'bp-moderate-group-creation');

	// Return component's with 'custom' appended
	return $component_names;
}
add_filter('bp_notifications_get_registered_components', 'bp_mgc_custom_filter_notifications_get_registered_components');

/**
 * Registers new notification types :
 *  - to inform the super-admin that a group is awaiting moderation
 *  - to inform the group creator that his group was activated
 *  @TODO  - to inform the group creator that his group was deleted (rejected)
 *			by the super-admin => needs to hook group deletion process / is
 *			there a default notification for group deletion ?
 * 
 * Taken from :
 * https://webdevstudios.com/2015/10/06/buddypress-adding-custom-notifications/
 */
function custom_format_buddypress_notifications($action, $item_id, $secondary_item_id, $total_items, $format = 'string') {
	// New custom notification : a group is awaiting moderation
	if ('bp_mgc_group_awaiting_moderation' === $action) {
		// $item_id is the group id
		$group = groups_get_group(array('group_id' => $item_id));

		$custom_title = __('A group is awaiting moderation', 'bp-moderate-group-creation')
			. ' : '. bp_get_group_name($group);
		$custom_link = bp_get_group_permalink($group);
		$custom_text = '<a href="'. bp_core_get_user_domain($group->creator_id) . '">'
			. bp_core_get_user_displayname($group->creator_id)
			. '</a>  '
			. __('created a new group', 'bp-moderate-group-creation') . ' : '
			. '<a href="' . bp_get_group_permalink($group) . '">'
			. bp_get_group_name($group)
			. '</a>. '
			. __('You may ', 'bp-moderate-group-creation')
			// better way to get admin menu URL ? menu_page_url() doesn't work...
			. '<a href="' . admin_url() . 'admin.php?page=bp-groups-moderation">'
			. __('accept or reject it', 'bp-moderate-group-creation')
			. '</a>';
		;

		// WordPress Toolbar
		if ('string' === $format) {
			$return = apply_filters('bp_mgc_custom_filter', $custom_text, esc_html($custom_text), $custom_link);
		// Deprecated BuddyBar
		} else {
			$return = apply_filters('bp_mgc_custom_filter', array(
				'text' => $custom_text,
				'link' => $custom_link
			), $custom_link, (int) $total_items, $custom_text, $custom_title);
		}

		return $return;
	}
	// New custom notification : a group was activated
	if ('bp_mgc_group_activated' === $action) {
		// $item_id is the group id
		$group = groups_get_group(array('group_id' => $item_id));

		$custom_title = __('Group', 'buddypress') . ' '
			. bp_get_group_name($group) . ' '
			. __('validated', 'buddypress');
		$custom_link  = bp_get_group_permalink($group);
		$custom_text = __('Your group', 'bp-moderate-group-creation') . ' '
			. '<a href="' . bp_get_group_permalink($group) . '">'
			. bp_get_group_name($group) . '</a> '
			. __('was validated by an administrator', 'bp-moderate-group-creation')
		;

		// WordPress Toolbar
		if ( 'string' === $format ) {
			$return = apply_filters('bp_mgc_custom_filter', $custom_text, $custom_text, $custom_link );
		// Deprecated BuddyBar
		} else {
			$return = apply_filters('bp_mgc_custom_filter', array(
				'text' => $custom_text,
				'link' => $custom_link
			), $custom_link, (int) $total_items, $custom_text, $custom_title);
		}

		return $return;
	}
}
add_filter('bp_notifications_get_notifications_for_user', 'custom_format_buddypress_notifications', 10, 5);
