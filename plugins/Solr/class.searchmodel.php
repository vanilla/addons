<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 */

class SearchModel extends Gdn_Model {
	/// PROPERTIES ///
   public $Types = [1 => 'Discussion', 2 => 'Comment'];

   /// METHODS ///

   public function search($search, $offset = 0, $limit = 20) {
      $baseUrl = c('Plugins.Solr.SearchUrl', 'http://localhost:8983/solr/select/?');
      if (!$baseUrl)
         throw new Gdn_UserException("The search url has not been configured.");

      if (!$search)
         return [];

      // Escepe the search.
      $search = preg_replace('`([][+&|!(){}^"~*?:\\\\-])`', "\\\\$1", $search);

      // Add the category watch.
      $categories = CategoryModel::categoryWatch();
      if ($categories === FALSE) {
         return [];
      } elseif ($categories !== TRUE) {
         $search = 'CategoryID:('.implode(' ', $categories).') AND '.$search;
      }

      // Build the search url.
      $baseUrl .= strpos($baseUrl, '?') === FALSE ? '?' : '&';
      $query = ['q' => $search, 'start' => $offset, 'rows' => $limit];
      $url = $baseUrl.http_build_query($query);

      // Grab the data.
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $curlResult = curl_exec($curl);
      curl_close($curl);

      // Parse the result into the form that the search controller expects.
      $xml = new SimpleXMLElement($curlResult);
      $result = [];

      if (!isset($xml->result))
         return [];

      foreach ($xml->result->children() as $doc) {
         $row = [];
         foreach ($doc->children() as $field) {
            $name = (string)$field['name'];
            $row[$name] = (string)$field;
         }
         // Add the url.
         switch ($row['DocType']) {
            case 'Discussion':
               $row['Url'] = '/discussion/'.$row['PrimaryID'].'/'.Gdn_Format::url($row['Title']);
               break;
            case 'Comment':
               $row['Url'] = "/discussion/comment/{$row['PrimaryID']}/#Comment_{$row['PrimaryID']}";
               break;
         }
         // Fix the time.
         $row['DateInserted'] = strtotime($row['DateInserted']);
         $result[] = $row;
      }

      // Join the users into the result.
      Gdn_DataSet::join($result, ['table' => 'User', 'parent' => 'UserID', 'prefix' => '', 'Name', 'Photo']);

      return $result;
	}

}