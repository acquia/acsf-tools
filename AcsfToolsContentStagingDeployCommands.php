<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Commands\acsf_tools\AcsfToolsUtils;
use Drush\Exceptions\CommandFailedException;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\InputOption;

/**
 * A Drush commandfile.
 */
class AcsfToolsContentStagingDeployCommands extends AcsfToolsUtils {

  /**
   * A command line utility for starting a Factory content staging deploy.
   *
   * Usage:
   * drush acsf-tools:content-staging-deploy <target-env> <site-name> [...options]
   *
   * @command acsf-tools:content-staging-deploy
   *
   * @bootstrap none
   * @param $env
   *   The target environment you are staging content to.
   * @param $sites
   *   A comma-delimited list of site aliases you wish to stage. Pass 'all' to stage all sites.
   * @option wipe-target
   *   Use this option to wipe the management console and all stacks on the selected environment before deploying sites.
   * @option wipe-stacks
   *   A comma-delimited list of stack ids to wipe. It will be ignored if --wipe-target is used.
   * @option skip-site-files
   *   Skip copying the staged down sites' files.
   * @option skip-site-files-overwrite
   *   A comma-delimited list of file patterns to skip copying during the stage down process. Ignored if --skip-site-files is not passed in.
   *
   * @usage Single site, only donwsync site's DB and files
   *   drush @mysite.local acsf-tools:content-staging-deploy dev sitename
   * @usage Single site, wipe destination environment
   *   drush @mysite.local acsf-tools:content-staging-deploy dev sitename --wipe-target
   * @usage Multiple sites
   *   drush @mysite.local acsf-tools:content-staging-deploy dev sitename1,sitename2
   * @usage All sites, wipe destination environment
   *   drush @mysite.local acsf-tools:content-staging-deploy dev all --wipe-target
   *
   * @aliases sfst,acsf-tools-content-staging-deploy
   */
  public function contentStagingDeploy($env, $sites, array $options = [
    'wipe-target' => FALSE,
    'wipe-stacks' => InputOption::VALUE_REQUIRED,
    'skip-site-files' => FALSE,
    'skip-site-files-overwrite' => InputOption::VALUE_REQUIRED,
  ]) {

    // Bail if an invalid staging environment.
    if (!in_array($env, array('dev','test'))) {
      return $this->logger()->error(dt('Invalid staging environment.'));
    }

    // Validate / normalize options passed in.
    $wipe_target = !empty($options['wipe-target']);
    $wipe_stacks = [];
    if (!empty($options['wipe-stacks'])) {
      $wipe_stacks = explode(",", $options['wipe_stacks']);
    }
    $skip_site_files = !empty($options['skip-site-files']);
    $skip_site_files_overwrite = [];
    if (!empty($options['skip-site-files-overwrite'])) {
      $skip_site_files_overwrite = explode(",", $options['skip-site-files-overwrite']);
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
      'wipe_target_environment' => $wipe_target,
      'wipe_stacks' => $wipe_stacks,
      'skip_site_files' => $skip_site_files,
      'skip_site_files_overwrite' => $skip_site_files_overwrite,
    );

    $staging_endpoint = $this->getFactoryUrl($config, '/api/v2/stage');

    $result = $this->curlWrapper($config->username, $config->password, $staging_endpoint, $post_data);
    $this->output()->writeln($result->message);
  }
}
