Desire2Learn BuddyPress Integration
===================================

This plugin connects a Desire2Learn course to a BuddyPress group.

**IMPORTANT:** This plugin only works if your Desire2Learn usernames are the same as your WordPress usernames.

A few additional steps are also necessary in order to get this up and running.

The following code snippet needs to be added to your [/wp-content/plugins/bp-custom.php](http://codex.buddypress.org/developer/customizing/bp-custom-php/) file.  Follow the steps listed in the code snippet to input the correct D2L API keys.

```
/** D2L ************************************************************/

// (1) Generate your D2L app credentials
// @see http://docs.valence.desire2learn.com/basic/firstlist.html#acquire-an-app-id-key-pair
define( 'BP_D2L_APPID',  'your_app_id' );
define( 'BP_D2L_APPKEY', 'your_app_key' );
define( 'BP_D2L_HOST',   'your_D2L_URL' );

// (2) Create a new user in D2L
//
// Login to your D2L install and create a new user.  This user will be used to make API calls.
// @see http://docs.valence.desire2learn.com/basic/firstlist.html#create-an-lms-test-account
//
// Follow the last point in that article.  Specifically, the information regarding creating a
// LMS service account.  It should have enough privileges to query information about the
// organization and to manage roles and role permissions.

// (3) Generate the D2L user credentials
//
// Once you have created this user, use the Valence API test tool:
// https://apitesttool.desire2learnvalence.com/
//
// 1. Input in the app credentials from step 1 and hit "Authenticate"
// 2. This will take you to your D2L install.  Login with the user you created in step 2.
// 3. This should redirect you back to the Valence API test tool and the "User ID" and "User Key"
//    fields should be populated.  These are your user credentials.  Enter them below:
define( 'BP_D2L_USERID',  'your_userid' );
define( 'BP_D2L_USERKEY', 'your_userkey' );

// (4) Get some important IDs
//
// You should still be on the Valence API test site.
//
// To get the organization ID, use the following API call on the page:
// 	1. In the "Request" field, type in "/d2l/api/lp/1.0/organization/info" without quotation marks
//	2. Select the "GET" radio field
//	3. Hit "Submit".  You should get an "Identifier" value.  Copy this value into the following:
//
define( 'BP_D2L_ORG_ID', THE_VALUE_FROM_THE_IDENTIFIER_VALUE );

// To get the course offering ID, use the following API call on the page:
// 	GET /d2l/api/lp/1.0/outypes/
//
// This should default to 3, but might be different for your D2L install
define( 'BP_D2L_COURSEOFFERING_ID', 3 );
```

Once this is done, navigate to a group's admin page:
`example.com/groups/TEST-GROUP/admin/edit-details`

There should be a tab labelled **Course**.  Click on this tab.  You should then be able to select a D2L course to connect to a BuddyPress group.

Membership of the group does not sync automatically. If users are added to the course after the initial connection, you'll need to hit the re-sync button to sync new members. When users leave a course, they need to be manually removed from the BP Group. 

Used in tandem with the BP GroupBlog plug, this also provides a way to create a blog for your course. 
