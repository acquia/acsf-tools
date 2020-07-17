<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Commands\acsf_tools\AcsfToolsUtils;

/**
 * Manage deployment statuses.
 */
class AcsfToolsDeploymentStatus extends AcsfToolsUtils {

  const DEPLOYMENT_PAUSED = TRUE;
  const DEPLOYMENT_RESUMED = FALSE;

  /**
   * Monitor deployment status and pause/enable depending on $theshold.
   *
   * @command acsf-tools:monitor-deployment-status
   *
   * @usage acsf-tools:monitor-deployment-status
   *
   */
  public function monitorDeploymentJobs($theshold) {

    if ($this->isDeploymentHappening()) {
      if ($this->getBackgroundTasksSitesStatus() > $theshold) {
        $this->setStatus($this::DEPLOYMENT_PAUSED);
      }
      else {
        // Resume the deployment.
        $this->setStatus($this::DEPLOYMENT_RESUMED);
      }
    }
  }

  /**
   * Find if a deployment is currently ongoing.
   *
   * @return bool
   */
  public function isDeploymentHappening() {
    $status = $this->getStatus();

    return TRUE;
  }

  /**
   * Fetches and displays the currently deployed sites tag for a Factory.
   *
   * @command acsf:tools-get-status
   *
   *
   * @aliases sfgst,acsf-tools-get-status
   *
   */
  public function getStatus() {
    $config = $this->getRestConfig();

    $sites_url = $this->getFactoryUrl($config, '/api/v1/status');

    $response = $this->curlWrapper($config->username, $config->password, $sites_url);
    return $response->current;
  }

  /**
   * Resume/pause the processing.
   *
   * @param BOOLEAN $status
   *
   * @return mixed
   */
  public function setStatus($status) {
    $config = $this->getRestConfig();

    $sites_url = $this->getFactoryUrl($config, '/api/v1/update/pause');

    $post_data = array(
      'paused' => $status,
    );

    $response = $this->curlWrapper($config->username, $config->password, $sites_url, $post_data);
    return $response->current;
  }

}
