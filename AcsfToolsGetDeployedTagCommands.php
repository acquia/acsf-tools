<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Commands\acsf_tools\AcsfToolsCommands;

/**
 * A Drush commandfile.
 */
class AcsfToolsGetDeployedTagCommands extends AcsfToolsCommands {

  /**
   * Fetches and displays the currently deployed sites tag for a Factory.
   *
   * @command acsf:tools-get-deployed-tag
   *
   * @bootstrap full
   * @param $env
   *   The environment whose tag we're requesting. I.e., dev, test, prod
   * @usage drush @mysite.local acsf-get-deployed-tag dev
   *
   * @aliases sft,acsf-tools-get-deployed-tag
   */
  public function getDeployedTag($env) {

    $utils = $this->utils;

    if (!in_array($env, array('dev','test','prod'))) {
      return $this->logger()->error('Invalid Factory environment.');
    }

    $config = $utils->getRestConfig();

    $sites_url = $utils->getFactoryUrl($config, '/api/v1/vcs?type=sites', $env);

    $response = $utils->curlWrapper($config->username, $config->password, $sites_url);
    $this->output()->writeln($response->current);
  }
}