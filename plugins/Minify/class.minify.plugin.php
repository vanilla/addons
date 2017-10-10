<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class MinifyPlugin extends Gdn_Plugin {
   
   /** @var string Subfolder that Vanilla lives in */
   protected $BasePath = "";

   /**
    * Remove all CSS and JS files and add minified versions.
    *
    * @param HeadModule $head
    */
   public function headModule_beforeToString_handler($head) {
      // Set BasePath for the plugin
      $this->BasePath = Gdn::request()->webRoot();
      
      // Get current tags
      $tags = $head->tags();

      // Grab all of the CSS
      $cssToCache = [];
      $jsToCache = []; // Add the global js files
      $globalJS = [
         'jquery.js',
         'jquery.livequery.js',
         'jquery.form.js',
         'jquery.popup.js',
         'jquery.gardenhandleajaxform.js',
         'global.js'
      ];
      
      // Process all tags, finding JS & CSS files
      foreach ($tags as $index => $tag) {
         $isJs = getValue(HeadModule::TAG_KEY, $tag) == 'script';
         $isCss = (getValue(HeadModule::TAG_KEY, $tag) == 'link' && getValue('rel', $tag) == 'stylesheet');
         if (!$isJs && !$isCss)
            continue;

         if ($isCss)
            $href = getValue('href', $tag, '!');
         else
            $href = getValue('src', $tag, '!');
         
         // Skip the rest if path doesn't start with a slash
         if ($href[0] != '/')
            continue;

         // Strip any querystring off the href.
         $hrefWithVersion = $href;
         $href = preg_replace('`\?.*`', '', $href);
         
         // Strip BasePath & extra slash from Href (Minify adds an extra slash when substituting basepath)
         if($this->BasePath != '')
            $href = preg_replace("`^/{$this->BasePath}/`U", '', $href);
            
         // Skip the rest if the file doesn't exist
         $fixPath = ($href[0] != '/') ? '/' : ''; // Put that slash back to test for it in file structure
         $path = PATH_ROOT . $fixPath . $href;
         if (!file_exists($path))
            continue;

         // Remove the css from the tag because minifier is taking care of it.
         unset($tags[$index]);

         // Add the reference to the appropriate cache collection.
         if ($isCss) {
            $cssToCache[] = $href;
         } elseif ($isJs) {
            // Don't include the file if it's in the global js.
            $filename = basename($path);
            if (in_array($filename, $globalJS)) {
               continue;
            }
            $jsToCache[] = $href;
         }
      }
      
      // Add minified css & js directly to the head module.
      $url = 'plugins/Minify/min/?' . ($this->BasePath != '' ? "b={$this->BasePath}&" : '');
      
      // Update HeadModule's $Tags
      $head->tags($tags);
      
      // Add minified CSS to HeadModule.
      $token = $this->_PrepareToken($cssToCache, ".css");
      if (file_exists(PATH_CACHE."/Minify/minify_$token")) {
         $head->addCss("/cache/Minify/minify_$token", 'screen', FALSE);
      } else {
         $head->addCss($url.'token='.urlencode($token), 'screen', FALSE);
      }
      
      // Add global minified JS separately (and first)
      $head->addScript($url . 'g=globaljs', 'text/javascript', -100);
      
      // Add other minified JS to HeadModule.
      $token = $this->_PrepareToken($jsToCache, '.js');
      if (file_exists(PATH_CACHE."/Minify/minify_$token")) {
         $head->addScript("/cache/Minify/minify_$token", 'text/javascript', NULL, FALSE);
      } else {
         $head->addScript($url . 'token=' . $token, 'text/javascript', NULL, FALSE);
      }
   }
   
   /**
    * Build unique, repeatable identifier for cache files.
    *
    * @param array $files List of filenames
    * @return string $token Unique identifier for file collection
    */
   protected function _PrepareToken($files, $suffix = '') {
      // Build token.
      $query = ['f' => implode(',', array_unique($files))];
      if ($this->BasePath != '')
         $query['b'] = $this->BasePath;
      $query = serialize($query);
      $token = md5($query).$suffix;
      
      // Save file name with token.
      $cacheFile = PATH_CACHE."/Minify/query_$token";
      if (!file_exists($cacheFile)) {
         if (!file_exists(dirname($cacheFile)))
            mkdir(dirname($cacheFile), 0777, TRUE);
         file_put_contents($cacheFile, $query);
      }
      
      return $token;
   }
   
   /**
    * Create 'Minify' cache folder.
    */
   public function setup() {
      $folder = PATH_CACHE.'/Minify';
      if (!file_exists($folder))
         @mkdir($folder);
   }
   
   /**
    * Empty cache when disabling this plugin.
    */ 
   public function onDisable() { $this->_EmptyCache(); }
   
   /** 
    * Empty cache when enabling or disabling any other plugin, application, or theme.
    */
   public function settingsController_afterEnablePlugin_handler() { $this->_EmptyCache(); }
   public function settingsController_afterDisablePlugin_handler() { $this->_EmptyCache(); }
   public function settingsController_afterEnableApplication_handler() { $this->_EmptyCache(); }
   public function settingsController_afterDisableApplication_handler() { $this->_EmptyCache(); }
   public function settingsController_afterEnableTheme_handler() { $this->_EmptyCache(); }
   
   /**
    * Empty Minify's cache.
    */
   private function _EmptyCache() {
      $files = glob(PATH_CACHE.'/Minify/*', GLOB_MARK);
      foreach ($files as $file) {
         if (substr($file, -1) != '/')
            unlink($file);
      }
   }
}
