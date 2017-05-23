<?php

namespace ChadoSearch\form\combo;

use ChadoSearch\Set;

class DynamicSelectFilter extends Filter {
  
  public $title;
  public $depend_on_id;
  public $callback;
  public $size;
  public $cacheTable;
  public $cacheColumns;
  
  public function setForm (&$form, &$form_state) {
    $search_name = $this->search_name;
    $id = $this->id;
    
    $tname = "chado_search_cache_" . $search_name . '_' . $id;
    $tname = substr($tname, 0, 63);
    // Create cache table if specified
    if ($this->cacheTable && count($this->cacheColumns) > 0) {
      $cols = '';
      $counter = 0;
      foreach ($this->cacheColumns AS $col) {
        $cols .= $col;
        if ($counter < count ($this->cacheColumns) - 1) {
          $cols .= ', ';
        }
        $counter ++;
      }
      
      if (!db_table_exists($tname)) { // If the cache table not exists, create it
        chado_query("SELECT distinct $cols INTO $tname FROM {" . $this->cacheTable . "}");
        $lastupdate = db_query(
            "SELECT last_update FROM tripal_mviews WHERE mv_table = :mv_table",
            array(':mv_table' => $this->cacheTable)
            )->fetchField();
            variable_set("chado_search_cache_last_update_" . $tname, $lastupdate);
      } else { // If cache table exists, check if it's up-to-date. If it's not up-to-date, recreate the cache table
        $lastupdate = db_query(
            "SELECT last_update FROM tripal_mviews WHERE mv_table = :mv_table",
            array(':mv_table' => $this->cacheTable)
            )->fetchField();
            if (variable_get("chado_search_cache_last_update_" . $tname, "") != $lastupdate) {
              chado_query("DROP table $tname");
              chado_query("SELECT distinct $column INTO $tname FROM {" . $this->cacheTable . "}");
              variable_set("chado_search_cache_last_update_" . $tname, $lastupdate);
            }
      }
      $sql = "SELECT DISTINCT $cols INTO $tname FROM $this->cacheTable";
    }
    // Remove the cache table if cache not specified
    else {
      if (db_table_exists($tname)) {
        chado_query("DROP table $tname");
        variable_del("chado_search_cache_last_update_" . $tname);
      }
    }
    
/*     $id_prefix = $id . '_prefix';
    $id_suffix = $id . '_suffix'; */
    $id_label = $id . '_label';
    $title = $this->title;
    $depend_on_id = $this->depend_on_id;
    $width = '';
    if ($this->label_width) {
      $width = "style=\"width:" . $this->label_width ."px\"";
    }
    $size = $this->size;

    // Add Ajax to the depending element
    $selected = isset($form_state['values'][$depend_on_id]) ? $form_state['values'][$depend_on_id] : 0;
    $form[$depend_on_id]['#ajax'] = array(
      //'callback' => 'chado_search_ajax_form_update', // deprecated. drupal won't allow multiple selection when a file upload exists
      'path' => 'chado_search_ajax_callback',
      'wrapper' => "chado_search-filter-$search_name-$id-field",
      'effect' => 'fade'
    );
    $form[$depend_on_id]['#attribute'] = array ('update' => $id);

    // Wrap the widget around a <div> tag
/*     $form[$id_prefix] = array (
      '#markup' => "<div id=\"chado_search-filter-$search_name-$id\" class=\"chado_search-filter chado_search-widget\">"
    ); */
    // Add Label
    $this->csform->addMarkup(Set::markup()->id($id_label)->text($title));
    $form[$id_label]['#prefix'] =
      "<div id=\"chado_search-filter-$search_name-$id-label\" class=\"chado_search-filter-label form-item\" $width>";
    $form[$id_label]['#suffix'] =
      "</div>";
    // Add Select
    $callback = $this->callback;
    if (function_exists($callback)) {
      //$selected_value = is_array($selected) ? array_shift($selected) : $selected; //deprecated. this only allows one selection
      $this->csform->addSelect(Set::select()->id($id)->options($callback($selected))->size($size));
      $form[$id]['#prefix'] =
        "<div id=\"chado_search-filter-$search_name-$id-field\" class=\"chado_search-filter-field chado_search-widget\">";
      $form[$id]['#suffix'] =
        "</div>";
/*       $form[$id_suffix] = array (
        '#markup' => "</div>"
      ); */
    }
    else {
      drupal_set_message('Fatal Error: DynamicSelectFilter ajax function not implemented', 'error');
    }
  }
}
