<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo T('JS Connect Settings'); ?></h1>
<div class="Info">
   <?php echo T('You can connect to multiple sites that support jsConnect.'); ?>
</div>
<div class="FilterMenu"><?php
   echo Anchor(T('Add Connection'), '/settings/jsconnect/addedit', 'SmallButton Popup');
?></div>
<table class="AltRows">
   <thead>
      <tr>
         <th><?php echo T('Client ID'); ?></th>
         <th><?php echo T('Site Name'); ?></th>
         <th><?php echo T('Authentication URL'); ?></th>
         <th><?php echo T('Test') ?></th>
         <th>&#160;</th>
      </tr>
   </thead>
   <tbody>
      <?php foreach ($this->Data('Providers') as $Provider): ?>
      <tr>
         <td><?php echo htmlspecialchars($Provider['AuthenticationKey']); ?></td>
         <td><?php echo htmlspecialchars($Provider['Name']); ?></td>
         <td><?php echo htmlspecialchars($Provider['AuthenticateUrl']); ?></td>
         <td>
            <?php 
            echo Anchor(T('Test URL'), JsConnectPlugin::ConnectUrl($Provider, TRUE));
            ?>
            <div class="JsConnectContainer UserInfo"></div>
         </td>
         <td>
            <?php
            echo Anchor(T('Edit'), '/settings/jsconnect/addedit?client_id='.urlencode($Provider['AuthenticationKey']), 'SmallButton');
            echo Anchor(T('Delete'), '/settings/jsconnect/delete?client_id='.urlencode($Provider['AuthenticationKey']), 'SmallButton');
            ?>
         </td>
      </tr>
      <?php endforeach; ?>
   </tbody>
</table>