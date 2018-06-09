<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Commands\acsf_tools\AcsfToolsUtils;

/**
 * A Drush commandfile.
 */
class AcsfToolsStageDomainsCommands extends AcsfToolsUtils {

  /**
   * Automatically stage the production Factories' vanity domains to a lower environment.
   *
   * @command acsf:tools-stage-domains
   *
   * @bootstrap full
   * @param $env
   *   The environment we're staging domains to. E.g. dev\test
   * @usage drush @mysite.local acsf-stage-domains dev
   *
   * @aliases sfdo,acsf-tools-stage-domains
   */
  public function stageDomains($env) {

    if (!in_array($env, array('dev','test'))) {
      return $this->logger()->error('Invalid staging environment.');
    }

    $config = $this->getRestConfig();

    // Get Sites in the prod factory.
    $sites = $this->getRemoteSites($config, 'prod');

    foreach ($sites as $site) {

      $this->postVanityDomain($config, $site, $env);
    }
  }

  /**
   * Helper function to post a vanity domain to a staging environment.
   *
   * @param $config
   * @param $site
   * @param $env
   */
  private function postVanityDomain($config, $site, $env) {

    // Only do work if we detect there's a custom domain that needs staging.
    if ($stage_domain = $this->getStageDomain($config, $site, $env)) {

      // Post the domain via API.
      $data = array(
        'domain_name' => $stage_domain
      );
      $post_domain_url = $this->getFactoryUrl($config,"/api/v1/domains/$site->id/add", $env);
      // $result = $this->curlWrapper($config->username, $config->password, $post_domain_url, $data);

      $this->output->writeln("$stage_domain set OK.");
    }
  }

  /**
   * Utility function to parse and set the staging domain based on a user defined
   * pattern set in acsf_tools_config.yml.
   *
   * @param $config
   * @param $site
   * @param $env
   * @return string
   */
  private function getStageDomain($config, $site, $env) {

    // Set the url_env string to 'stage' if env is test.
    $url_env = ($env == 'test') ? 'stage' : $env;

    // Find the existing prod vanity domain.
    if ($prod_domain = $this->getProdVanityDomainForSite($config, $site)) {

      // Get the subdomain off the prod vanity url. E.g., 'www.foo.com'
      // is 'www'. Or, 'coolsite.domain.com' is 'coolsite'.
      $parts = explode('.', $prod_domain, 2);
      $subdomain = $parts[0];
      $prod_root_domain = $parts[1];

      if ($subdomain == 'www') {
        // If subdomain is www, then stage pattern should be dev.foo.com,
        // test.foo.com, etc.
        $new_subdomain = $url_env;
      } else {
        // If subdomain is custom, e.g., 'coolsite.foo.com', then follow
        // the pattern set in acsf_tools_config.yml - default is {subdomain}-{env},
        // e.g., coolsite-dev.foo.com.
        $new_subdomain = str_replace('{subdomain}', $subdomain, $config->subdomain_pattern);
        $new_subdomain = str_replace('{env}', $url_env, $new_subdomain);
      }

      return "$new_subdomain.$prod_root_domain";
    }

    return FALSE;
  }

  /**
   * Utility function to get the existing vanity domain for a given Site.
   *
   * @param $config
   * @param $site
   *
   * @return mixed
   */
  private function getProdVanityDomainForSite($config, $site) {

    $domain_url = $this->getFactoryUrl($config,"/api/v1/domains/$site->id");
    $result = $this->curlWrapper($config->username, $config->password, $domain_url);

    return $result->domains->custom_domains[0];
  }
}
