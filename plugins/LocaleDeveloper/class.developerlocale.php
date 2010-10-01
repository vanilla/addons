<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class DeveloperLocale extends Gdn_Locale {
   public $_CapturedDefinitions = array();

   /**
    * Gets all of the definitions in the current locale.
    *
    * return array
    */
   public function AllDefinitions() {
      $Result = array_merge($this->_Definition, $this->_CapturedDefinitions);
      return $Result;
   }

   public function Translate($Code, $Default = FALSE) {
      // Figure out where the translation was called.
//      $Trace = debug_backtrace();
//      $LastIndex = NULL;
//      foreach ($Trace as $Index => $Item) {
//         if (in_array(strtolower($Item['function']), array('t', 'translate'))) {
//            $LastIndex = $Index;
//            continue;
//         } else {
//            break;
//         }
//      }
//      if (isset($Trace[$LastIndex])) {
//         $TraceItem = $Trace[$LastIndex];
//      }

      $Result = parent::Translate($Code, $Default);
      if ($Code)
         $this->_CapturedDefinitions[$Code] = $Result;

      return $Result;
   }
}
