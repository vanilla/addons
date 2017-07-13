<?php if (!defined('APPLICATION')) exit();

/**
 * Class MollomVanilla
 *
 * Implements abstract methods in class Mollom.
 */
class MollomVanilla extends Mollom {

   public function loadConfiguration($name) {
      return C('Plugins.Mollom.'.$name, NULL);
   }

   public function saveConfiguration($name, $value) {
      SaveToConfig('Plugins.Mollom.'.$name, $value);
   }

   public function deleteConfiguration($name) {
      RemoveFromConfig('Plugins.Mollom.'.$name);
   }

   public function getClientInformation() {
      $PluginInfo = Gdn::PluginManager()->AvailablePlugins();
      return [
         'platformName' => 'Vanilla',
         'platformVersion' => APPLICATION_VERSION,
         'clientName' => 'Mollom Vanilla',
         'clientVersion' => $PluginInfo['Mollom']['Version']
      ];
   }

   protected function request($method, $server, $path, $query = NULL, array $headers = []) {
      $Request = new ProxyRequest();
      $Request->Request(
         ['Method' => $method,
            'URL' => trim($server,'/').trim($path,'/'),
            //'Debug' => TRUE
         ],
         $query,
         NULL,
         $headers
      );

      $MollomResponse = (object) [
         'code' => $Request->ResponseStatus,
         'headers' => $Request->ResponseHeaders,
         'body' => $Request->ResponseBody,
      ];
      return $MollomResponse;
   }
}
