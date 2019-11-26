<?php
/**
 * @file
 * Utility commands for ACSF sites.
 */

use Drush\Log\LogLevel;

/*
$sites_file = $file_prefix . "_sites.json";
$entities_file = $file_prefix . "_entities.json";
$views_file = $file_prefix . "_views.json";*/

/**
 * Implements hook_drush_command().
 */
function acsf_tools_analyze_drush_command() {
  $items = array();

  $items['acsf-tools-analyze'] = array(
    'description' => dt('Extract information about sites on the factory.'),
    'options' => array(
      'out' => 'Path to directory where result files should be placed. Defaults to alias named directory in current directory.',
      'types' => 'The list of information types to extract (comma separated list). Supported options: sites,modules,entities,views',
      'format' => 'Output format. In addition to default JSON files, specified format files will be created. Supported options: csv.',
      'rebuild' => 'If set, existing JSON files with information will be rebuilt. Combined with --types option, can be used to reset specific information sets.',
      'rebuild-csv' => 'If set, existing CSV files with information will be rebuilt. Combined with --types option, can be used to reset specific CSV files.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'examples' => array(
      'drush acsf-tools-analyze @site.01dev' => 'Extract all details for all the sites of the factory.',
      'drush acsf-tools-analyze @site.01dev --types=modules' => 'Extract only used modules details for all the sites of the factory.',
      'drush acsf-tools-analyze @site.01dev --types=modules --rebuild' => 'Rebuild used modules details for all the sites of the factory.',
      'drush acsf-tools-analyze @site.01dev --rebuild' => 'Rebuild information of all types for all the sites of the factory.',
    ),
    'aliases' => ['sfa'],
  );

  return $items;
}

/**
 * Validate callback for acsf-tools-analyze command.
 */
function drush_acsf_tools_analyze_validate() {
  $arguments = drush_get_arguments();
  if (count($arguments) == 1) {
    return drush_set_error('WRONG_PARAMETERS', dt('This command expects at least one parameter.'));
  }
  else {
    $dir = drush_acsf_tools_analyze_get_dir();

    if (!file_exists($dir)) {
      drush_log(dt("Output directory %dir does not exist. Creating it.", array('%dir' => $dir)), LogLevel::NOTICE);
      mkdir($dir);
      if (is_writable($dir)) {
        drush_log(dt("Output directory %dir created and is writable.", array('%dir' => $dir)), LogLevel::OK);
      }
    }
    elseif (!is_writable($dir)) {
      return drush_set_error('OUPUT_DIR_NOT_WRITABLE', dt('Output directory %dir is not writable.', array('%dir' => $dir)));
    }
    else {
      drush_log(dt("Output directory %dir exists and is writable.", array('%dir' => $dir)), LogLevel::OK);
    }

    $alias = drush_acsf_tools_analyze_get_alias();
    $info_file = drush_acsf_tools_analyze_get_file_path('info.json');

    if (!file_exists($info_file)) {
      drush_log(dt("File %s does not exist. Creating it.", array('%s' => $info_file)), LogLevel::NOTICE);
      drush_log(dt("Getting basic @%s factory information.", array('%s' => $alias)), LogLevel::NOTICE);

      drush_shell_exec("drush @$alias status --format=json > $info_file");
    }

    $info = drush_acsf_tools_analyze_get_info();

    if (!isset($info['drupal-version'])) {
      return drush_set_error('FACTORY_INFO_NOT_AVAILABLE', dt('Could not get basic information about @%s.', array('%s' => $alias)));
    }
    elseif (strpos($info['drupal-version'], '7') === 0) {
      $info['drupal-major-version'] = 7;
    }
    elseif (strpos($info['drupal-version'], '8') === 0) {
      $info['drupal-major-version'] = 8;
    }
    else {
      return drush_set_error('DRUPAL_VERSION_NOT_SUPPORTED', dt('Unsupported Drupal version: %s.', array('%s' => $info['drupal-version'])));
    }

    drush_log(dt("Drupal version: %s (%m).", array('%s' => $info['drupal-version'], '%m' => $info['drupal-major-version'])), LogLevel::OK);

    $sites = drush_acsf_tools_analyze_get_sites();
    if (!is_array($sites)) {
      return drush_set_error('NO_SITES_INFORMATION', dt('Could not retrieve sites list.'));
    }
    else {
      drush_log(dt("Total domains: %s.", array('%s' => count($sites))), LogLevel::OK);
    }

    $custom_domains = drush_acsf_tools_analyze_get_sites_custom();
    drush_log(dt("Custom domains: %s.", array('%s' => count($custom_domains))), LogLevel::OK);

    $preferred_domains = drush_acsf_tools_analyze_get_sites_preferred();
    drush_log(dt("Preferred domains (this shows accurate site count): %s.", array('%s' => count($preferred_domains))), LogLevel::OK);
  }
}

/**
 * Get information about entities used on each site.
 */
function drush_acsf_tools_analyze_get_site_entities($rebuild = FALSE) {
  $sites = drush_acsf_tools_analyze_get_site_statuses();
  $preferred_domains_count = count($sites);
  $entities_file = drush_acsf_tools_analyze_get_file_path('entities.json');
  $alias = '@' . drush_acsf_tools_analyze_get_alias();
  $info = drush_acsf_tools_analyze_get_info();

  if (!file_exists($entities_file) || $rebuild) {
    drush_log(dt("File %s does not exist. Creating it.", array('%s' => $entities_file)), LogLevel::NOTICE);

    $entities_info = [];
    $count = 1;
    foreach ($sites as $uri => $site) {
      $entities_info[$uri]['entities'] = [];

      $arg = array(
        '%s' => $uri,
        '%i' => $count,
        '%t' => $preferred_domains_count
      );
      drush_log(dt("Getting entity types and usage stats for site %s (%i of %t).", $arg), LogLevel::OK); // @TODO review log level.

      $out_arr = [];
      exec("drush $alias --uri=$uri sqlq 'select type, count(*) from node group by type;'", $out_arr);
      // @TODO check output.

      $entities_info[$uri]['entities']['node'] = [];
      foreach ($out_arr as $r) {
        $l = explode("\t", $r);

        if (isset($l[1])) {
          $entities_info[$uri]['entities']['node'][$l[0]] = $l[1];
        }
      }

      $out_arr = [];
      exec("drush $alias --uri=$uri sqlq 'select type, count(*) from block_content group by type;'", $out_arr);

      $entities_info[$uri]['entities']['block'] = [];
      foreach ($out_arr as $r) {
        $l = explode("\t", $r);
        if (isset($l[1])) {
          $entities_info[$uri]['entities']['block'][$l[0]] = $l[1];
        }
      }

      $entities_info[$uri]['entities']['paragraph'] = [];
      if (isset($sites[$uri]['modules']['paragraphs']) && $sites[$uri]['modules']['paragraphs']['status'] === 'Enabled') {
        $out_arr = [];
        exec("drush $alias --uri=$uri sqlq 'select type, count(*) from paragraphs_item group by type;'", $out_arr);

        foreach ($out_arr as $r) {
          $l = explode("\t", $r);

          if (isset($l[1])) {
            $entities_info[$uri]['entities']['paragraph'][$l[0]] = $l[1];
          }
        }
      }

      $entities_info[$uri]['entities']['fieldable_panels_pane'] = [];
      if (isset($sites[$uri]['modules']['fieldable_panels_panes']) && $sites[$uri]['modules']['fieldable_panels_panes']['status'] === 'Enabled') {
        $out_arr = [];
        exec("drush $alias --uri=$uri sqlq 'select bundle, count(*) from fieldable_panels_panes group by bundle'", $out_arr);

        foreach ($out_arr as $r) {
          $l = explode("\t", $r);

          if (isset($l[1])) {
            $entities_info[$uri]['entities']['fieldable_panels_pane'][$l[0]] = $l[1];
          }
        }
      }

      $entities_info[$uri]['entities']['panels_pane'] = [];
      if (isset($sites[$uri]['modules']['panels']) && $sites[$uri]['modules']['panels']['status'] === 'Enabled') {
        $out_arr = [];
        exec("drush $alias --uri=$uri sqlq 'select type, count(*) from panels_pane group by type;'", $out_arr);

        foreach ($out_arr as $r) {
          $l = explode("\t", $r);

          if (isset($l[1])) {
            $entities_info[$uri]['entities']['panels_pane'][$l[0]] = $l[1];
          }
        }
      }


      $entities_info[$uri]['entities']['profile2'] = [];
      if (isset($sites[$uri]['modules']['profile2']) && $sites[$uri]['modules']['profile2']['status'] === 'Enabled') {
        $out_arr = [];
        exec("drush $alias --uri=$uri sqlq 'select type, count(*) from profile group by type;'", $out_arr);

        foreach ($out_arr as $r) {
          $l = explode("\t", $r);

          if (isset($l[1])) {
            $entities_info[$uri]['entities']['profile2'][$l[0]] = $l[1];
          }
        }
      }

      $entities_info[$uri]['entities']['field_collection'] = [];
      if (isset($sites[$uri]['modules']['field_collection']) && $sites[$uri]['modules']['field_collection']['status'] === 'Enabled') {
        $out_arr = [];
        exec("drush $alias --uri=$uri sqlq 'select field_name, count(*) from field_collection_item group by field_name;'", $out_arr);

        foreach ($out_arr as $r) {
          $l = explode("\t", $r);

          if (isset($l[1])) {
            $entities_info[$uri]['entities']['field_collection'][$l[0]] = $l[1];
          }
        }
      }

      $entities_info[$uri]['entities']['eck'] = [];
      if (isset($sites[$uri]['modules']['eck']) && $sites[$uri]['modules']['eck']['status'] === 'Enabled') {
        $out_arr = [];
        exec("drush $alias --uri=$uri sqlq 'select distinct entity_type from eck_bundle;'", $out_arr);

        $eck_types = [];
        foreach ($out_arr as $r) {
          $eck_types[] = $r;
        }

        foreach ($eck_types as $type) {
          $out_arr = [];
          exec("drush $alias --uri=$uri sqlq 'select type, count(*) from eck_$type group by type;'", $out_arr);

          $entities_info[$uri]['entities']['eck_' . $type] = [];
          foreach ($out_arr as $r) {
            $l = explode("\t", $r);

            if (isset($l[1])) {
              $entities_info[$uri]['entities']['eck_' . $type][$l[0]] = $l[1];
            }
          }
        }
      }

      $out_arr = [];
      exec("drush $alias --uri=$uri sqlq 'select r.name, count(*) from users_roles as ur join role as r on ur.rid = r.rid group by r.name;'", $out_arr);

      $entities_info[$uri]['entities']['user'] = [];
      foreach ($out_arr as $r) {
        $l = explode("\t", $r);
        if (isset($l[1])) {
          $entities_info[$uri]['entities']['user'][$l[0]] = $l[1];
        }
      }

      $out_arr = [];
      exec("drush $alias --uri=$uri sqlq 'select count(*) from users;'", $out_arr);
      $entities_info[$uri]['entities']['user']['total'] = $out_arr[0];


      $out_arr = [];
      exec("drush $alias --uri=$uri sqlq 'select type, count(*) from file_managed group by type;'", $out_arr);

      $entities_info[$uri]['entities']['file'] = [];
      foreach ($out_arr as $r) {
        $l = explode("\t", $r);
        if (isset($l[1])) {
          $entities_info[$uri]['entities']['file'][$l[0]] = $l[1];
        }
      }


      $count++;
    }

    file_put_contents($entities_file, json_encode($entities_info, JSON_PRETTY_PRINT));
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $entities_file)), LogLevel::OK);
  }

  $entities = json_decode(file_get_contents($entities_file), TRUE);
  return $entities;
}

/**
 * Get information about views used on each site.
 */
function drush_acsf_tools_analyze_get_site_views($rebuild = FALSE) {
  $sites = drush_acsf_tools_analyze_get_sites_preferred();
  $preferred_domains_count = count($sites);
  $views_file = drush_acsf_tools_analyze_get_file_path('views.json');
  $alias = drush_acsf_tools_analyze_get_alias();
  $info = drush_acsf_tools_analyze_get_info();

  if (!file_exists($views_file) || $rebuild) {
    drush_log(dt("File %s does not exist. Creating it.", array('%s' => $views_file)), LogLevel::NOTICE);

    $views_info = [];
    $count = 1;
    foreach ($sites as $uri => $site) {
      $arg = array(
        '%s' => $uri,
        '%i' => $count,
        '%t' => $preferred_domains_count
      );
      drush_log(dt("Getting views of site %s (%i of %t).", $arg), LogLevel::OK); // @TODO review log level.

      if ($info['drupal-major-version'] == 8) {
        $out_arr = [];
        exec("drush @$alias --uri=$uri vl --format=json", $out_arr);
        $out = implode($out_arr);

        // @TODO check output.

        $views_info[$uri]['views'] = json_decode($out, TRUE);
      }
      elseif ($info['drupal-major-version'] == 7) {
        $out_arr = [];
        exec("drush @$alias --uri=$uri sqlq 'select type, count(*) from block_content group by type;'", $out_arr);

        $entities_info[$uri]['entities']['view'] = [];
        foreach ($out_arr as $r) {
          $l = explode("\t", $r);
          if (isset($l[1])) {
            $entities_info[$uri]['entities']['view'][$l[0]] = $l[1];
          }
        }
      }

      $count++;
    }

    file_put_contents($views_file, json_encode($views_info, JSON_PRETTY_PRINT));
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $views_file)), LogLevel::OK);
  }

  $views = json_decode(file_get_contents($views_file), TRUE);
  return $views;
}

/**
 * Get status for each site.
 */
function drush_acsf_tools_analyze_get_site_statuses($rebuild = FALSE) {
  $sites = drush_acsf_tools_analyze_get_sites_preferred();
  $preferred_domains_count = count($sites);
  $status_file = drush_acsf_tools_analyze_get_file_path('status.json');
  $alias = drush_acsf_tools_analyze_get_alias();

  if (!file_exists($status_file) || $rebuild) {
    drush_log(dt("File %s does not exist. Creating it.", array('%s' => $status_file)), LogLevel::NOTICE);

    $count = 1;
    foreach ($sites as $uri => $site) {
      $arg = array(
        '%s' => $uri,
        '%i' => $count,
        '%t' => $preferred_domains_count
      );
      drush_log(dt("Getting status of site %s (%i of %t).", $arg), LogLevel::OK); // @TODO review log level.

      $out_arr = [];
      exec("drush @$alias --uri=$uri status --format=json --fields=drupal-version,install-profile,uri,theme", $out_arr);
      $out = implode($out_arr);

      // @TODO check output.

      $sites[$uri]['status'] = json_decode($out, TRUE);

      drush_log(dt("Getting module list of site %s (%i of %t).", $arg), LogLevel::OK); // @TODO review log level.
      $out_arr = [];
      exec("drush @$alias --uri=$uri pml --format=json", $out_arr);
      $out = implode($out_arr);

      // @TODO check output.

      $sites[$uri]['modules'] = json_decode($out, TRUE);

      $count++;
    }

    file_put_contents($status_file, json_encode($sites, JSON_PRETTY_PRINT));
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $status_file)), LogLevel::OK);
  }

  $sites = json_decode(file_get_contents($status_file), TRUE);

  return $sites;
}

/**
 * Get custom site domains from sites.json.
 */
function drush_acsf_tools_analyze_get_sites_custom() {
  $sites = drush_acsf_tools_analyze_get_sites();
  $acsf_domain_suffix = 'acsitefactory.com';

  foreach ($sites as $uri => $site) {
    if (substr($uri, -strlen($acsf_domain_suffix)) === $acsf_domain_suffix) {
      unset($sites[$uri]);
    }
  }

  return $sites;
}

/**
 * Get preferred site domains from sites.json.
 */
function drush_acsf_tools_analyze_get_sites_preferred() {
  $sites = drush_acsf_tools_analyze_get_sites();

  foreach ($sites as $uri => $site) {
    if (!isset($site['flags']) || !isset($site['flags']['preferred_domain']) || $site['flags']['preferred_domain'] !== true) {
      unset($sites[$uri]);
    }
  }

  return $sites;
}

/**
 * Get sites info from sites.json.
 */
function drush_acsf_tools_analyze_get_sites() {
  $sites_file = drush_acsf_tools_analyze_get_file_path('sites.json');
  $alias = drush_acsf_tools_analyze_get_alias();

  if (!file_exists($sites_file)) {
    drush_log(dt("File %s does not exist. Creating it.", array('%s' => $sites_file)), LogLevel::NOTICE);

    drush_shell_exec("drush @$alias php-eval 'print(file_get_contents(gardens_site_data_get_filepath()))' > $sites_file");
  }

  $sites = json_decode(file_get_contents($sites_file), TRUE)['sites'];

  return $sites;
}

/**
 * Get basic info (drush status without specifying a site).
 */
function drush_acsf_tools_analyze_get_info() {
  $info_file = drush_acsf_tools_analyze_get_file_path('info.json');
  $info = json_decode(file_get_contents($info_file), TRUE);

  if (strpos($info['drupal-version'], '7') === 0) {
    $info['drupal-major-version'] = 7;
  }
  elseif (strpos($info['drupal-version'], '8') === 0) {
    $info['drupal-major-version'] = 8;
  }

  return $info;
}

/**
 * Get full file path.
 */
function drush_acsf_tools_analyze_get_file_path($file_name) {
  $alias = drush_acsf_tools_analyze_get_alias();
  return drush_acsf_tools_analyze_get_dir() . '/' . $alias . '_' . $file_name;
}

/**
 * Get alias.
 */
function drush_acsf_tools_analyze_get_alias() {
  $arguments = drush_get_arguments();
  return trim($arguments[1], '@');
}

/**
 * Get path to folder which should contain output files.
 */
function drush_acsf_tools_analyze_get_dir() {
  $out = drush_get_option('out');
  if (!$out) {
    $alias_name = drush_acsf_tools_analyze_get_alias();
    $out = "./$alias_name";
  }

  return $out;
}

/**
 * Action callback for acsf-tools-analyze command.
 */
function drush_acsf_tools_analyze() {
  $format = drush_get_option('format');

  $rebuild = drush_get_option('rebuild');
  if (!$rebuild) {
    $rebuild = FALSE;
  }
  $rebuild_csv = drush_get_option('rebuild-csv');
  if (!$rebuild_csv) {
    $rebuild_csv = FALSE;
  }

  $types = drush_get_option('types');
  if (!$types) {
    $types = 'modules,views,entities';
  }
  $types = array_flip(explode(',', $types));

  if (isset($types['sites'])) {
    $statuses = drush_acsf_tools_analyze_get_site_statuses($rebuild);

    if ($statuses) {
      drush_log(dt("Site and module statuses present."), LogLevel::OK);
    }

    if ($format == 'csv') {
      $r = drush_acsf_tools_analyze_get_sites_csv($statuses, $rebuild_csv);
    }
  }

  if (isset($types['modules']) && $format == 'csv') {
    $statuses = drush_acsf_tools_analyze_get_site_statuses($rebuild);

    if ($statuses) {
      drush_log(dt("Site and module statuses present."), LogLevel::OK);
    }

    $r = drush_acsf_tools_analyze_get_modules_csv($statuses, $rebuild_csv);
  }

  if (isset($types['views'])) {
    $views = drush_acsf_tools_analyze_get_site_views($rebuild);

    if ($views) {
      drush_log(dt("Views information present."), LogLevel::OK);
    }

    if ($format == 'csv') {
      $r = drush_acsf_tools_analyze_get_views_csv($views, $rebuild_csv);
    }
  }

  if (isset($types['entities'])) {
    $entities = drush_acsf_tools_analyze_get_site_entities($rebuild);

    if ($entities) {
      drush_log(dt("Entities information present."), LogLevel::OK);
    }

    if ($format == 'csv') {
      $r = drush_acsf_tools_analyze_get_entities_csv($entities, $rebuild_csv);
    }
  }
}

/**
 * Get CSV formatted information about entities.
 */
function drush_acsf_tools_analyze_get_entities_csv($entities_info, $rebuild = FALSE) {
  $entities_csv = drush_acsf_tools_analyze_get_file_path('entities.csv');

  if (!file_exists($entities_csv) || $rebuild) {
    $results = [];
    $results_csv_arr = [];

    foreach ($entities_info as $uri => $index) {
      foreach ($entities_info[$uri]['entities'] as $entity_type => $entity_info) {
        foreach ($entity_info as $type => $count) {
          $m = $entity_type . '-' . $type;

          if (!isset($results[$m])) {
            $results[$m]['name'] = $type;
            $results[$m]['type'] = $entity_type;
            $results[$m]['counts'] = [];
          }

          $results[$m]['counts'][$uri] = $count;
        }
      }
    }

    $results_csv_arr[0] = [
      'Name',
      'Type',
    ];

    foreach($entities_info as $uri => $i) {
      $results_csv_arr[0][] = $uri;
    }

    $k = 0;
    foreach ($results as $i => $v) {
      $row_arr = [];
      $row_arr[] = $v['name'];
      $row_arr[] = $v['type'];

      foreach($entities_info as $uri => $index) {
        if (isset($v['counts'][$uri])) {
          $row_arr[] = $v['counts'][$uri];
        }
        else {
          $row_arr[] = '';
        }
      }

      $results_csv_arr[] = $row_arr;
      $k++;
    }

    foreach ($results_csv_arr as $i => $v) {
      $results_csv_arr[$i] = implode(',', $v);
    }

    $results_csv = implode("\n", $results_csv_arr);

    file_put_contents($entities_csv, $results_csv);
    drush_log(dt("File %s created.", array('%s' => $entities_csv)), LogLevel::OK);
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $entities_csv)), LogLevel::OK);
  }

  return file_get_contents($entities_csv);
}

/**
 * Get CSV formatted information about views.
 */
function drush_acsf_tools_analyze_get_views_csv($views_info, $rebuild = FALSE) {
  $views_csv = drush_acsf_tools_analyze_get_file_path('views.csv');

  if (!file_exists($views_csv) || $rebuild) {
    $results = [];
    $results_csv_arr = [];

    foreach ($views_info as $uri => $info) {
      $site = $views_info[$uri];

      if (!is_array($views_info[$uri]['views'])) continue;

      foreach ($views_info[$uri]['views'] as $view) {
        if (isset($view['machine-name'])) {
          $m = $view['machine-name'];
        }
        elseif (isset($view['name'])) {
          $m = $view['name'];
        }
        else {
          var_dump($view); // @TODO handle this nicely.
          continue;
        }

        if (!isset($results[$m])) {
          $results[$m]['name'] = $m;
          $results[$m]['type'] = 'view';
          $results[$m]['statuses'] = [];
        }

        $results[$m]['statuses'][$uri] = $view['status'];
      }

    }

    $results_csv_arr[0] = [
      'Name',
      'Type',
    ];

    foreach($views_info as $uri => $i) {
      $results_csv_arr[0][] = $uri;
    }

    $k = 0;
    foreach ($results as $i => $v) {
      $row_arr = [];
      $row_arr[] = $v['name'];
      $row_arr[] = $v['type'];

      foreach($views_info as $uri => $index) {
        if (isset($v['statuses'][$uri])) {
          $row_arr[] = $v['statuses'][$uri];
        }
        else {
          $row_arr[] = '';
        }
      }

      $results_csv_arr[] = $row_arr;
      $k++;
    }

    foreach ($results_csv_arr as $i => $v) {
      $results_csv_arr[$i] = implode(',', $v);
    }

    $results_csv = implode("\n", $results_csv_arr);

    file_put_contents($views_csv, $results_csv);
    drush_log(dt("File %s created.", array('%s' => $views_csv)), LogLevel::OK);
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $views_csv)), LogLevel::OK);
  }

  return file_get_contents($views_csv);
}

/**
 * Get CSV formatted information about modules used.
 */
function drush_acsf_tools_analyze_get_modules_csv($sites, $rebuild = FALSE) {
  $modules_csv = drush_acsf_tools_analyze_get_file_path('modules.csv');

  if (!file_exists($modules_csv) || $rebuild) {
    $results = [];
    $results_csv_arr = [];

    $results_csv_arr[0] = [
      'Name',
      'Version',
      'Type',
    ];

    foreach($sites as $uri => $i) {
      $results_csv_arr[0][] = $uri;
    }

    foreach ($sites as $uri => $index) {
      $site = $sites[$uri];

      if (!is_array($site['modules'])) {
        continue;
      }

      foreach ($site['modules'] as $module => $module_info) {
        $m = $module . '-' . $module_info['version'];

        if (!isset($results[$m])) {
          $results[$m] = $module_info;
          unset($results[$m]['status']);
          $results[$m]['name'] = $module;
          $results[$m]['type'] = 'module/theme';
          $results[$m]['statuses'] = [];
        }
        $results[$m]['statuses'][$uri] = $module_info['status'];
      }

    }

    $k = 0;
    foreach ($results as $i => $v) {
      $row_arr = [];
      $row_arr[] = $v['name'];
      $row_arr[] = $v['version'];
      $row_arr[] = $v['type'];

      foreach($sites as $uri => $index) {
        if (isset($v['statuses'][$uri])) {
          $row_arr[] = $v['statuses'][$uri];
        }
        else {
          $row_arr[] = '';
        }
      }

      $results_csv_arr[] = $row_arr;
      $k++;
    }

    foreach ($results_csv_arr as $i => $v) {
      $results_csv_arr[$i] = implode(',', $v);
    }

    $results_csv = implode("\n", $results_csv_arr);

    file_put_contents($modules_csv, $results_csv);
    drush_log(dt("File %s created.", array('%s' => $modules_csv)), LogLevel::OK);
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $modules_csv)), LogLevel::OK);
  }

  return file_get_contents($modules_csv);
}

/**
 * Get CSV formatted information for sites.
 */
function drush_acsf_tools_analyze_get_sites_csv($sites, $rebuild = FALSE) {
  $status_file = drush_acsf_tools_analyze_get_file_path('sites.csv');

  if (!file_exists($status_file) || $rebuild) {
    $results_csv_arr = [];

    $results_csv_arr[0] = [
      'URI',
      'Drupal version',
      'Installation profile',
      'Theme',
    ];

    $k = 0;
    foreach ($sites as $uri => $index) {
      $row_arr = [];
      $row_arr[] = $uri;
      $row_arr[] = $sites[$uri]['status']['drupal-version'];
      $row_arr[] = $sites[$uri]['status']['install-profile'];
      $row_arr[] = $sites[$uri]['status']['theme'];

      $results_csv_arr[] = $row_arr;
      $k++;
    }

    foreach ($results_csv_arr as $i => $v) {
      $results_csv_arr[$i] = implode(',', $v);
    }

    $results_csv = implode("\n", $results_csv_arr);

    file_put_contents($status_file, $results_csv);
    drush_log(dt("File %s created.", array('%s' => $status_file)), LogLevel::OK);
  }
  else {
    drush_log(dt("File %s exists.", array('%s' => $status_file)), LogLevel::OK);
  }

  return file_get_contents($status_file);
}
