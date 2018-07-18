<?php

namespace ChadoSearch\result;

use ChadoSearch\SessionVar;

class Download extends Source {
  
  public $search_id;
  public $path;
  public $show;
  
  public function __construct($search_id, $path, $show = TRUE) {
    $this->search_id = $search_id;
    $this->path = $path;
    $this->show = $show;
    $js = $this->jsDownload();
    $this->src = $js; 
  }
  
  private function jsDownload() {
    $search_id = $this->search_id;
    $path = $this->path;
    $show = $this->show;
    if ($path == NULL) {
      $path = "search/$search_id/download";
    } else {
      $path = $path . "/download";
    }
    $dpost = "form_build_id=" . $_POST['form_build_id'];
    global $base_url;
    $js =
      "<script type=\"text/javascript\">
          (function ($) {
            function " . $search_id . "_download (custom) {
              if (custom) {
                var sendData = '$dpost' + '&custom_function_call=' + custom;
              } else {
                var sendData = '$dpost';
              }
              var link = '$base_url';
              link += '/$path';
              $('.chado_search-$search_id-waiting-box').show();
              var check_progress = setInterval(function(){
                // Check the progress
                $.ajax({
                  url: link + '/progress',
                  dataType: 'json',
                  success: function(data){
                  $('#chado_search-$search_id-waiting-box-progress').show();
                    $('#chado_search-$search_id-waiting-box-progress').text(data.progress + ' %');
                  }
                });
              }, 2000);
              $.ajax({
                url: link,
                data: sendData,
                dataType: 'json',
                type: 'POST',
                success: function(data){
                  window.location = data.path;
                  $('.chado_search-$search_id-waiting-box').hide();
                  $('#chado_search-$search_id-waiting-box-progress').text('0 %');
                  $('#chado_search-$search_id-waiting-box-progress').hide();
                  clearInterval(check_progress);
                }
              });
             }
             window." . $search_id . "_download = " . $search_id . "_download;
          })(jQuery);
       </script>";
    if ($show) {
      $js .=
        "<div id=\"$search_id-table-download\" class=\"chado_search-download-links\">
            <a href=\"javascript:void(0)\" onClick=\"" . $search_id . "_download();return false;\">
              Table
            </a>
         </div>";
    }
    return $js;
  }
  
  // Set up download
  public function createDownload ($headers) {
    $search_id = $this->search_id;
    $path = $this->path;
    
    // Do not impose a time limit 
    set_time_limit(0);

    // If header is not defined, return
    if (!$headers) {
      $headers = SessionVar::getSessionVar($search_id, 'default-headers');
      if (!$headers) {
        return array();
      }
    }
    // Get the SQL from $_SESSION
    // Try to get SQL that includes '</br>' tag
    $sql = SessionVar::getSessionVar($search_id, 'download');
    if (!$sql) {
      // If no SQL with </br> tag found, get default SQL
      $sql = SessionVar::getSessionVar($search_id, 'sql');
    }
    if (!$sql) {
      return array('path' => "/$path");
    }
    $orderby = SessionVar::getSessionVar($search_id, 'download-order');
    if ($orderby) {
      $sql .= " ORDER BY " . $orderby;
    }
    // Disable columns on request
    $disabledCols = SessionVar::getSessionVar($search_id, 'disabled-columns');
    if ($disabledCols) {
      $dcols = explode(';', $disabledCols);
      foreach ($dcols AS $dc) {
          foreach($headers AS $hk => $hv) {
            $pattern = explode(':', $hk);
            if ($pattern[0] == $dc) {
              unset ($headers[$hk]);
            }
          }
      }
    }
    // Change the text file headers on request
    $changedHeaders = SessionVar::getSessionVar($search_id, 'changed-headers');
    if ($changedHeaders) {
      $cheaders = explode(';', $changedHeaders);
      foreach ($cheaders AS $ch) {
          foreach($headers AS $hk => $hv) {
            $pattern = explode(':', $hk);
            $h = explode('=', $ch);
            if ($pattern[0] == $h[0]) {
              $headers[$hk] = $h[1];
            }
          }
      }
    }
    // Rewrite columns on request, conver the session variable (i.e. <column1>=<callback1>;) into an associated array (i.e. 'column1' => 'callback1')
    $rewriteCols = SessionVar::getSessionVar($search_id, 'rewrite-columns');
    $rewriteCallback = array();
    if ($rewriteCols) {
      $rwcols = explode(';', $rewriteCols);
      foreach ($rwcols AS $rwc) {
        $rewrite = explode('=', $rwc);
        if (count($rewrite) == 2 && function_exists($rewrite[1]) ) {
          $rewriteCallback[$rewrite[0]] = $rewrite[1];
        }
      }
    }
  
    // Get hstore column settings if there is any
    $hstoreToColumns = SessionVar::getSessionVar($search_id, 'hstore-to-columns');
    $hstoreCol = $hstoreToColumns['column'];
    
    // Create result
    $result = chado_query($sql);
    $sid = session_id();
    $file = $search_id . '_download.csv';
    $dir = 'sites/default/files/tripal/chado_search/' . $sid;
    if (!file_exists($dir)) {
      mkdir ($dir, 0777);
    }
    $path = $dir . "/" . $file;
    $handle = fopen($path, 'w');
    $total_items = SessionVar::getSessionVar($search_id,'total-items');
    $progress_var = 'chado_search-'. session_id() . '-' . $search_id . '-download-progress';
    // If there is a custom function call, pass in $handle and $result for it to modify output
    $custom_function = isset($_POST['custom_function_call']) ? $_POST['custom_function_call'] : NULL;
    if ($custom_function) {
      $custom_function($handle, $result, $sql, $total_items, $progress_var, $headers, $hstoreCol, $hstoreToColumns);
    } else {
      fwrite($handle, "\"#\",");
      $col = 0;
      foreach ($headers AS $k => $v) {
        // handle the hstore column
        if ($k == $hstoreCol) {
          $counter_hs = 0;
          $total_hs = count($hstoreToColumns['data']);
          foreach ($hstoreToColumns['data'] AS $hsk => $hsv) {
            fwrite($handle, "\"". $hsv . "\"");
            if ($counter_hs < $total_hs - 1) {
              fwrite($handle, ",");
            }
            $counter_hs ++;
          }
        }
        else {
          fwrite($handle, "\"". $v . "\"");
        }
        $col ++;
        if ($col < count($headers)) {
          fwrite($handle, ",");
        } else {
          fwrite($handle, "\n");
        }
      }
      $progress = 0;
      $counter = 1;
      while ($row = $result->fetchObject()) {
        $current = round ($counter / $total_items * 100);
        if ($current != $progress) {
          $progress = $current;
          variable_set($progress_var, $progress);
        }
        fwrite($handle, "\"$counter\",");
        $col = 0;
        foreach ($headers AS $k => $v) {
          // handle the hstore column
          if ($k == $hstoreCol) {
            $value = property_exists($row, $k) ? $row->$k : ''; // hstore column value
            $values = chado_search_hstore_to_assoc($value);
            $counter_hs = 0;
            $total_hs = count($hstoreToColumns['data']);
            foreach ($hstoreToColumns['data'] AS $hsk => $hsv) {
              $display_val = key_exists($hsk, $values) ? $values[$hsk] : '';
              fwrite($handle, '"' . str_replace('"', '""', $display_val) . '"');
              if ($counter_hs < $total_hs - 1) {
                fwrite($handle, ",");
              }
              $counter_hs ++;
            }
          }
          else {
            $value = $row->$k;
            if (key_exists($k, $rewriteCallback)) {
              $rwfunc = $rewriteCallback[$k];
              $value = $rwfunc($value);
            }
            fwrite($handle, '"' . str_replace('"', '""', $value) . '"');
          }
          $col ++;
          if ($col < count($headers)) {
            fwrite($handle, ",");
          } else {
            fwrite($handle, "\n");
          }
        }
        $counter ++;
      }
    }
    fclose($handle);
    chmod($path, 0777);
    $url = "/sites/default/files/tripal/chado_search/$sid/$file";
    
    // Reset progress bar
    variable_del($progress_var);
    return array ('path' => $url);
  }
}
