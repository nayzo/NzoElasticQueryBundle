<?php

namespace Nzo\ElasticQueryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('nzo_elastic_query');
        // Keep compatibility with symfony/config < 4.2
        if (\method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            $rootNode = $treeBuilder->root('nzo_elastic_query');
        }

        $rootNode
            ->children()
                ->scalarNode('elastic_index_prefix')
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
