# ACSF Tools

**This tool is community-supported. Acquia does not provide any direct support for this software or provide any warranty as to its stability.**

## Summary:

This project contains a set of drush scripts designed to ease administering an Acquia Cloud Site Factory multisite
platform. While Drush provides many utilities to aid generally in Drupal administraton, multsite in general and ACSF in
particular adds a lot of complexity when managing multiple sites that live in a shared codebase. These tools merge
ACSF multisites concepts with the ease of Drush-based administration.

## Why a 2.x version:

This project has been historicaly developed to ease running Drush commands on ACSF. ACSF is progressively
replaced by MEO which manage multisites in a more standard manner. This version of acsf-tools has been adapted
to work on both ACSF and MEO. It has also been simplified to only keep the possibility to run Drush commands
at scale.

## Install and Configuration:

#### Install

For simpler projects with a single developer or very small teams, you can just clone this repository in your users' drush
directory (e.g., ~/.drush).

For larger teams, we recommend adding this project as a composer library, e.g. `composer require acquia/acsf-tools`. See [Using Composer to manage Drupal site dependencies](https://www.drupal.org/node/2718229) if you're new to Composer.

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
