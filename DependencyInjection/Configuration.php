<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nzo_elastic_query');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('elastic_index_prefix')
                    ->defaultValue('')
                ->end()
                ->integerNode('default_page_number')
                    ->defaultValue(1)
                ->end()
                ->integerNode('limit_per_page')
                    ->defaultValue(100)
                ->end()
                ->integerNode('items_max_limit')
                    ->defaultValue(1000)
                ->end()
                ->booleanNode('show_score')
                    ->defaultValue(false)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
