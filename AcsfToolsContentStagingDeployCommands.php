<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Commands\acsf_tools\AcsfToolsUtils;
use Drush\Exceptions\UserAbortException;

/**
 * A Drush commandfile.
 */
class AcsfToolsContentStagingDeployCommands extends AcsfToolsUtils {

  /**
   * A command line utility for starting a Factory content staging deploy.
   *
   * @command acsf-tools:content-staging-deploy
   *
   * @bootstrap none
   * @param $env
   *   The target environment you are staging content to.
   * @param $sites
   *   A comma-delimited list of site aliases you wish to stage. Pass 'all' to stage all sites.
   * @usage Single site
   *   drush @mysite.local acsf-content-staging-deploy dev sitename
   * @usage Multiple sites
   *   drush @mysite.local acsf-content-staging-deploy dev sitename1,sitename2
   * @usage All sites
   *   drush @mysite.local acsf-content-staging-deploy dev all
   *
   * @aliases sfst,acsf-tools-content-staging-deploy
   */
  public function contentStagingDeploy($env, $sites) {

    // Bail if an invalid staging environment.
    if (!in_array($env, array('dev','test'))) {
      return $this->logger()->error(dt('Invalid staging environment.'));
    }

    // Ask/warn user about staging all sites.
    $stage_all_sites = FALSE;
    if ($sites == 'all') {
      $warning = 'Are you sure you want to stage ALL sites? **WARNING: Staging all sites in a factory can take a very long time.';
      if (!$this->io()->confirm(dt($warning))) {
        $this->logger()->info(dt('Ok, exiting.'));
        throw new UserAbortException();
      }
      $stage_all_sites = TRUE;
    }

    $config = $this->getRestConfig();

    // Get a list of sites in the prod factory.
    $prod_sites = $this->getRemoteSites($config, 'prod');

    // Walk the prod list looking for the alias(es) the user specified.
    $to_stage = array();
    $user_sites = explode(',', $sites);
    foreach ($prod_sites as $prod_site) {
      if ($stage_all_sites) {
        $to_stage[] = $prod_site->id;
      }
      else {
        // Search list of prod sites with the list of site names the user
        // provided.
        if (in_array($prod_site->site, $user_sites)) {
          $to_stage[] = $prod_site->id;
        }

        // TODO: Add group support.
      }
    }

    if (empty($to_stage)) {
      return $this->logger()->error(dt('No sites found. Exiting.'));
    }

    // TODO:  add support for electing not to stage the Factory.

    // Kick off a staging process with our list of site node IDs.
    $post_data = array(
      'to_env' => $env,
      'sites' => $to_stage,
    );

    $staging_endpoint = $this->getFactoryUrl($config, '/api/v1/stage');

    $result = $this->curlWrapper($config->username, $config->password, $staging_endpoint, $post_data);
    $this->output()->writeln($result->message);
  }
}
