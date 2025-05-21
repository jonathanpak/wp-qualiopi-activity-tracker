=== User Activity & Session Logger ===
Contributors: yourname
Tags: activity log, user sessions, fluentcrm, learndash, fluent forms
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Comprehensive user activity and session tracking with FluentCRM, LearnDash, and Fluent Forms integration.

== Description ==

User Activity & Session Logger provides detailed tracking of user sessions and activities on your WordPress site, with specialized integration for FluentCRM, LearnDash, and Fluent Forms.

= Key Features =

* **User Session Logging**: Track when users log in and out, their IP addresses, and session durations.
* **Page Visit Tracking**: Record which pages logged-in users visit, including post titles and URLs.
* **Fluent Forms Integration**: Log when users complete form submissions.
* **LearnDash Integration**: Track course, lesson, topic, and quiz completions.
* **FluentCRM Integration**: View all user activity directly in the contact profile with a dedicated tab.
* **Shortcodes**: Display user activity data on your site with customizable shortcodes.
* **Admin Interface**: View and analyze user activity data through an intuitive admin interface.

= Shortcodes =

The plugin provides two shortcodes for displaying user activity:

* `[user_activity_overview]`: Displays a table with summary information for all users.
* `[user_activity_detail user_id="X"]` or `[user_activity_detail user_email="email@example.com"]`: Shows detailed activity for a specific user.

= Integration Requirements =

* **FluentCRM**: Required for the CRM contact profile integration.
* **LearnDash**: Required for tracking learning activity.
* **Fluent Forms**: Required for tracking form submissions.

The plugin will still function without these plugins, but the related tracking features will be inactive.

== Installation ==

1. Upload the `user-activity-logger` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under 'User Activity' in the admin menu

== Frequently Asked Questions ==

= Will this plugin slow down my site? =

No, the plugin is designed to be lightweight and optimized for performance. Logging happens asynchronously and does not interfere with page loading.

= How long is activity data stored? =

By default, activity data is stored for 90 days, but this can be customized in the plugin settings.

= Can I track anonymous users? =

No, the plugin only tracks activity for logged-in WordPress users.

= Does this plugin work with multisite? =

Yes, the plugin is compatible with WordPress multisite installations.

== Screenshots ==

1. User activity overview
2. Detailed user activity log
3. FluentCRM integration
4. Plugin settings

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release

== WordPress Hooks Used ==

= WordPress Core =
* `wp_login`: Used to log user login events
* `wp_logout`: Used to log user logout events
* `admin_menu`: Used to add admin menu items
* `admin_init`: Used to register settings
* `admin_enqueue_scripts`: Used to enqueue admin assets
* `wp_enqueue_scripts`: Used to enqueue frontend assets
* `plugins_loaded`: Used to initialize components

= FluentCRM Hooks =
* `fluentcrm_profile_tabs`: Used to add a custom tab to contact profiles
* `fluentcrm_profile_tab_content_user_activity`: Used to render tab content

= LearnDash Hooks =
* `learndash_lesson_completed`: Used to log lesson completions
* `learndash_topic_completed`: Used to log topic completions
* `learndash_quiz_completed`: Used to log quiz completions
* `learndash_course_completed`: Used to log course completions

= Fluent Forms Hooks =
* `fluentform_submission_inserted`: Used to log form submissions