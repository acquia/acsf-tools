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
class AcsfToolsSitesBackupCommands extends AcsfToolsUtils {

  /**
   * A command line utility for backing up sites within a Factory.
   * Note this is a full Drush backup and includes files as well as the DB.
   *
   * @command acsf:tools-sites-backup
   *
   * @bootstrap full
   * @param $env
   *   The target environment you wish to run the backup in.
   * @param $sites
   *   A comma-delimited list of site aliases you wish to backup. Pass 'all' to backup all sites.
   * @usage Single site
   *   drush @mysite.local acsf-sites-backup dev sitename
   * @usage Multiple sites
   *   drush @mysite.local acsf-sites-backup dev sitename1,sitename2
   * @usage All sites
   *   drush @mysite.local acsf-sites-backup dev all
   *
   * @aliases sfb,acsf-tools-sites-backup
   */
  public function sitesBackup($env, $sites) {

    if (!in_array($env, array('dev','test','prod'))) {
      return $this->logger()->error('Invalid Factory environment.');
    }

    $config = $this->getRestConfig();

    // Ask/warn user about backing up all sites.
    $backup_all_sites = FALSE;
    if ($sites == 'all') {
      $warning = 'Are you sure you want to backup ALL sites?';
      if (!$this->io()->confirm(dt($warning))) {
        $this->logger()->notice(dt('Ok, exiting.'));
        throw new UserAbortException();
      }
      $backup_all_sites = TRUE;
    }

    // Get a list of sites in the prod factory.
    $factory_sites = $this->getRemoteSites($config, $env);

    // Walk the prod list looking for the alias(es) the user specified.
    $to_backup = array();
    $user_sites = explode(',', $sites);
    foreach ($factory_sites as $site) {
      if ($backup_all_sites) {
        $this->postSiteBackup($config, $site, $env);
      }
      else {
        // Search list of prod sites with the list of site names the user
        // provided.
        if (in_array($site->site, $user_sites)) {
          $this->postSiteBackup($config, $site, $env);
        }

        // TODO: Add group support.
      }
    }
  }

  private function postSiteBackup($config, $site, $env) {

    $backup_endpoint = $this->getFactoryUrl($config, "/api/v1/sites/$site->id/backup", $env);

    $post_data = array(
      'label' => $site->site . ' ' . date('m-d-Y g:i')
    );

    $result = $this->curlWrapper($config->username, $config->password, $backup_endpoint, $post_data);
    $this->output()->writeln(dt("Backup started for site $site->site."));
  }
}
