<?php

namespace Drupal\acsf_tools;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AcsfToolsServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $yaml_loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../'));
    $yaml_loader->load('acsf_tools.services.yml');
  }

}