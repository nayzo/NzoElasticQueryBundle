<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\Manager;

use Nzo\ElasticQueryBundle\Service\IndexTools;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SearchManager
{
    private $indexTools;
    private $indexProperties;

    public function __construct(IndexTools $indexTools)
    {
        $this->indexTools = $indexTools;
    }

    public function resolveQueryMapping($queryObj, string $entityNamespace)
    {
        $query = $queryObj->query;

        $resultQuery['query'] = $this->createQuery($query->search);
        if (!empty($query->sort)) {
            $this->indexProperties = $this->indexTools->getIndexMappingProperties($entityNamespace);
            $resultQuery['sort'] = $this->createSort($query->sort);
        }

        return $resultQuery;
    }

    private function createQuery($searchModel): array
    {
        $properties = \get_object_vars($searchModel);

        if (empty($properties)) {
            return $this->getAll();
        }

        $nativeQuery = [];
        $this->queryGenerationHandler($properties, $nativeQuery);

        return ['bool' => $nativeQuery];
    }

    private function queryGenerationHandler(array $properties, array &$nativeQuery)
    {
        if (empty($properties)) {
            return;
        }

        foreach ($properties as $property => $value) {
            switch ($property) {
                case 'and':
                    if (\is_array($value)) {
                        foreach ($value as $elem) {
                            $elem = \get_object_vars($elem);
                            $result = $this->executeQueryGeneration($elem);
                            if (\array_key_exists('bool', $result)) {
                                $nativeQuery['must'][] = $result;
                            } else {
                                $nativeQuery['must'][] = ['bool' => $result];
                            }
                        }
                    } elseif (\is_object($value)) {
                        $value = \get_object_vars($value);
                        $result = $this->executeQueryGeneration($value);
                        if (\array_key_exists('bool', $result)) {
                            $nativeQuery['must'] = $result;
                        } else {
                            $nativeQuery['must'] = ['bool' => $result];
                        }
                    }
                    break;
                case 'or':
                    if (\is_array($value)) {
                        foreach ($value as $elem) {
                            $elem = \get_object_vars($elem);
                            $result = $this->executeQueryGeneration($elem);
                            if (\array_key_exists('bool', $result)) {
                                $nativeQuery['should'][] = $result;
                            } else {
                                $nativeQuery['should'][] = ['bool' => $result];
                            }
                        }
                    } elseif (\is_object($value)) {
                        $value = \get_object_vars($value);
                        $result = $this->executeQueryGeneration($value);
                        if (\array_key_exists('bool', $result)) {
                            $nativeQuery['should'] = $result;
                        } else {
                            $nativeQuery['should'] = ['bool' => $result];
                        }
                    }
                    break;
            }
        }
    }

    private function executeQueryGeneration($properties)
    {
        $this->queryChecker($properties);

        foreach ($properties as $property => $value) {

            switch ($property) {
                case 'and':
                    if (\is_array($value)) {
                        $buffer = [];
                        foreach ($value as $elem) {
                            $elem = \get_object_vars($elem);
                            $result = $this->executeQueryGeneration($elem);
                            if (\array_key_exists('must', $result) && !\array_key_exists('bool', $result)) {
                                $result = ['bool' => $result];
                            }
                            $buffer['must'][] = $result;
                        }

                        return $buffer;
                    }
                    if (\is_object($value)) {
                        $value = \get_object_vars($value);
                        $result = $this->executeQueryGeneration($value);
                        if (\array_key_exists('must', $result) && !\array_key_exists('bool', $result)) {
                            $result = ['bool' => $result];
                        }
                        $buffer['must'] = $result;

                        return $buffer;
                    }
                    break;
                case 'or':
                    if (\is_array($value)) {
                        $buffer = [];
                        foreach ($value as $elem) {
                            $elem = \get_object_vars($elem);
                            $result = $this->executeQueryGeneration($elem);
                            if (\array_key_exists('bool', $result)) {
                                $buffer['bool']['should'][] = $result;
                            } else {
                                $buffer['bool']['should'][] = ['bool' => $result];
                            }
                        }

                        return $buffer;
                    }
                    if (\is_object($value)) {
                        $value = \get_object_vars($value);
                        $result = $this->executeQueryGeneration($value);
                        if (\array_key_exists('bool', $result)) {
                            $buffer['bool']['should'] = $result;
                        } else {
                            $buffer['bool']['should'] = ['bool' => $result];
                        }

                        return $buffer;
                    }
                    break;
                case 'match':
                    $field = $this->getField($properties);
                    $subQuery = $this->setIfNested(['match' => [$field => $value]], $field);
                    $buffer['bool'] = ['must' => $subQuery];

                    return $buffer;
                case 'notmatch':
                    $field = $this->getField($properties);
                    $subQuery = $this->setIfNested(['match' => [$field => $value]], $field);
                    $buffer['bool'] = ['must_not' => $subQuery];

                    return $buffer;
                case 'isnull':
                    $field = $this->getField($properties);
                    $subQuery = $this->setIfNested(['exists' => ['field' => $field]], $field);

                    if ($value) {
                        $buffer['bool'] = ['must_not' => $subQuery];
                    } else {
                        $buffer['bool'] = ['must' => $subQuery];
                    }

                    return $buffer;
                case 'in':
                    $field = $this->getField($properties);
                    $subQuery = $this->setIfNested(['terms' => [$field => $value]], $field);
                    $buffer['bool'] = ['must' => $subQuery];

                    return $buffer;
                case 'notin':
                    $field = $this->getField($properties);
                    $subQuery = $this->setIfNested(['terms' => [$field => $value]], $field);
                    $buffer['bool'] = ['must_not' => $subQuery];

                    return $buffer;
                case 'range':
                    $field = $this->getField($properties);

                    return [
                        'must' => $this->setIfNested(
                            ['range' => [$field => ['gte' => $value[0], 'lte' => $value[1]]]],
                            $field
                        ),
                    ];
                case 'gt':
                case 'gte':
                case 'lt':
                case 'lte':
                    $field = $this->getField($properties);

                    return ['must' => $this->setIfNested(['range' => [$field => [$property => $value]]], $field)];
                case 'wildcard':
                    $field = $this->getField($properties);
                    $subQuery = $this->setIfNested(['wildcard' => [$field => $value]], $field);
                    $buffer['bool'] = ['must' => $subQuery];

                    return $buffer;
            }
        }
    }

    private function setIfNested(array $query, string $field): array
    {
        if (\strpos($field, '.') !== false) { // nested

            $path = substr($field, 0, strrpos($field, '.'));
            $newQuery = ['nested' => ['path' => $path, 'query' => $query]];

            return $this->setIfNested($newQuery, $path);
        }

        return $query;
    }

    private function createSort(array $sortList): array
    {
        $resultSort = [];
        foreach ($sortList as $sort) {
            $properties = \get_object_vars($sort);
            $baseField = $properties['field'];
            $field = $this->sortResolver($baseField);

            $sort = ['order' => $properties['order']];

            if ($this->isNested($baseField)) {
                $sort['nested_path'] = \explode('.', $baseField)[0];
            }

            $resultSort[] = [$field => $sort];
        }

        return $resultSort;
    }

    /**
     * An alternative to the Fielddata elasticsearch limitation for sorting fields with "text" type.
     */
    private function sortResolver(string $baseField): string
    {
        if ($this->isNested($baseField)) { // nested
            list($nestedEntity, $field) = \explode('.', $baseField);
            $fieldType = $this->indexProperties[$nestedEntity]['properties'][$field]['type'];
        } else {
            $fieldType = $this->indexProperties[$baseField]['type'];
        }

        return 'text' === $fieldType ? $baseField.'.sort' : $baseField;
    }

    private function isNested(string $baseField): bool
    {
        return \strpos($baseField, '.') !== false;
    }

    private function getField(array &$elemProperties): string
    {
        if (empty($elemProperties['field'])) {
            throw new BadRequestHttpException(
                \sprintf('\'field\' property not found in the query:  %s', \json_encode($elemProperties))
            );
        }

        $field = $elemProperties['field'];
        unset($elemProperties['field']);

        return $field;
    }

    private function queryChecker(array $query): void
    {
        if (empty($query)) {
            throw new BadRequestHttpException('Not allowed empty object query body');
        }

        if (\count($query) > 2) {
            throw new BadRequestHttpException(
                \sprintf('Only one condition is allowed in each object query body, %s', \json_encode($query))
            );
        }

        if (\count($query) === 1 && \array_key_exists('field', $query)) {
            throw new BadRequestHttpException(
                \sprintf('No condition found in the object query body, %s', \json_encode($query))
            );
        }

        if (\array_key_exists('and', $query) && \array_key_exists('or', $query)) {
            throw new BadRequestHttpException(
                'Must not have the \'and\' and \'or\' properties in the same object query!'
            );
        }
    }

    private function getAll(): array
    {
        return array(
            'match_all' => new \stdClass,
        );
    }
}
