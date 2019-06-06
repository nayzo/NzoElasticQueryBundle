<?php

namespace Nzo\ElasticQueryBundle\DependencyInjection\Compiler;

use Nzo\ElasticQueryBundle\EventListener\FosElasticaListener;
use Nzo\ElasticQueryBundle\Service\IndexTools;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
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
            $properties = current($config['types'])['mapping']['properties'];
            $namespace = current($config['types'])['config']['persistence']['model'];
            foreach ($properties as $field => $property) {
                if ('nested' === $property['type']) {
                    $nestedList[] = $this->getNestedEntityName($container, $indexTools, $namespace, $field);
                }
            }
        }

        $nestedList = array_unique(array_filter($nestedList));

        foreach ($nestedList as $type) {
            $id = sprintf('nzo.elastic_query.fos_elastica_listener.%s', $type);
            if (!$container->has($id)) {

                $index = $indexTools->getElasticIndex($type);
                $elsasticaPersesterId = sprintf('fos_elastica.object_persister.%s.%s', $index, $type);

                if ($container->has($elsasticaPersesterId)) {
                    $definition = new Definition(
                        FosElasticaListener::class, [
                            new Reference($elsasticaPersesterId),
                            new Reference('fos_elastica.indexable'),
                            new Reference('nzo.elastic_query.index_tools'),
                            ['indexName' => $index, 'typeName' => $type],
                        ]
                    );

                    $definition
                        ->addMethodCall('setContainer', [new Reference('service_container')])
                        ->addTag('doctrine.event_subscriber');

                    $container->setDefinition($id, $definition);
                }
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
    public function getNestedEntityName(ContainerBuilder $container, IndexTools $indexTools, $namespace, $field)
    {
        if ($container->has('doctrine.orm.default_annotation_metadata_driver')) {
            return $this->resolveAnnotationNestedEntityName($indexTools, $namespace, $field);
        } else {
            if ($container->has('doctrine.orm.default_yml_metadata_driver')) {
                return $this->resolveYmlNestedEntityName($container, $indexTools, $namespace, $field);
            }
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Only "annotation" and "yml" doctrine metadata drivers are supported to handle ElasticQuery mapping'
            )
        );
    }

    /**
     * @param IndexTools $indexTools
     * @param string $namespace
     * @param string $field
     * @return string|null
     */
    public function resolveAnnotationNestedEntityName(IndexTools $indexTools, $namespace, $field)
    {
        $entity = new $namespace;
        $object = new \ReflectionObject($entity);
        $properties = $object->getProperties();

        foreach ($properties as $property) {
            if ($property->getName() === $field) {
                $p1 = strpos($property->getDocComment(), 'targetEntity="');
                $p2 = strpos($property->getDocComment(), '"', $p1 + 14);
                $target = substr($property->getDocComment(), $p1 + 14, $p2 - ($p1 + 14));
                if (!empty($target)) {
                    return $indexTools->getElasticType($target);
                }

                return null;
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
    public function resolveYmlNestedEntityName(ContainerBuilder $container, IndexTools $indexTools, $namespace, $field)
    {
        $ymlDriver = $container->getDefinition('doctrine.orm.default_yml_metadata_driver');
        $argument = $ymlDriver->getArgument(0);
        $dir = array_keys($argument)[0];
        $ormFile = sprintf('%s/%s.orm.yml', $dir, ucfirst($indexTools->getElasticType($namespace)));

        if (!file_exists($ormFile)) {
            throw new \InvalidArgumentException(
                sprintf('The doctrine yml mapping file "%s" not found for the entity "%s"', $ormFile, $namespace)
            );
        }
        $entityMapping = Yaml::parseFile($ormFile);
        $attributes = current($entityMapping);
        foreach ($attributes as $name => $attribute) {
            if (in_array($name, ['manyToOne', 'oneToMany', 'manyToMany'])) {
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
