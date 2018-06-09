<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Commands\acsf_tools\AcsfToolsUtils;

/**
 * A Drush commandfile.
 */
class AcsfToolsGetDeployedTagCommands extends AcsfToolsUtils {

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

    if (!in_array($env, array('dev','test','prod'))) {
      return $this->logger()->error('Invalid Factory environment.');
    }

    $config = $this->getRestConfig();

    $sites_url = $this->getFactoryUrl($config, '/api/v1/vcs?type=sites', $env);

    $response = $this->curlWrapper($config->username, $config->password, $sites_url);
    $this->output()->writeln($response->current);
  }
}