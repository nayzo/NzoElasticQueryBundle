<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\ObjectManager;
use Nzo\ElasticQueryBundle\Service\IndexTools;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\String\Inflector\EnglishInflector;

class FosElasticaListener implements EventSubscriber
{
    const ACTION_INSERT = 'insert';
    const ACTION_UPDATE = 'update';
    const ACTION_REMOVE = 'remove';

    private $objectPersister;
    private $indexable;
    private $config;
    private $scheduledForInsertion = [];
    private $scheduledForUpdate = [];
    private $scheduledForDeletion = [];
    private $propertyAccessor;
    private $indexTools;
    private $serviceLocator;
    private $inflector;

    public function __construct(
        ObjectPersisterInterface $objectPersister,
        IndexableInterface $indexable,
        IndexTools $indexTools,
        ServiceLocator $serviceLocator,
        array $config
    ) {
        $this->objectPersister = $objectPersister;
        $this->indexable = $indexable;
        $this->indexTools = $indexTools;
        $this->serviceLocator = $serviceLocator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->config = \array_merge(
            array(
                'identifier' => 'id',
            ),
            $config
        );
        $this->inflector = new EnglishInflector();
    }

    public function getSubscribedEvents(): array
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
     */
    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();

        if ($this->objectPersister->handlesObject($entity) && $this->isObjectIndexable($entity)) {
            $this->scheduledForInsertion[] = $entity;
            // Update related document
            $this->updateRelations($eventArgs->getObjectManager(), $entity, self::ACTION_INSERT);
        }
    }

    /**
     * Looks for objects being updated that should be indexed or removed from the index.
     */
    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();
        if ($this->objectPersister->handlesObject($entity)) {
            if ($this->isObjectIndexable($entity)) {
                $this->scheduledForUpdate[] = $entity;
                // Update related document
                $this->updateRelations($eventArgs->getObjectManager(), $entity);
            } else {
                // Delete if no longer indexable
                $this->scheduleForDeletion($entity);
            }
        }
    }

    /**
     * Delete objects on preRemove instead of postRemove so that we have access to the id.
     */
    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();
        if ($this->objectPersister->handlesObject($entity)) {
            $this->scheduleForDeletion($entity);
        }
    }

    /**
     * Update relations on postRemove
     */
    public function postRemove(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();

        if ($this->objectPersister->handlesObject($entity)) {
            // Update related document
            $this->updateRelations($eventArgs->getObjectManager(), $entity, self::ACTION_REMOVE);
        }
    }

    /**
     * Iterating through scheduled actions *after* flushing ensures that the
     * ElasticSearch index will be affected only if the query is successful.
     */
    public function postFlush(): void
    {
        $this->flushScheduled();
    }

    /**
     * Update all object's relation managed by Doctrine
     */
    private function updateRelations(ObjectManager $objectManager, $entity, string $task = self::ACTION_UPDATE): void
    {
        // Get all association of the current entity
        $entityAssociations = $objectManager->getMetadataFactory()
            ->getMetadataFor(\get_class($entity))
            ->getAssociationMappings();

        foreach ($entityAssociations as $asso) {
            $elasticType = $this->indexTools->getElasticType($asso['targetEntity']);
            $elasticIndex = $this->indexTools->getElasticIndex($elasticType);
            $persisterReference = \sprintf('%s.%s', $elasticIndex, $elasticType);
            if ($this->serviceLocator->has($persisterReference)) {
                $objectPersisterRelation = $this->serviceLocator->get($persisterReference);
                $relationObjects = $this->propertyAccessor->getValue($entity, $asso['fieldName']);
                if (null !== $relationObjects && !is_array($relationObjects)) {
                    $scheduledForUpdate = [];
                    // Collection of Objects (ManyToOne, ManyToMany)
                    if ($relationObjects instanceof Collection) {
                        if ($relationObjects->count() > 0) {
                            foreach ($relationObjects as $object) {
                                if ($objectPersisterRelation->handlesObject($object) && $this->isObjectIndexable(
                                        $object
                                    )) {
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
    }

    private function handleReplaceMany(
        ObjectPersisterInterface $objectPersisterRelation,
        array $scheduledForUpdate,
        string $task
    ): void {
        if (self::ACTION_REMOVE === $task) {
            $scheduledForRemove = [];
            foreach ($scheduledForUpdate as $object) {
                if (\method_exists($object, 'getId') && $object->getId() !== null) {
                    $scheduledForRemove[] = $object;
                }
            }
            $scheduledForUpdate = $scheduledForRemove;
        }

        if (!empty($scheduledForUpdate)) {
            $objectPersisterRelation->replaceMany($scheduledForUpdate);
        }
    }

    private function handleInsert(string $task, array $asso, $entity, $object)
    {
        if (self::ACTION_INSERT === $task) {
            $ressource = !empty($asso['inversedBy']) ? $asso['inversedBy'] : null;
            $ressource = (null === $ressource && !empty($asso['mappedBy'])) ? $asso['mappedBy'] : $ressource;

            if (empty($ressource)) {
                throw new \InvalidArgumentException(
                    \sprintf(
                        'The doctrine attribute "inversedBy" or "mappedBy" must be set in the entity "%s" for the association with "%s"',
                        \get_class($entity),
                        \get_class($object)
                    )
                );
            }

            $singularizedRessource = $this->inflector->singularize($ressource);
            if (empty($singularizedRessource)) {
                $singularizedRessource = $ressource;
            } else {
                $singularizedRessource = $singularizedRessource[0];
            }
            $setAssoObject = 'add'.\ucfirst($singularizedRessource);
            if (\method_exists($object, $setAssoObject)) {
                $object->$setAssoObject($entity);

                return;
            }

            $setAssoObject = 'set'.\ucfirst($singularizedRessource);
            if (\method_exists($object, $setAssoObject)) {
                $object->$setAssoObject($entity);

                return;
            }

            throw new \InvalidArgumentException(
                \sprintf(
                    'One of the methods "%s" or "%s" must be implemented in the entity "%s"',
                    'set'.\ucfirst($singularizedRessource),
                    'add'.\ucfirst($singularizedRessource),
                    \get_class($object)
                )
            );
        }
    }

    /**
     * Persist scheduled objects to ElasticSearch
     * After persisting, clear the scheduled queue to prevent multiple data updates when using multiple flush calls.
     */
    private function flushScheduled(): void
    {
        if (\count($this->scheduledForInsertion)) {
            $this->objectPersister->insertMany($this->scheduledForInsertion);
            $this->scheduledForInsertion = [];
        }
        if (\count($this->scheduledForUpdate)) {
            $this->handleReplaceMany($this->objectPersister, $this->scheduledForUpdate, self::ACTION_UPDATE);
            $this->scheduledForUpdate = [];
        }
        if (\count($this->scheduledForDeletion)) {
            $this->objectPersister->deleteManyByIdentifiers($this->scheduledForDeletion);
            $this->scheduledForDeletion = [];
        }
    }

    /**
     * Set the specified identifier to delete.
     */
    private function scheduleForDeletion($object): void
    {
        if ($identifierValue = $this->propertyAccessor->getValue($object, $this->config['identifier'])) {
            $this->scheduledForDeletion[] = $identifierValue;
        }
    }

    /**
     * Checks if the object is indexable or not.
     */
    private function isObjectIndexable($object): bool
    {
        return $this->indexable->isObjectIndexable(
            $this->config['indexName'],
            $this->config['typeName'],
            $object
        );
    }
}
