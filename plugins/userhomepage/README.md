# User Home Page Selector Plugin

This is a simple plugin to allow users to set their preferred home page separately from the site's default settings.

The plugin is made up of the following:

-   A custom preferences page on the profile controller with a simple form.
-   Logic for saving that value with the `UserMetaModel`.
-   An event handler for dynamically setting the default controller with `Gdn_Router` when the dispatcher is started.
-   Event handlers adding navigation to the user preference pages.
