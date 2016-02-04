# HipChat integration

This is an MVP HipChat notification plugin. It delivers notifications to a designated HipChat room.

## Requirements & Setup

* You need: Vanilla 2.2 and PHP with cURL installed.
* You need: A HipChat account, room, and auth_token (try Integrations -> BYO).
* Fill in those details on the HipChat settings page.
* Notification will begin immediately.

## Forced notifications

* Every new discussion (linked author & title).
* Every new registration (linked to profile or to applicants list with reason).

## Optional notifications.

* None yet.


## History

* 0.3: Abstract HipChat model as its own class.
* 0.2: Add new registrations notification.