<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_sequence_search_form ($form) {
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('organism')
      ->title('Species')
      ->column('organism')
      ->table('chado_search_sequence_search')
      ->multiple(TRUE)
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('feature_type')
      ->title('Type')
      ->column('feature_type')
      ->table('chado_search_sequence_search')
      ->multiple(TRUE)
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('analysis')
      ->title('Source')
      ->column('analysis_name')
      ->table('chado_search_sequence_search')
      ->multiple( TRUE)
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('feature_name')
      ->title('Name')
  );
  $form->addFile(
      Set::file()
      ->id('feature_name_file')
      ->title("File Upload")
      ->description("Provide sequence names in a file. Separate each name by a new line.")
      ->newLine()
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('location')
      ->title('Location')
      ->dependOnId('organism')
      ->callback('chado_search_sequence_search_ajax_location')
  );
  $form->addBetweenFilter(
      Set::betweenFilter()
      ->id('fmin')
      ->title("between")
      ->id2('fmax')
      ->title2("and")
      ->size(15)
      ->labelWidth2(50)
  );
  $form->addSubmit();
  $form->addReset();
  $desc = "Search for sequences by entering names in the field below. Alternatively, you may upload a file of names. You may also filter results by sequence type and the sequence source. To select multiple options click while holding the \"ctrl\" key. The results can be downloaded in FASTA or CSV tabular format.";
  $form->addFieldset(
      Set::fieldset()
      ->id('sequence_search')
      ->startWidget('organism')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

// Submit the form
function chado_search_sequence_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = "SELECT * FROM {chado_search_sequence_search}";
  // Add conditions
  $where [0] = Sql::textFilterOnMultipleColumns('feature_name', $form_state, array('uniquename', 'name'));
  $where [1] = Sql::selectFilter('feature_type', $form_state, 'feature_type');
  $where [2] = Sql::selectFilter('analysis', $form_state, 'analysis_name');
  $where [3] = Sql::fileOnMultipleColumns('feature_name_file', array('uniquename', 'name'));
  $where [4] = Sql::selectFilter('organism', $form_state, 'organism');
  $where [5] = Sql::selectFilter('location', $form_state, 'landmark');
  $where [6] = Sql::betweenFilter('fmin', 'fmax', $form_state, 'fmin', 'fmax');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_sequence_search_table_definition')
    ->fastaDownload(TRUE)
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_sequence_search_table_definition () {
  $headers = array(      
      'name:s:chado_search_sequence_search_link_feature:feature_id' => 'Name',
      'uniquename:s' => 'Uniquename',
      'feature_type:s' => 'Type',
      'organism:s' => 'Organism',
      'analysis_name:s:chado_search_sequence_search_link_analysis:analysis_id' => 'Source',
      'location:s:chado_search_legume_sequence_search_link_jbrowse:srcfeature_id,location,analysis_name' => 'Location'
  );
  return $headers;
}
// Define call back to link the sequence to its node for result table
function chado_search_sequence_search_link_analysis ($analysis_id) {
  $nid = chado_get_nid_from_id('analysis', $analysis_id);
  return chado_search_link_node ($nid);
}
// Define call back to link the featuremap to its  node for result table
function chado_search_sequence_search_link_feature ($feature_id) {
  $nid = chado_get_nid_from_id('feature', $feature_id);
  return chado_search_link_node ($nid);
}
// Define call back to link the location to GBrowse
function chado_search_legume_sequence_search_link_jbrowse ($paras) {
  $srcfeature_id = $paras [0];
  $loc = preg_replace("/ +/", "", $paras [1]);
  $loc = str_replace("_v1.0_kabuli", "", $loc);
  $analysis = $paras [2];
  $org = chado_query("SELECT genus || ' ' || species FROM {organism} WHERE organism_id = (SELECT organism_id FROM {feature} WHERE feature_id = :srcfeature_id)", array(':srcfeature_id' => $srcfeature_id))->fetchField();
  $url = "";
  if($org == 'Cicer arietinum') {
    $url = "https://www.coolseasonfoodlegume.org/jbrowse/index.html?data=data%2Fchickpea%2FCa_kabuli_v1&loc=$loc";
  }
  return chado_search_link_url ($url);
}
/*************************************************************
 * AJAX callbacks
*/
// User defined: Populating the landmark for selected organism
function chado_search_sequence_search_ajax_location ($val) {
  $sql = "SELECT distinct landmark, char_length(landmark) AS length FROM {chado_search_sequence_search} WHERE organism = :organism ORDER BY length, landmark";
  return chado_search_bind_dynamic_select(array(':organism' => $val), 'landmark', $sql);
}
