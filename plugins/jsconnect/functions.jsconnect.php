<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

function WriteJsConnect($User, $Request, $ClientID, $Secret, $Secure = TRUE) {
   $User = array_change_key_case($User);
   
   // Error checking.
   if ($Secure) {
      // Check the client.
      if (!isset($Request['client_id']))
         $Error = array('error' => 'invalid_request', 'message' => 'The client_id parameter is missing.');
      elseif ($Request['client_id'] != $ClientID)
         $Error = array('error' => 'invalid_client', 'message' => "Unknown client {$Request['client_id']}.");
      elseif (!isset($Request['timestamp']) && !isset($Request['signature'])) {
         if (is_array($User) && count($User) > 0) {
            // This isn't really an error, but we are just going to return public information when no signature is sent.
            $Error = array('name' => $User['name'], 'photourl' => @$User['photourl']);
         } else {
            $Error = array('name' => '', 'photourl' => '');
         }
      } elseif (!isset($Request['timestamp']) || !is_numeric($Request['timestamp']))
         $Error = array('error' => 'invalid_request', 'message' => 'The timestamp parameter is missing or invalid.');
      elseif (!isset($Request['signature']))
         $Error = array('error' => 'invalid_request', 'message' => 'Missing  signature parameter.');
      elseif (($Diff = abs($Request['timestamp'] - JsTimestamp())) > 30 * 60)
         $Error = array('error' => 'invalid_request', 'message' => 'The timestamp is invalid.');
      else {
         // Make sure the timestamp hasn't timed out.
         $Signature = md5($Request['timestamp'].$Secret);
         if ($Signature != $Request['signature'])
            $Error = array('error' => 'access_denied', 'message' => 'Signature invalid.');
      }
   }
   
   if (isset($Error))
      $Result = $Error;
   elseif (is_array($User) && count($User) > 0) {
      $Result = SignJsConnect($User, $ClientID, $Secret, TRUE);
   } else
      $Result = array('name' => '', 'photourl' => '');
   
   $Json = json_encode($Result);
   
   if (isset($Request['callback']))
      echo "{$Request['callback']}($Json)";
   else
      echo $Json;
}

function SignJsConnect($Data, $ClientID, $Secret, $ReturnData = FALSE) {
   $Data = array_change_key_case($Data);
   ksort($Data);

   foreach ($Data as $Key => $Value) {
      if ($Value === NULL)
         $Data[$Key] = '';
   }
   
   $String = http_build_query($Data);
//   echo "$String\n";
   $Signature = md5($String.$Secret);
   
   if ($ReturnData) {
      $Data['client_id'] = $ClientID;
      $Data['signature'] = $Signature;
//      $Data['string'] = $String;
      return $Data;
   } else {
      return $Signature;
   }
}

function JsTimestamp() {
   return time() + date("Z");
}