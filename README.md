# bp-moderate-group-creation
A BuddyPress plugin that allows an admin to moderate groups creation. 
Once activated, every new group will remain in a unpublished state until a super-admin activates it.

## features

 * newly created groups are moderated
 * super-admins receive a notification upon group creation
 * new page in WP admin dashboard to manage groups awaiting moderation
 * group creators receive a notification upon group activation
 * groups awaiting moderation are not shown in groups list
 * when accessed, groups awaiting moderation show a message "awaiting moderation"
 * super-admins may still create groups without moderation
 
## things to know about
This is an early stage plugin, our first one. Most of the mechanisms used are inspired by existing plugins but we can't guarantee evrything is done the way it should.

Some minor issues are not yet resolved (see _issues_)

## FAQ
### some groups do not appear, neither in "groups list" nor in "groups awaiting moderation"
Maybe you imported some groups into the database after the plugin was activated ? Deactivate and reactivate the plugin to generate appropriate metadata
