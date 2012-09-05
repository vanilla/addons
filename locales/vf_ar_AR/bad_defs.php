<?php

$Definition['Activity.PictureChange.FullHeadline'] = '%1$s changed %6$s profile picture.';
$Definition['Activity.PictureChange.ProfileHeadline'] = '%1$s changed %6$s profile picture.';

$Definition['Commenting as %1$s (%2$s)'] = 'Commenting as %1$s <span class="SignOutWrap">(%2$s)</span>';
$Definition['Confirm email addresses'] = 'Require users to confirm their email addresses (recommended)';

$Definition['Date.DefaultDateTimeFormat'] = '%B %e, %Y %l:%M%p';
$Definition['Date.DefaultTimeFormat'] = '%l:%M%p';
$Definition['Define who can upload files on the Roles & Permissions page.'] = 'Define who can upload and manage files on the <a href="%s">Roles & Permissions</a> page.';
$Definition['Draft.Delete'] = '×';

$Definition['EmailConfirmEmail'] = 'You need to confirm your email address before you can continue. Please confirm your email address by clicking on the following link: {/entry/emailconfirm,exurl,domain}/{User.UserID,rawurlencode}/{EmailKey,rawurlencode}';
$Definition['EmailInvitation'] = 'Hello!

%1$s has invited you to join %2$s. If you want to join, you can do so by clicking this link:

  %3$s';
$Definition['EmailMembershipApproved'] = 'Hello %1$s,

You have been approved for membership. Sign in now at the following link:

  %2$s';
$Definition['EmailNotification'] = '%1$s

Follow the link below to check it out:
%2$s

Have a great day!';
$Definition['EmailPassword'] = '%2$s has reset your password at %3$s. Your login credentials are now:

  Email: %6$s
  Password: %5$s
  Url: %4$s';
$Definition['EmailStoryNotification'] = '%1$s

%3$s

---
Follow the link below to check it out:
%2$s

Have a great day!';
$Definition['EmailWelcome'] = '%2$s has created an account for you at %3$s. Your login credentials are:

  Email: %6$s
  Password: %5$s
  Url: %4$s';
$Definition['EmailWelcomeConnect'] = 'You have successfully connected to {Title}. Here is your information:

  Username: {User.Name}
  Connected With: {ProviderName}

You can access the site at {/,exurl,domain}.';
$Definition['EmbeddedDiscussionFormat'] = '<div class="EmbeddedContent">{Image}<strong>{Title}</strong>
<p>{Excerpt}</p>
<p><a href="{Url}">Read the full story here</a></p><div class="ClearFix"></div></div>';
$Definition['EmbeddedNoBodyFormat'] = '{Url}';
$Definition['EmbededDiscussionFormat'] = '<div class="EmbeddedContent">{Image}<strong>{Title}</strong>
<p>{Excerpt}</p>
<p><a href="{Url}">Read the full story here</a></p><div class="ClearFix"></div></div>';
$Definition['ErrorPluginDisableRequired'] = 'You cannot disable the {0} plugin because the {1} plugin requires it in order to function.';
$Definition['ErrorPluginEnableRequired'] = 'This plugin requires that the {0} plugin be enabled before it can be enabled itself.';
$Definition['ErrorPluginVersionMatch'] = 'The enabled {0} plugin (version {1}) failed to meet the version requirements ({2}).';

$Definition['Garden.Import.Complete.Description'] = 'You have successfully completed an import.
   Click <b>Finished</b> when you are ready.';
$Definition['Garden.Import.Continue.Description'] = 'It appears as though you are in the middle of an import.
   Please choose one of the following options.';
$Definition['Garden.Import.Merge.Description'] = 'This will merge all of the user and discussion data from the import into this forum.
<b>Warning: If you merge the same data twice you will get duplicate discussions.</b>';

$Definition['Locale'] = 'en-CA';

$Definition['MoneyFormat2'] = '$%7.2f';

$Definition['Null Date'] = '-';

$Definition['Operation By'] = 'By';

$Definition['PasswordRequest'] = 'Someone has requested to reset your password at %2$s. To reset your password, follow this link:

  %3$s

If you did not make this request, disregard this email.';
$Definition['ProfileFieldsCustomDescription'] = 'Use these fields to create custom profile information. You can enter things like "Relationship Status", "Skype", or "Favorite Dinosaur". Be creative!';
$Definition['ProxyConnect.RimBlurb'] = 'If you are using ProxyConnect with an officially supported remote application plugin such as our wordpress-proxyconnect plugin, these values will be available in that plugin\'s configuration screen.';

$Definition['Ranks.ActivityFormat'] = '{ActivityUserID,user} {ActivityUserID,plural,was,were} promoted to {Data.Name,plaintext}.';
$Definition['Ranks.NotificationFormat'] = 'Congratulations! You\'ve been promoted to {Data.Name,plaintext}.';

$Definition['Test Mode'] = 'Test Mode: The pocket will only be displayed for pocket administrators.';
$Definition['The quote had to be converted from %s to %s.'] = 'The quote had to be converted from %s to %s. Some formatting may have been lost.';

$Definition['UserDeletionPrompt'] = 'Choose how to handle all of the content associated with the user account for %s (comments, messages, etc).';

$Definition['ValidateIntegerArray'] = '%s must be a comma-delimited list of numbers.';
$Definition['ValidateLength'] = '%1$s is %2$s characters too long.';
$Definition['ValidateOneOrMoreArrayItemRequired'] = 'You must select at least one %s.';
$Definition['ValidateRequiredArray'] = 'You must select at least one %s.';
$Definition['ValidateTimestamp'] = '%s is not a valid timestamp.';
$Definition['ValidateUrlStringRelaxed'] = '%s can not contain slashes, quotes or tag characters.';
$Definition['ValidateUsername'] = 'Usernames must be 3-20 characters and consist of letters, numbers, and underscores.';
$Definition['ValidateVersion'] = 'The %s field is not a valid version number. See the php version_compare() function for examples of valid version numbers.';

$Definition['Warning: Loading tables can be slow.'] = '<b>Warning</b>: Your server configuration does not support fast data loading.
If you are importing a very large file (ex. over 200,000 comments) you might want to consider changing your configuration. Click <a href="http://vanillaforums.com/porter">here</a> for more information.';
$Definition['WarningTitleFormat'] = '{InsertUserID,User} warned {WarnUserID,User} for {Points,plural,%s points}.';
$Definition['WarningTitleFormat.Notice'] = '{InsertUserID,User} warned {WarnUserID,User} for {Points,plural,%s points} (just a notice).';
$Definition['Wordpress Source'] = 'Wordpress';

$Definition['You can either ask a question or start a discussion.'] = 'You can either ask a question or start a discussion. Choose what you want to do below.';
$Definition['You can place files in your /uploads folder.'] = 'If your file is too
		large to upload directly to this page you can place it in your /uploads
		folder. Make sure the filename begins with the word <b>export</b> and ends
		with one of <b>.txt, .gz</b>.';
$Definition['You can use HTML in your signature.'] = 'You can use <b><a href="http://htmlguide.drgrog.com/cheatsheet.php" target="_new">Simple Html</a></b> in your signature.';
$Definition['You were added to a conversation.'] = '{InsertUserID,user} added {NotifyUserID,you} to a <a href="{Url,htmlencode}">conversation</a>.';
$Definition['Your default locale won\'t display properly'] = 'Your default locale won\'t display properly until it is enabled below. Please enable the following: %s.';
