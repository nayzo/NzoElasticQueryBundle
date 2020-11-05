<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\DependencyInjection\Compiler;

use Nzo\ElasticQueryBundle\EventListener\FosElasticaListener;
use Nzo\ElasticQueryBundle\Service\IndexTools;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Yaml\Yaml;

class NzoElasticUpdateNestedQueryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $indexConfigs = $container->getDefinition('fos_elastica.config_source.container')->getArgument(0);
        $indexTools = $container->get('nzo.elastic_query.index_tools');

        $nestedList = [];
        foreach ($indexConfigs as $config) {
            if (empty($config['types'])) {
                continue;
            }
            $properties = \current($config['types'])['mapping']['properties'];
            $namespace = \current($config['types'])['config']['persistence']['model'];
            foreach ($properties as $field => $property) {
                if ('nested' === $property['type']) {
                    $nestedList[] = $this->getNestedEntityName($container, $indexTools, $namespace, $field);
                }
            }
        }

        $nestedList = \array_unique(\array_filter($nestedList));

        $this->persistersLazyLoading($container);

        foreach ($nestedList as $type) {
            $id = \sprintf('nzo.elastic_query.fos_elastica_listener.%s', $type);
            if (!$container->has($id)) {

                $index = $indexTools->getElasticIndex($type);
                $elsasticaPersesterId = \sprintf('fos_elastica.object_persister.%s.%s', $index, $type);

                if ($container->has($elsasticaPersesterId)) {
                    $definition = new Definition(
                        FosElasticaListener::class, [
                            new Reference($elsasticaPersesterId),
                            new Reference('fos_elastica.indexable'),
                            new Reference('nzo.elastic_query.index_tools'),
                            new Reference('nzo.elastic_query.locator'),
                            ['indexName' => $index, 'typeName' => $type],
                        ]
                    );

                    $definition
                        ->addTag('doctrine.event_subscriber');

                    $container->setDefinition($id, $definition);
                }
            }
        }
    }

    private function persistersLazyLoading(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds('fos_elastica.persister');

        $persisters = [];
        foreach ($taggedServices as $id => $tags) {
            $ref = \str_replace('fos_elastica.object_persister.', '', $id);
            $persisters[$ref] = new Reference($id);
        }

        $container
            ->register('nzo.elastic_query.locator', ServiceLocator::class)
            ->setArguments([$persisters])
            ->addTag('container.service_locator');
    }

    /**
     * @param ContainerBuilder $container
     * @param IndexTools $indexTools
     * @param string $namespace
     * @param string $field
     * @return string|null
     */
    private function getNestedEntityName(ContainerBuilder $container, IndexTools $indexTools, $namespace, $field)
    {
        if ($container->has('doctrine.orm.default_annotation_metadata_driver')) {
            return $this->resolveAnnotationNestedEntityName($indexTools, $namespace, $field);
        }
        if ($container->has('doctrine.orm.default_yml_metadata_driver')) {
            return $this->resolveYmlNestedEntityName($container, $indexTools, $namespace, $field);
        }

        throw new \InvalidArgumentException(
            \sprintf(
                'Only "annotation" and "yml" doctrine metadata drivers are supported to handle ElasticQuery mapping'
            )
        );
    }

    /**
     * @param IndexTools $indexTools
     * @param string $namespace
     * @param string $field
     * @return string|null
     * @throws \RuntimeException
     */
    private function resolveAnnotationNestedEntityName(IndexTools $indexTools, $namespace, $field)
    {
        if (\class_exists($namespace)) {
            try {
                $object = new \ReflectionClass($namespace);
                $properties = $object->getProperties();
                foreach ($properties as $property) {
                    if ($property->getName() === $field) {
                        $p1 = \strpos($property->getDocComment(), 'targetEntity="');
                        $p2 = \strpos($property->getDocComment(), '"', $p1 + 14);
                        $target = \substr($property->getDocComment(), $p1 + 14, $p2 - ($p1 + 14));
                        if (!empty($target)) {
                            return $indexTools->getElasticType($target);
                        }

                        return null;
                    }
                }
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    \sprintf(
                        'Annotaion nested entity name can\'t be resolved. Exception: %s',
                        $e->getMessage()
                    )
                );
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param IndexTools $indexTools
     * @param string $namespace
     * @param string $field
     * @return string|null
     */
    private function resolveYmlNestedEntityName(ContainerBuilder $container, IndexTools $indexTools, $namespace, $field)
    {
        $ymlDriver = $container->getDefinition('doctrine.orm.default_yml_metadata_driver');
        $argument = $ymlDriver->getArgument(0);
        $dir = \array_keys($argument)[0];
        $ormFile = \sprintf('%s/%s.orm.yml', $dir, \ucfirst($indexTools->getElasticType($namespace)));

        if (!\file_exists($ormFile)) {
            throw new \InvalidArgumentException(
                \sprintf('The doctrine yml mapping file "%s" not found for the entity "%s"', $ormFile, $namespace)
            );
        }
        $entityMapping = Yaml::parseFile($ormFile);
        $attributes = \current($entityMapping);
        foreach ($attributes as $name => $attribute) {
            if (\in_array($name, ['manyToOne', 'oneToMany', 'manyToMany'])) {
                foreach ($attribute as $key => $item) {
                    if ($key === $field) {
                        return $indexTools->getElasticType($item['targetEntity']);
                    }
                }
            }
        }

        return null;
    }
}
