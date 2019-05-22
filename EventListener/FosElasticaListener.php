<?php

namespace Nzo\ElasticQueryBundle\EventListener;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\LazyCriteriaCollection;
use Doctrine\ORM\PersistentCollection;
use Nzo\ElasticQueryBundle\Service\IndexTools;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Inflector\Inflector;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FosElasticaListener implements EventSubscriber
{
    /**
     * @var ObjectPersisterInterface
     */
    private $objectPersister;
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var IndexableInterface
     */
    private $indexable;
    /**
     * Configuration for the listener.
     *
     * @var array
     */
    private $config;
    /**
     * Objects scheduled for insertion.
     *
     * @var array
     */
    private $scheduledForInsertion = [];
    /**
     * Objects scheduled to be updated or removed.
     *
     * @var array
     */
    private $scheduledForUpdate = [];
    /**
     * IDs of objects scheduled for removal.
     *
     * @var array
     */
    private $scheduledForDeletion = [];
    /**
     * PropertyAccessor instance.
     *
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var IndexTools
     */
    private $indexTools;

    public function __construct(
        ObjectPersisterInterface $objectPersister,
        IndexableInterface $indexable,
        IndexTools $indexTools,
        array $config
    ) {
        $this->objectPersister = $objectPersister;
        $this->indexable = $indexable;
        $this->indexTools = $indexTools;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->config = array_merge(
            array(
                'identifier' => 'id',
            ),
            $config
        );
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getSubscribedEvents()
    {
        return array(
            'postPersist',
            'preRemove',
            'postRemove',
            'postUpdate',
            'postFlush',
        );
    }

    /**
     * Looks for new objects that should be indexed.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->objectPersister->handlesObject($entity) && $this->isObjectIndexable($entity)) {
            $this->scheduledForInsertion[] = $entity;
            // Update related document
            $this->updateRelations($entity, 'insert');
        }
    }

    /**
     * Looks for objects being updated that should be indexed or removed from the index.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();
        if ($this->objectPersister->handlesObject($entity)) {
            if ($this->isObjectIndexable($entity)) {
                $this->scheduledForUpdate[] = $entity;
                // Update related document
                $this->updateRelations($entity);
            } else {
                // Delete if no longer indexable
                $this->scheduleForDeletion($entity);
            }
        }
    }

    /**
     * Delete objects on preRemove instead of postRemove so that we have access to the id.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();
        if ($this->objectPersister->handlesObject($entity)) {
            $this->scheduleForDeletion($entity);
        }
    }

    /**
     * Update relations on postRemove
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->objectPersister->handlesObject($entity)) {
            // Update related document
            $this->updateRelations($entity, 'remove');
        }
    }

    /**
     * Iterating through scheduled actions *after* flushing ensures that the
     * ElasticSearch index will be affected only if the query is successful.
     */
    public function postFlush()
    {
        $this->flushScheduled();
    }

    /**
     * Update all object's relation managed by Doctrine
     *
     * @param Object $entity
     * @param string $stask
     */
    private function updateRelations($entity, $task = 'update')
    {
        // Get all association of the current entity
        $entityAssociations = $this->container->get('doctrine')->getManager()->getMetadataFactory()->getMetadataFor(
            get_class($entity)
        )->getAssociationMappings();

        foreach ($entityAssociations as $asso) {
            $elasticType = $this->indexTools->getElasticType($asso['targetEntity']);
            $elasticIndex = $this->indexTools->getElasticIndex($elasticType);
            $objectPersisterName = sprintf('fos_elastica.object_persister.%s.%s', $elasticIndex, $elasticType);
            if ($this->container->has($objectPersisterName)) {
                $objectPersisterRelation = $this->container->get($objectPersisterName);
                $getAssoObject = 'get'.ucfirst($asso['fieldName']);
                $relationObjects = $entity->$getAssoObject();
                $scheduledForUpdate = [];
                // Collection of Objects (ManyToOne, ManyToMany)
                if ($relationObjects instanceof PersistentCollection || $relationObjects instanceof LazyCriteriaCollection) {
                    if ($relationObjects->count() > 0) {
                        foreach ($relationObjects as $key => $object) {
                            if ($objectPersisterRelation->handlesObject($object) && $this->isObjectIndexable($object)) {
                                $this->handleInsert($task, $asso, $entity, $object);
                                $scheduledForUpdate[] = $object;
                            }
                        }
                        $this->handleReplaceMany($objectPersisterRelation, $scheduledForUpdate, $task);
                    }
                } else {
                    // One object
                    $object = $relationObjects;
                    if ($objectPersisterRelation->handlesObject($object) && $this->isObjectIndexable($object)) {
                        $this->handleInsert($task, $asso, $entity, $object);
                        $scheduledForUpdate[] = $object;

                        $this->handleReplaceMany($objectPersisterRelation, $scheduledForUpdate, $task);
                    }
                }
            }
        }
    }

    /**
     * @param ObjectPersisterInterface|Object $objectPersisterRelation
     * @param array $scheduledForUpdate
     * @param string $task
     */
    private function handleReplaceMany(
        ObjectPersisterInterface $objectPersisterRelation,
        array $scheduledForUpdate,
        string $task
    ) {
        if ('remove' === $task) {
            $scheduledForRemove = [];
            foreach ($scheduledForUpdate as $object) {
                if (method_exists($object, 'getId') && $object->getId() !== null) {
                    $scheduledForRemove[] = $object;
                }
            }
            $scheduledForUpdate = $scheduledForRemove;
        }

        if (!empty($scheduledForUpdate)) {
            $objectPersisterRelation->replaceMany($scheduledForUpdate);
        }
    }

    /**
     * @param string $task
     * @param array $asso
     * @param Object $entity
     * @param Object $object
     */
    private function handleInsert(string $task, array $asso, $entity, $object)
    {
        if ('insert' === $task) {

            $ressource = $asso['inversedBy'] ?? $asso['mappedBy'] ?? null;

            if (empty($ressource)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The doctrine attribute "inversedBy" or "mappedBy" must be set in the entity "%s" for the association with "%s"',
                        get_class($entity),
                        get_class($object)
                    )
                );
            }

            $ressource = Inflector::singularize($ressource);
            $setAssoObject = 'add'.ucfirst($ressource);
            if (method_exists($object, $setAssoObject)) {
                $object->$setAssoObject($entity);

                return;
            }

            $setAssoObject = 'set'.ucfirst($ressource);
            if (method_exists($object, $setAssoObject)) {
                $object->$setAssoObject($entity);

                return;
            }

            throw new \InvalidArgumentException(
                sprintf(
                    'One of the methods "%s" or "%s" must be implemented in the entity "%s"',
                    'set'.ucfirst($ressource),
                    'add'.ucfirst($ressource),
                    get_class($object)
                )
            );
        }
    }

    /**
     * Persist scheduled objects to ElasticSearch
     * After persisting, clear the scheduled queue to prevent multiple data updates when using multiple flush calls.
     */
    private function flushScheduled()
    {
        if (count($this->scheduledForInsertion)) {
            $this->objectPersister->insertMany($this->scheduledForInsertion);
            $this->scheduledForInsertion = [];
        }
        if (count($this->scheduledForUpdate)) {
            $this->handleReplaceMany($this->objectPersister, $this->scheduledForUpdate, 'update');
            $this->scheduledForUpdate = [];
        }
        if (count($this->scheduledForDeletion)) {
            $this->objectPersister->deleteManyByIdentifiers($this->scheduledForDeletion);
            $this->scheduledForDeletion = [];
        }
    }

    /**
     * Set the specified identifier to delete.
     *
     * @param object $object
     */
    private function scheduleForDeletion($object)
    {
        if ($identifierValue = $this->propertyAccessor->getValue($object, $this->config['identifier'])) {
            $this->scheduledForDeletion[] = $identifierValue;
        }
    }

    /**
     * Checks if the object is indexable or not.
     *
     * @param object $object
     * @return bool
     */
    private function isObjectIndexable($object)
    {
        return $this->indexable->isObjectIndexable(
            $this->config['indexName'],
            $this->config['typeName'],
            $object
        );
    }
}
