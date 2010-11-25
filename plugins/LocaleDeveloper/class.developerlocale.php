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

   public function CapturedDefinitions() {
      return $this->_CapturedDefinitions;
   }

   public function Translate($Code, $Default = FALSE) {
      $Result = parent::Translate($Code, $Default);
      if ($Code)
         $this->_CapturedDefinitions[$Code] = $Result;

      return $Result;
   }
}
