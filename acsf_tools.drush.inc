<?php
/**
 * @file
 * Utility commands for ACSF sites.
 */

/**
 * Implements hook_drush_command().
 */
function acsf_tools_drush_command() {
  $items = array();

  $items['acsf-tools-list'] = array(
    'description' => dt('List the sites of the factory.'),
    'options' => array(
      'fields' => 'The list of fields to display (comma separated list).',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => array(
      'drush acsf-tools-list' => 'Get all details for all the sites of the factory.',
      'drush acsf-tools-list --fields' => 'Get prefix for all the sites of the factory.',
      'drush acsf-tools-list --fields=name,domains' => 'Get prefix, name and domains for all the sites of the factory.',
    ),
    'aliases' => ['sfl'],
  );

  $items['acsf-tools-info'] = array(
    'description' => dt('List details for each site in the Factory.'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => array(
      'drush acsf-tools-info' => 'Get more details for all the sites of the factory.',
    ),
    'aliases' => ['sfi'],
  );

  $items['acsf-tools-ml'] = array(
    'description' => dt('Runs the passed drush command against all the sites of the factory (ml stands for multiple -l option).'),
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => array(
      'drush8 acsf-tools-ml vget site_mail' => 'Get value of site_mail variable for all the sites.',
      'drush8 acsf-tools-ml sqlq "select status from system where name=\'php\'"' => 'Check status of php module on all the sites.',
      'drush8 acsf-tools-ml cron --profiles=standard,custom' => "Execute command on specific profiles"
    ),
    'aliases' => ['sfml'],
  );

  $items['acsf-tools-dump'] = array(
    'description' => dt('Make a DB dump for each site of the factory).'),
    'options' => array(
      'result-folder' => 'The folder in which the dumps will be written. Defaults to ~/drush-backups.',
    ),
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => array(
      'drush8 acsf-tools-dump' => 'Create DB dumps for the sites of the factory. Default result folder will be used.',
      'drush8 acsf-tools-dump --result-folder=/home/project/backup/20160617' => 'Create DB dumps for the sites of the factory and store them in the specified folder. If folder does not exist the command will try to create it.',
      'drush8 acsf-tools-dump --result-folder=/home/project/backup/20160617 --gzip' => 'Same as above but using options of sql-dump command.',
      'drush8 acsf-tools-dump --result-folder=/home/project/backup/20160617 --gzip --no-prompt' => 'Create DB dumps for the sites forcefully without any prompt message needed for confirmation on all the sites of your factory.',
    ),
    'aliases' => ['sfdu'],
  );

  $items['acsf-tools-restore'] = [
    'description' => dt('Restore a DB dump for each site of the factory.'),
    'options' => [
      'source-folder' => 'The folder in which the dumps will be picked-up. Default to ~/drush-backups.',
      'gzip' => 'Indicate the dump have been compressed using gzip. See acsf-tools-dump options.',
    ],
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => [
      'drush8 acsf-tools-restore' => 'Restore DB dumps for the sites of the factory. Default source folder will be used.',
      'drush8 acsf-tools-restore --source-folder=/home/project/backup/20160617' => 'Restore DB dumps for the sites of the factory using files from the specified folder.',
      'drush8 acsf-tools-restore --source-folder=/home/project/backup/20160617 --gzip' => 'Same as above but using compressed DB dumps.',
      'drush8 acsf-tools-restore --source-folder=/home/project/backup/20160617 --gzip --no-prompt' => 'Restore DB dumps forcefully without any prompt message needed for confirmation on all the sites of your factory.',
    ],
    'aliases' => ['sfre'],
  ];

  return $items;
}

/**
 * Action callback for acsf-tools-list command.
 */
function drush_acsf_tools_list() {
  // Look for list of sites and loop over it.
  if ($sites = _drush_acsf_tools_get_sites()) {
    // Render the info.
    $fields = drush_get_option('fields');
    if (isset($fields)) {
      $expected_attributes = array_flip(explode(',', $fields));
    }

    foreach ($sites as $name => $details) {
      $prefix = $details['machine_name'];
      drush_print($prefix);

      // Filter attributes.
      if (isset($expected_attributes)) {
        $details = array_intersect_key($details, $expected_attributes);
      }

      // Print attributes.
      _drush_acsf_tools_recursive_print($details, 2);
    }
  }
}

/**
 * Action callback for acsf-tools-info command.
 */
function drush_acsf_tools_info() {
  if (!is_this_acsf_platform()) {
    $sites = _drush_acsf_tools_get_multisite_sites();
    drush_print("\nID\t\tName\t\tDB Name\t\t\t\tDomain\n");

    foreach ($sites as $key => $site) {
      drush_print("$key\t\t" . $site['conf']['gname'] . "\t\t" . $site['conf']['db_name'] . "\t\t" . $site['domains'][0]);
    }

    return;
  }

  // Look for list of sites and loop over it.
  if (($map = gardens_site_data_load_file()) && isset($map['sites'])) {
    // Acquire sites info.
    $sites = array();
    foreach ($map['sites'] as $domain => $site_details) {
      $conf = $site_details['conf'];

      // Include settings file to get DB name. To save rescources, without bootsrtapping Drupal
      $settings_inc = "/var/www/site-php/{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}/D7-{$_ENV['AH_SITE_ENVIRONMENT']}-" . $conf['gardens_db_name'] . "-settings.inc";
      $file = file_get_contents($settings_inc);
      $need= "\"database\" => \"";
      $need2= "\",";
      // Find db name
      $dpos = strpos($file, $need);
      $db_name = substr($file, ($dpos + strlen($need)) );
      $upos = strpos($db_name, $need2);
      // Isolate db name
      $db_name = substr($db_name, 0, $upos );

      // Re-structure  site
      $sites[$conf['gardens_site_id']]['domains'][] = $domain;
      $sites[$conf['gardens_site_id']]['conf'] = array('db_name' => $db_name, 'gname' => $conf['gardens_db_name'], );
    }
  }
  else {
    drush_log("\nFailed to retrieve the list of sites of the factory.", 'error');
  }

  drush_print("\nID\t\tName\t\tDB Name\t\t\t\tDomain\n");

  foreach ($sites as $key => $site) {
    drush_print("$key\t\t" . $site['conf']['gname'] . "\t\t" . $site['conf']['db_name'] . "\t\t" . $site['domains'][0]);
  }
}

/**
 * Validate callback for acsf-tools-ml command.
 */
function drush_acsf_tools_ml_validate() {
  $arguments = drush_get_arguments();
  if (count($arguments) == 1) {
    return drush_set_error('WRONG_PARAMETERS', dt('This command expects at least one parameter.'));
  }
}

/**
 * Action callback for acsf-tools-ml command.
 */
function drush_acsf_tools_ml() {

  // Look for list of sites and loop over it.
  if ($sites = _drush_acsf_tools_get_sites()) {
    $arguments = drush_get_arguments();
    unset($arguments[0]);
    $command = array_shift($arguments);

    $options = drush_get_context('cli');
    unset($options['php']);
    unset($options['php-options']);

    $profiles = $options['profiles'] ?? NULL;
    $profiles = $profiles ? explode(',', $profiles) : NULL;
    unset($options['profiles']);

    foreach ($sites as $details) {
      // Get the first custom domain if any. Otherwise use the first domain
      // which is *.acsitefactory.com. Given this is used as --uri parameter
      // by the drush command, it can have an impact on the drupal process.
      $domain = $details['domains'][1] ?? $details['domains'][0];

      // If specific profile is provided, we check if it is the site's profile.
      // We first try to get site's profile from the settings file. If we can't
      // find the settings file, we use drush core-status to get it.
      if (!empty($profiles)) {
        $proceed = TRUE;

        $site_settings_filepath = 'sites/g/files/' . $details['name'] . '/settings.php';
        if (file_exists($site_settings_filepath)) {
          $site_settings = @file_get_contents($site_settings_filepath);
          if (preg_match("/'install_profile'] = '([a-zA-Z_]*)'/", $site_settings, $matches)) {
            if (isset($matches[1]) && !in_array($matches[1], $profiles)) {
              $proceed = FALSE;
            }
          }
        }
        else {
          $profile_options = $options;
          $profile_options['fields'] = 'install-profile';
          $profile_options['l'] = $domain;
          $site_profile_info = drush_invoke_process('@self', 'core-status', [], $profile_options, ['output' => FALSE]);
          if (!in_array($site_profile_info['object']['install-profile'], $profiles)) {
            $proceed = FALSE;
          }
        }

        if (!$proceed) {
          drush_log("\n=> Skipping command on $domain", 'ok');
          continue;
        }
      }

      drush_log("\n=> Running command on $domain", 'ok');
      drush_invoke_process('@self', $command, $arguments, $options + array('l' => $domain));
    }
  }
}

/**
 * Action callback for acsf-tools-dump command.
 */
function drush_acsf_tools_dump() {
  // Ask for confirmation before running the command.
  if (!_drush_acsf_tools_prompt_confirm()) {
    return;
  }

  // Identify target folder.
  $result_folder = drush_get_option('result-folder');
  if (!isset($result_folder)) {
    $result_folder = '~/drush-backups';
  }

  if (!is_dir($result_folder) || !is_writable($result_folder)) {
    // Target folder does not exist. Try to create it.
    if (!mkdir($result_folder, 0777, TRUE)) {
      drush_log("\nImpossible to write to $result_folder folder.", 'error');
      return;
    }
  }

  // Look for list of sites and loop over it.
  if ($sites = _drush_acsf_tools_get_sites()) {
    $arguments = drush_get_arguments();
    $command = 'sql-dump';

    $options = drush_get_context('cli');
    unset($options['php']);
    unset($options['php-options']);
    unset($options['result-folder']);
    unset($options['no-prompt']);

    $processed = array();
    foreach ($sites as $details) {
      $domain = $details['domains'][0];
      $prefix = explode('.', $domain)[0];

      $options['result-file'] = $result_folder . '/' . $prefix . '.sql';

      drush_log("\n=> Running command on $domain", 'ok');
      drush_invoke_process('@self', $command, $arguments, $options + array('l' => $domain));
    }
  }
}

/**
 * Action callback for acsf-tools-restore command.
 */
function drush_acsf_tools_restore() {
  // Ask for confirmation before running the command.
  if (!_drush_acsf_tools_prompt_confirm()) {
    return;
  }

  // Identify source folder.
  $source_folder = drush_get_option('source-folder');
  if (!isset($source_folder)) {
    $source_folder = '~/drush-backups';
  }

  if (!is_dir($source_folder)) {
    // Source folder does not exist.
    drush_log("\nSource folder $source_folder does not exist.", 'error');
    return;
  }

  $gzip = drush_get_option('gzip', FALSE);

  // Look for list of sites and loop over it.
  if ($sites = _drush_acsf_tools_get_sites()) {
    $arguments = drush_get_arguments();

    $options = drush_get_context('cli');
    unset($options['php']);
    unset($options['php-options']);
    unset($options['source-folder']);
    unset($options['gzip']);
    unset($options['no-prompt']);

    foreach ($sites as $details) {
      $domain = $details['domains'][0];
      $prefix = explode('.', $domain)[0];

      $source_file = $source_folder . '/' . $prefix . '.sql';

      if ($gzip) {
        $source_file .= '.gz';
      }

      if (!file_exists($source_file)) {
        drush_log("\n => No source file $source_file for $prefix site.", "error");
        continue;
      }

      // Temporary decompress the dump to be used with drush sql-cli.
      if ($gzip) {
        drush_shell_exec('gunzip -k ' . $source_file);
        $source_file = substr($source_file, 0, -3);
      }

      drush_log("\n=> Dropping and restoring database on $domain", 'ok');
      $result = drush_invoke_process('@self', 'sql-connect', $arguments, $options + ['l' => $domain], ['output' => FALSE]);
      if (!empty($result['object'])) {
        drush_invoke_process('@self', 'sql-drop', $arguments, $options + ['l' => $domain, 'y']);
        drush_shell_exec($result['object'] . ' < ' . $source_file);
      }

      // Remove the temporary decompressed dump
      if ($gzip) {
        drush_shell_exec('rm ' . $source_file);
      }
    }
  }
}

/**
 * Checking if we are on an ACSF platform.
 *
 * @return bool
 */
function is_this_acsf_platform() {
  // Check if the current environment is an ACSF platform.
  return function_exists('gardens_site_data_load_file');
}

/**
 * Utility function to retrieve sites information.
 *
 * @return array|bool
 */
function _drush_acsf_tools_get_sites() {
  if (is_this_acsf_platform()) {
    return _drush_acsf_tools_get_acsf_sites();
  }

  return _drush_acsf_tools_get_multisite_sites();
}

function _drush_acsf_tools_get_acsf_sites() {
  $sites = FALSE;

  // Look for list of sites and loop over it.
  if (($map = gardens_site_data_load_file()) && isset($map['sites'])) {
    // Acquire sites info.
    $sites = array();
    foreach ($map['sites'] as $domain => $site_details) {
      if (!isset($sites[$site_details['name']])) {
        $sites[$site_details['name']] = $site_details;
      }

      // Path domains need a trailing slash to be recognized as a drush alias.
      if (FALSE !== strpos($domain, '/')) {
        $domain = rtrim($domain, '/') . '/';
      }

      $sites[$site_details['name']]['domains'][] = $domain;

      // Identify the site machine name from the acsitefactory.com domain.
      $machine_name = [];
      if (preg_match('/(.*)\..*\.acsitefactory\.com/', $domain, $machine_name)) {
        $sites[$site_details['name']]['machine_name'] = $machine_name[1];
      }
    }
  }
  else {
    drush_log("\nFailed to retrieve the list of sites of the factory.", 'error');
  }

  return $sites;
}

function _drush_acsf_tools_get_multisite_sites() {
  // Read information from the sites.php file inside `/sites` directory.
  // @todo: Change this to proper file where data is available.
  $multisite_directory_path = DRUPAL_ROOT . '/sites';
  $multisite_file_path = $multisite_directory_path.'/sites.php';

  if (!is_file($multisite_file_path)) {
    throw new \RuntimeException("Cannot find $multisite_file_path");
  }

  require $multisite_file_path;

  if (!isset($sites) || !is_array($sites)) {
    throw new \RuntimeException("Multi-site not defined in $multisite_file_path");
  }

  $site_details = [];
  foreach ($sites as $site_name => $site_path) {
    // If site path already exists, just add the domain to the list of domains.
    if (!empty($site_details[$site_path])) {
      $site_details[$site_path]['domains'][] = $site_name;
      continue;
    }

    $site_details[$site_path] = [
      'name' => $site_path,
      'domains' => [
        $site_name,
      ],
      'flags' => [],
      'conf' => [
        // These below array maintained same as ACSF sites.
        'db_name' => $site_path, // Default to site name as DB name.
        'site_id' => $site_path, // Default to site name as site ID.
        'gardens_site_id' => $site_path, // Default to site name as gardens site ID.
        'gardens_db_name' => $site_path, // Default to site name as gardens DB name.
     ],
      'machine_name' => $site_path, // Default to site name as machine name.
    ];
  }

  return $site_details;
}

/**
 * Utility function to print arrays.
 *
 * @param $variable
 * @param $indent
 */
function _drush_acsf_tools_recursive_print($variable, $indent) {
  foreach ($variable as $key => $value) {
    if (!is_array($value)) {
      drush_print($key . ': ' . $value, $indent);
    }
    else {
      drush_print($key . ':', $indent);
      _drush_acsf_tools_recursive_print($value, $indent + 2);
    }
  }
}

/**
 * Utility function to ask for confirmation.
 *
 * @return bool
 */
function _drush_acsf_tools_prompt_confirm() {
  // no-prompt option added to skip the confirmation command.
  $no_prompt = drush_get_option('no-prompt', FALSE);
  if ($no_prompt) {
    return TRUE;
  }

  // Ask for confirmation before running the command.
  // Special care for -y option to avoid drush_prompt default behaviour.
  $yes = drush_get_context('DRUSH_AFFIRMATIVE');
  if ($yes) {
    drush_set_context('DRUSH_AFFIRMATIVE', FALSE);
  }

  $input = drush_prompt(
    dt('You are about to run a command on all the sites of your factory.
Do you confirm you want to do that? If yes, type \'ok\'')
  );
  if ($input != 'ok') {
    return FALSE;
  }

  if ($yes) {
    drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
  }

  return TRUE;
}
