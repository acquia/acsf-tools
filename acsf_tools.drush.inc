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

  $items['acsf-tools-ml'] = array(
    'description' => dt('Runs the passed drush command against all the sites of the factory (ml stands for multiple -l option).'),
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => array(
      'drush8 acsf-tools-ml vget site_mail' => 'Get value of site_mail variable for all the sites.',
      'drush8 acsf-tools-ml sqlq "select status from system where name=\'php\'"' => 'Check status of php module on all the sites.',
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
    ),
    'aliases' => ['sfdu'],
  );

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

    $processed = array();
    foreach ($sites as $details) {
      $domain = $details['domains'][0];

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
  $config->username = $yaml['rest_api_user'];
  $config->password = $yaml['rest_api_key'];
  $config->prod_uri = $yaml['rest_factories']['prod'];
  $config->test_uri = $yaml['rest_factories']['test'];
  $config->dev_uri = $yaml['rest_factories']['dev'];
  $config->root_domain = $yaml['root_domain'];
  $config->subdomain_pattern = $yaml['subdomain_pattern'];

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
