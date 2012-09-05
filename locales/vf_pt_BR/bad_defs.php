<?php

$Definition['Ban Item'] = 'Item';

$Definition['Date.DefaultDateTimeFormat'] = '%B %e, %Y %l:%M%p';
$Definition['Date.DefaultDayFormat'] = '%B %e';
$Definition['Date.DefaultFormat'] = '%B %e, %Y';
$Definition['Date.DefaultTimeFormat'] = '%l:%M%p';
$Definition['Date.DefaultYearFormat'] = '%B %Y';
$Definition['Define who can upload files on the Roles & Permissions page.'] = 'Define who can upload and manage files on the <a href="%s">Roles & Permissions</a> page.';
$Definition['Draft.Delete'] = '×';

$Definition['Email Source'] = 'Email';
$Definition['EmailConfirmEmail'] = 'You need to confirm your email address before you can continue. Please confirm your email address by clicking on the following link: {/entry/emailconfirm,exurl,domain}/{User.UserID,rawurlencode}/{EmailKey,rawurlencode}';
$Definition['EmbeddedDiscussionFormat'] = '<div class="EmbeddedContent">{Image}<strong>{Title}</strong>
<p>{Excerpt}</p>
<p><a href="{Url}">Read the full story here</a></p><div class="ClearFix"></div></div>';
$Definition['EmbeddedNoBodyFormat'] = '{Url}';
$Definition['EmbededDiscussionFormat'] = '<div class="EmbeddedContent">{Image}<strong>{Title}</strong>
<p>{Excerpt}</p>
<p><a href="{Url}">Read the full story here</a></p><div class="ClearFix"></div></div>';

$Definition['Garden.Import.Merge.Description'] = 'This will merge all of the user and discussion data from the import into this forum.
<b>Warning: If you merge the same data twice you will get duplicate discussions.</b>';

$Definition['HeadlineFormat.Ban'] = '{RegardingUserID,You} banned {ActivityUserID,you}.';
$Definition['HeadlineFormat.Comment'] = '{ActivityUserID,user} commented on <a href="{Url,html}">{Data.Name,text}</a>';
$Definition['HeadlineFormat.Discussion'] = '{ActivityUserID,user} Started a new discussion. <a href="{Url,html}">{Data.Name,text}</a>';
$Definition['HeadlineFormat.Mention'] = '{ActivityUserID,user} mentioned you in <a href="{Url,html}">{Data.Name,text}</a>';
$Definition['HeadlineFormat.PictureChange.ForUser'] = '{RegardingUserID,You} changed the profile picture for {ActivityUserID,user}.';
$Definition['HeadlineFormat.Unban'] = '{RegardingUserID,You} unbanned {ActivityUserID,you}.';
$Definition['HeadlineFormat.Warning'] = '{ActivityUserID,You} warned {RegardingUserID,you}.';

$Definition['Locale'] = 'en-CA';

$Definition['MoneyFormat2'] = '$%7.2f';

$Definition['Null Date'] = '-';

$Definition['ProxyConnect.RimBlurb'] = 'If you are using ProxyConnect with an officially supported remote application plugin such as our wordpress-proxyconnect plugin, these values will be available in that plugin\'s configuration screen.';

$Definition['Ranks.ActivityFormat'] = '{ActivityUserID,user} {ActivityUserID,plural,was,were} promoted to {Data.Name,plaintext}.';
$Definition['Ranks.NotificationFormat'] = 'Congratulations! You\'ve been promoted to {Data.Name,plaintext}.';

$Definition['Warning: Loading tables can be slow.'] = '<b>Warning</b>: Your server configuration does not support fast data loading.
If you are importing a very large file (ex. over 200,000 comments) you might want to consider changing your configuration. Click <a href="http://vanillaforums.com/porter">here</a> for more information.';
$Definition['WarningTitleFormat'] = '{InsertUserID,User} warned {WarnUserID,User} for {Points,plural,%s points}.';
$Definition['WarningTitleFormat.Notice'] = '{InsertUserID,User} warned {WarnUserID,User} for {Points,plural,%s points} (just a notice).';

$Definition['You can use HTML in your signature.'] = 'You can use <b><a href="http://htmlguide.drgrog.com/cheatsheet.php" target="_new">Simple Html</a></b> in your signature.';
