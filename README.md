# ACSF Tools

**This tool is community-supported. Acquia does not provide any direct support for this software or provide any warranty as to its stability.**

## Summary:

This project contains drush scripts designed to ease administering an Acquia Cloud Site Factory multisite
platform. While Drush provides many utilities to aid generally in Drupal administraton, multsite in general and ACSF in
particular adds a lot of complexity when managing multiple sites that live in a shared codebase. These tools merge
ACSF multisites concepts with the ease of Drush-based administration.

## Why a 2.x version:

This project has been historicaly developed to ease running Drush commands on ACSF. ACSF is progressively
replaced by [MEO](https://www.acquia.com/products/acquia-cloud-platform/multi-experience-operations) which manage multisites in a more standard manner. This version of acsf-tools has been adapted
to work on both ACSF and MEO. It has also been simplified to only keep the possibility to run Drush commands
at scale.

## Install and Configuration:

#### Install

For simpler projects with a single developer or very small teams, you can just clone this repository in your projects' global drush
directory (e.g., [project-root]/drush/Commands).

For larger teams, we recommend adding this project as a composer library, e.g. `composer require acquia/acsf-tools:9.x-dev`. See [Using Composer to manage Drupal site dependencies](https://www.drupal.org/node/2718229) if you're new to Composer.

_If you upgraded Drush to 10.x then use acsf-tools 10.x, the easiest method is to run `composer remove acquia/acsf-tools` and then `composer require acquia/acsf-tools:10.x-dev`. This will ensure no cruft remains from the 9.x version or earlier versions. Make a backup of your local acsf_tools_config.yml before running `composer remove`._

#### Drush 10 Installs

Using this branch requires Drush 10. There are some [architectural changes to global commands in Drush 10](http://docs.drush.org/en/master/commands/#global-drush-commands) that you'll want to become familiar with.

_Also, there are some additional manual install steps while some upstream packages ([BLT](https://github.com/acquia/blt/tree/11.x), [Composer-installers](https://github.com/composer/installers)) adapt to Drush 10:_

* In your project's main composer.json, change the 'type:drupal-drush' installer-path from `drush/contrib/{$name}` to `drush/Commands/{$name}`.
* If your repository incldues a legacy `/drush/contrib` folder, rename it to `drush/Commands`.
* If you're using BLT:
  * Change `drush/contrib` to `drush/Commands` in your main .gitignore.
  * Add the following as a `post-deploy-build` command in `blt/blt.yml`:

  ```javascript
  dir: '${deploy.dir}/drush'
  command: 'find ''Commands'' -type d -name ''.git'' -exec rm -fr {} +'
  ```

## Tools:

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





