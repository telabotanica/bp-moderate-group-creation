<?php

// register the location of the plugin templates
function bp_mgc_register_template_location() {
    return __DIR__ . '/templates/';
}

// replace home.php with the template overload from the plugin
function bp_mgc_maybe_replace_template($templates, $slug, $name) {
	if( 'groups/single/home' != $slug ) {
		return $templates;
	}
	// if you want to make your theme compatible with this plugin, create a
	// template named "group-awaiting-moderation.php" in "groups/single/"
	return array('groups/single/group-awaiting-moderation.php');
}

function bp_mgc_overload_home_template() {
	// register custom template location
	if(function_exists('bp_register_template_stack')) {
		bp_register_template_stack('bp_mgc_register_template_location');
	}
	// if trying to view an unpublished group page, overload the template
	if (bp_is_group()) {
		$group_id = bp_get_current_group_id();
		$published_state = groups_get_groupmeta($group_id, GROUPMETA_PUBLISHED_STATE);
		if (! is_super_admin() && ($published_state !== "1")) {
			add_filter('bp_get_template_part', 'bp_mgc_maybe_replace_template', 10, 3);
		}
	}
}
add_action('bp_init', 'bp_mgc_overload_home_template');