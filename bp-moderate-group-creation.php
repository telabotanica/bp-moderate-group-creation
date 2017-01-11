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

// Admin settings
//require_once __DIR__ . '/admin.php';


// @TODO check that it doesn't conflict with any existing BP plugin
const GROUPMETA_PUBLISHED_STATE = "published";


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
}
// if not all steps are completed, the group exists anyway; using
// "groups_created_group" instead of "groups_group_create_complete"
add_action('groups_created_group', 'bp_mgc_unpublish_group_after_create');


/**
 * Excludes groups having a groupmeta value "0" for key "published" from the
 * groups list / total count
 */
function bp_mgc_filter_unpublished_groups($array) { 
	$newarray = array();
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
function bp_mgc_filter_unpublished_group($group) { 
	//echo "<pre>"; var_dump($group); echo "</pre>";
	$published = groups_get_groupmeta($group->id, GROUPMETA_PUBLISHED_STATE);
	//echo "Statut pour groupe [" . $group->id . "] (" . $group->name . ") : "; var_dump($published); echo "<br/>";
	//exit;
	if ($published === "0") {
		$group = null;
	}
	return $group; 
}; 
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