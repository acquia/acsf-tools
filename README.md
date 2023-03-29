# ACSF Tools

**This tool is community-supported. Acquia does not provide any direct support for this software or provide any warranty as to its stability.**

## Summary:

This project contains drush scripts designed to ease administering an Acquia Cloud Site Factory multisite
platform. While Drush provides many utilities to aid generally in Drupal administraton, multsite in general and ACSF in
particular adds a lot of complexity when managing multiple sites that live in a shared codebase. These tools merge
ACSF multisites concepts with the ease of Drush-based administration.

## Install and Configuration:

#### Install

For simpler projects with a single developer or very small teams, you can just clone this repository in your projects' global drush
directory (e.g., [project-root]/drush/Commands).

For larger teams, we recommend adding this project as a composer library, e.g. `composer require acquia/acsf-tools:9.x-dev`. See [Using Composer to manage Drupal site dependencies](https://www.drupal.org/node/2718229) if you're new to Composer.

_If you upgraded Drush to 11.x then use acsf-tools 11.x, the easiest method is to run `composer remove acquia/acsf-tools` and then `composer require acquia/acsf-tools:11.x-dev`. This will ensure no cruft remains from the 10.x version or earlier versions. Make a backup of your local acsf_tools_config.yml before running `composer remove`._

#### Drush 11 Installs

Using this branch requires Drush 11.

_Also, there are some additional manual install steps while some upstream packages ([BLT](https://github.com/acquia/blt/tree/11.x), [Composer-installers](https://github.com/composer/installers)) adapt to Drush 11:_

* In your project's main composer.json, change the 'type:drupal-drush' installer-path from `drush/contrib/{$name}` to `drush/Commands/{$name}`.
* If your repository incldues a legacy `/drush/contrib` folder, rename it to `drush/Commands`.
* If you're using BLT:
  * Change `drush/contrib` to `drush/Commands` in your main .gitignore.
  * Add the following as a `post-deploy-build` command in `blt/blt.yml`:

  ```javascript
  dir: '${deploy.dir}/drush'
  command: 'find ''Commands'' -type d -name ''.git'' -exec rm -fr {} +'
  ```

#### Configuration

Rename acsf_tools_config.default.yml as acsf_tools_config.yml and save it in the same directory. Replace the following
values:

* Site ID: This is the ID of your Factory. The easiest place to find this string is in the URL of your production factory. It is the subdomain immediately succeeding 'www' in the URL. E.g., for "www.demo.acquia-cc.com", the Site ID is 'demo'.
* Rest API User: This is your Factory username, which is displayed in the header after logging into your Factory.
* Rest API Key: This is your Factory REST API key. After logging into the Factory, click on your username, then the
'API key' tab.
* Rest Factories: This is an array of the URLs for your Prod, Test, and Dev factories. This should include a leading 'https://' as the protocol, and should _not_ include a trailing slash.
* Subdomain pattern: An optional config, used when staging custom domains from production, that allows you to define
a custom subdomain pattern. E.g., 'foo-dev.coolsites.com', where '{subdomain}-{env}' is the default.
* Prod Web: The server ID for your main production server. This is found in your cloud.acquia.com dashboard, under the servers tab. E.g., 'web-1234'.
* Dev Web: The server ID for your development server. This is found in your cloud.acquia.com dashboard, under the servers tab. E.g., 'web-1234'.

**Note**: The acsf_tools_config file is deliberately ignored via .gitignore. The idea is that most of these utility
scripts should only be ran by a platform admin with the appropriate permissions on their local machines. You should
_not be committing API credentials to your repository_.

## Tools:

#### Get Deployed Tag

__acsf-tools-get-deployed-tag (sft):__ This command will fetch and display the currently deployed Git tag for the sites
within a factory. E.g., `drush @coolsites.local sft dev` will display the currently deployed tag in the development
environment.

#### Backup Sites

__acsf-tools-sites-backup (sfb):__ This command will create a backup for a site or list of sites in your Factory. It
accepts either a single site id, a list of ids, or 'all' to backup all sites. E.g., `drush @coolsites.local sfb dev all`
will create a backup of all sites in your dev factory. You can get a list of site IDs in your factory by running
`drush @coolsites.01live acsf-tools-list`.

#### Content Staging Deploy

__acsf-tools-content-staging-deploy (sfst):__ This command will begin a content staging deploy from your Production
factory down to one of the lower environments, i.e., dev or test. You can stage either a single site, a list of sites,
or all sites. This is conceptually the same process as dragging your database and files from Production to Dev/Test in
Acquia Cloud Enterprise/Professional (ACE/ACP), only the multisite equivalent for ACSF.

**NOTE/WARNING**: Content staging deploys will overwrite the current state of all sites in the lower environment. For
example, if you are staging the production sites to the development server, this is will overwrite the databases that
are currently running on the dev server with the contents of the production databases. Also note, if you are only
staging a defined list of sites, this will replace the currently deployed sites in that environment with the sites
selected in this command. If the list of sites you're staging is _different_ from the sites currently deployed in
that environment, the sites not included in your staging deploy will essentially be deleted in that environment.

#### Custom Domains Staging

__acsf-tools-stage-domains (sfdo):__ Factory sites are given a default URL based on the user-defined ID, e.g.,
'foo.coolsites.acsitefactory.com' where the site ID is foo. In many business use cases, these default URLS are not
desirable, and we need a custom domain, e.g., 'foosite.com'.

This command allows you to take the custom domains as defined in production, and stage them down to the testing or
development environments to maintain consistency, i.e., 'dev.foosite.com' and 'test.foosite.com'. This command will
automatically detect the appropriate URL pattern, either based on the default 'www/test/dev' pattern, or a pattern you
define in acsf_tools_config.yml.

**Note:** This script will stage domains for all sites in your factory, but will only applied to the sites that are
actually present in that environment.

#### ACSF Tools

**Note**: The commands in this section are run remotely on a factory by remote drush alias, and do not require REST API
authentication. E.g., `drush @coolsites.01dev sfl` will list all the sites in the development factory for the 'coolsite'
subscription. This is the one exception to the 'always run local' rule. These commands do require SSH access via drush,
same as any other drush remote execution script.

* __acsf-tools-list (sfl):__ This command will list the details (e.g., name, url, aliases) for all sites in your
factory.
* __acsf-tools-info (sfi):__ This command will list site specific information (e.g., ID, Name, DB Name, Domain) for all sites in your factory.
* __acsf-tools-ml (sfml):__ This command will run any drush command against *all* sites in your factory. E.g.,
`drush @coolsites.01dev sfml st` will run the drush status command against all sites in your factory and return the
output. This is useful for clearing cacheS, or disabling a single module for every site in your factory.
* __acsf-tools-dump (sfdu):__ This command will create a quick sql backup for all sites in your factory.
* __acsf-tools-restore (sfr):__ This command will restore sql backups for all sites in your factory.
