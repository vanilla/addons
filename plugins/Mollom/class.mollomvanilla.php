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
      $pluginInfo = Gdn::PluginManager()->AvailablePlugins();
      return [
         'platformName' => 'Vanilla',
         'platformVersion' => APPLICATION_VERSION,
         'clientName' => 'Mollom Vanilla',
         'clientVersion' => $pluginInfo['Mollom']['Version']
      ];
   }

   protected function request($method, $server, $path, $query = NULL, array $headers = []) {
      $request = new ProxyRequest();
      $request->Request(
         ['Method' => $method,
            'URL' => trim($server,'/').trim($path,'/'),
            //'Debug' => TRUE
         ],
         $query,
         NULL,
         $headers
      );

      $mollomResponse = (object) [
         'code' => $request->ResponseStatus,
         'headers' => $request->ResponseHeaders,
         'body' => $request->ResponseBody,
      ];
      return $mollomResponse;
   }
}
