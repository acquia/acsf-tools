<?php
/**
 * @file
 * Utility commands for ACSF sites.
 */

use Symfony\Component\Yaml\Yaml;

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
      'drush8 acsf-tools-restore --source_folder=/home/project/backup/20160617' => 'Restore DB dumps for the sites of the factory using files from the specified folder.',
      'drush8 acsf-tools-restore --source_folder=/home/project/backup/20160617 --gzip' => 'Same as above but using compressed DB dumps.',
      'drush8 acsf-tools-restore --source_folder=/home/project/backup/20160617 --gzip --no-prompt' => 'Restore DB dumps forcefully without any prompt message needed for confirmation on all the sites of your factory.',
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
      // Get site prefix from main domain.
      $prefix = explode('.', $details['domains'][0])[0];
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

      if (!_drush_acsf_tools_is_site_available($details)) {
        drush_log("\n=> Skipping command on $domain as site is not ready yet", 'ok');
        continue;
      };

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
          drush_log("\n=> Skipping command on $domain as installation profile does not match", 'ok');
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
 * Utility function to retrieve locally stored REST API connection info.
 *
 * @return mixed
 */
function acsf_tools_get_rest_config() {

  $path = realpath(dirname(__FILE__));
  $yaml = Yaml::parse(file_get_contents($path . '/acsf_tools_config.yml'));
  if ($yaml === FALSE) {
    $error  = 'acsf_tools_config.yml not found. Make sure to copy/rename ';
    $error .= 'acsf_tools_config.default.yml and set the appropriate ';
    $error .= 'connection info.';
    drush_set_error(dt($error));
  }

  $config = new stdClass();
  $config->site_id = $yaml['site_id'];
  $config->username = $yaml['rest_api_user'];
  $config->password = $yaml['rest_api_key'];
  $config->prod_uri = $yaml['rest_factories']['prod'];
  $config->test_uri = $yaml['rest_factories']['test'];
  $config->dev_uri = $yaml['rest_factories']['dev'];
  $config->root_domain = $yaml['root_domain'];
  $config->subdomain_pattern = $yaml['subdomain_pattern'];
  $config->prod_web = $yaml['prod_web'];
  $config->dev_web = $yaml['dev_web'];

  return $config;
}

/**
 * Utility function to retrieve the correct factory URI given an environment and desired path.
 *
 * @param $config
 * @param string $path
 * @param string $env
 * @return string
 */
function acsf_tools_get_factory_url($config, $path = '', $env = 'prod') {

  switch ($env) {
    case 'dev':
      $factory_url = $config->dev_uri . $path;
      break;
    case 'test':
      $factory_url = $config->test_uri . $path;
      break;
    default:
      $factory_url = $config->prod_uri . $path;
      break;
  }

  return $factory_url;
}

/**
 * Helper script to abstract curl requests into a single function. Handles both
 * GET and POST, depending on whether $data is defined or not.
 *
 * @param $username
 * @param $password
 * @param $url
 * @param array $data
 * @return mixed
 */
function acsf_tools_curl_wrapper($username, $password, $url, $data = array()) {

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
  if (!empty($data)) {
    curl_setopt($ch, CURLOPT_POST, count($data));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  }
  $result = json_decode(curl_exec($ch));
  curl_close($ch);
  return $result;
}

/**
 * Utility function to retrieve sites information.
 *
 * @return array|bool
 */
function _drush_acsf_tools_get_sites() {
  $sites = FALSE;

  // Look for list of sites and loop over it.
  if (($map = gardens_site_data_load_file()) && isset($map['sites'])) {
    // Acquire sites info.
    $sites = array();
    foreach ($map['sites'] as $domain => $site_details) {
      if (!isset($sites[$site_details['name']])) {
        $sites[$site_details['name']] = $site_details;
      }
      $sites[$site_details['name']]['domains'][] = $domain;
    }
  }
  else {
    drush_log("\nFailed to retrieve the list of sites of the factory.", 'error');
  }

  return $sites;
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

/**
 * Determines whether or not a site is available based on its flags array.
 *
 * Restricted sites usually mean that an installation or another process is
 * working on the site, so need to skip those.
 *
 * @param array $data
 *   The ACSF site data array.
 *
 * @return bool
 *   True if site is available.
 */
function _drush_acsf_tools_is_site_available(array $data): bool {
  // Initialize variables.
  $site_available = TRUE;

  // Not available if access is restricted or site is under operation.
  if (_drush_acsf_tools_is_access_restricted($data) || _drush_acsf_tools_is_operation_blocked($data)) {
    $site_available = FALSE;
  }
  return $site_available;
}

/**
 * Determines whether or not a site is restricted.
 *
 * Restricted sites usually mean that an installation or another process is
 * working on the site, so need to skip those.
 *
 * @param array $data
 *   The ACSF site data array.
 *
 * @return bool
 *   True if access restriction is enabled.
 */
function _drush_acsf_tools_is_access_restricted(array $data): bool {
  if (array_key_exists('access_restricted', $data['flags'])) {
    if (isset($data['flags']['access_restricted']['enabled']) && $data['flags']['access_restricted']['enabled'] == 1) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Determines whether or not another process is using a site's data.
 *
 * The 'operation' key means that the site is in a usable state but a
 * process is using the site's data.
 *
 * @param array $data
 *   The ACSF site data array.
 *
 * @return bool
 *   True if the site's data is being used by another process.
 */
function _drush_acsf_tools_is_operation_blocked(array $data): bool {
  if (isset($data['flags']['operation']) && $data['flags']['operation'] == 'move') {
    return TRUE;
  }
  return FALSE;
}
