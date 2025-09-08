# ACSF Tools

**This tool is community-supported. Acquia does not provide any direct support for this software or provide any warranty as to its stability.**

## Summary:

This project contains a set of drush scripts designed to ease administering an Acquia Cloud Site Factory multisite
platform. While Drush provides many utilities to aid generally in Drupal administraton, multsite in general and ACSF in
particular adds a lot of complexity when managing multiple sites that live in a shared codebase. These tools merge
ACSF multisites concepts with the ease of Drush-based administration.

## Install and Configuration:

#### Install

For simpler projects with a single developer or very small teams, you can just clone this repository in your users' drush
directory (e.g., ~/.drush).

For larger teams, we recommend adding this project as a composer library, e.g. `composer require acquia/acsf-tools`. See [Using Composer to manage Drupal site dependencies](https://www.drupal.org/node/2718229) if you're new to Composer.

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

### ACSF Tools

**Note**: The commands in this section are run remotely on a factory by remote drush alias, and do not require REST API
authentication. E.g., `drush @coolsites.01dev sfl` will list all the sites in the development factory for the 'coolsite'
subscription. This is the one exception to the 'always run local' rule. These commands do require SSH access via drush,
same as any other drush remote execution script.

* __acsf-tools-list (sfl):__ This command will list the details (e.g., name, url, aliases) for all sites in your
factory.
* __acsf-tools-info (sfi):__ This command will list site specific information (e.g., ID, Name, DB Name, Domain) for all sites in your
factory.
* __acsf-tools-ml (sfml):__ This command will run any drush command against *all* sites in your factory. E.g.,
`drush @coolsites.01dev sfml st` will run the drush status command against all sites in your factory and return the
output. This is useful for disabling clearing cache, or disabling a single module for every site in your factory.
* __acsf-tools-dump (sfdu):__ This command will create database backups for all sites in your factory.
* __acsf-tools-restore (sfre):__ This command will restore database backups for all sites in your factory.
* __acsf-tools-analyze (sfa):__ This command will extract information about modules, themes, entities, views from sites on a factory. It works with non-ACSF multisite installations as well.
