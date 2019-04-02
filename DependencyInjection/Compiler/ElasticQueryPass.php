<?php

namespace Nzo\ElasticQueryBundle\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ElasticQueryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $indexConfigs = $container->getDefinition('fos_elastica.config_source.container')->getArgument(0);

        foreach ($indexConfigs as $key => $config) {
            unset($indexConfigs[$key]['reference']);
        }

        $container->setParameter('nzo_elastic_query.index_configs', $indexConfigs);
    }
}
