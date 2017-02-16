# bp-moderate-group-creation
A BuddyPress plugin that allows an admin to moderate groups creation. 
Once activated, every new group will remain in a unpublished state until a super-admin activates it.

## Features

 * newly created groups are moderated
 * super-admins receive a notification upon group creation
 * new page in WP admin dashboard to manage groups awaiting moderation
 * group creators receive a notification upon group activation
 * groups awaiting moderation are not shown in groups list
 * when accessed, groups awaiting moderation show a message "awaiting moderation"
 * super-admins may still create groups without moderation
 
## Acknowledgments
This is an early stage plugin, our first one. Most of the mechanisms used are inspired by existing plugins but we can't guarantee everything is done the way it should. If you notice anything wrong, feel free to open an issue to discuss, or open a pull request :)

Some minor issues are not yet resolved (see _issues_)

## FAQ
### some groups do not appear, neither in "groups list" nor in "groups awaiting moderation"
Maybe you imported some groups into the database after the plugin was activated ? Deactivate and reactivate the plugin to generate appropriate metadata

## Licence
MIT
