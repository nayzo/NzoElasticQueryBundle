<?php

namespace Nzo\ElasticQueryBundle\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Nzo\ElasticQueryBundle\Service\IndexTools;

class QueryValidator extends AbstractValidator
{
    private $entityManager;
    private $indexTools;

    public function __construct(EntityManagerInterface $entityManager, IndexTools $indexTools)
    {
        $this->entityManager = $entityManager;
        $this->indexTools = $indexTools;
    }

    public function isSearchQueryValid(): bool
    {
        return empty($this->getValidationErrors());
    }

    public function checkSearchQuery(array $query, string $entityNamespace): void
    {
        foreach ($query as $key => $value) {
            if (\in_array($key, ['or', 'and'], true)) {
                if (\is_array($value)) {
                    $this->checkSearchQuery($value, $entityNamespace);
                } else {
                    $this->checkSearchQuery(\get_object_vars($value), $entityNamespace);
                }
            } else {
                if (\is_object($value)) {
                    $this->checkSearchQuery(\get_object_vars($value), $entityNamespace);
                }

                $this->checkFieldExist($key, $value, $entityNamespace);
                $this->checkRange($key, $value);
                $this->checkGtLtFields($key, $value);
            }
        }
    }

    public function checkSortQuery(array $query, string $entityNamespace): void
    {
        foreach ($query as $queryValue) {
            $sort = \get_object_vars($queryValue);
            foreach ($sort as $key => $value) {
                $this->checkFieldExist($key, $value, $entityNamespace, true);
            }
        }
    }

    private function checkFieldExist(?string $key, $value, string $entityNamespace, $isSort = false)
    {
        if ('field' === $key) {
            if (\strpos($value, '.') !== false) {
                $entityFieldName = \explode('.', $value)[0];
                $fieldName = \explode('.', $value)[1];
                $metadata = $this->entityManager->getClassMetadata($entityNamespace);
                if (!\array_key_exists($entityFieldName, $metadata->associationMappings)) {
                    $this->addValidationError(
                        \sprintf(
                            '%sNo association exist for the nested property \'%s\'',
                            $isSort ? '[Sort] ' : '',
                            $entityFieldName
                        ),
                        $value
                    );

                    return;
                } else {
                    $indexProperties = $this->indexTools->getIndexMappingProperties(
                        $entityNamespace
                    )[$entityFieldName]['properties'];
                }
            } else {
                $indexProperties = $this->indexTools->getIndexMappingProperties($entityNamespace);
                $fieldName = $value;
            }

            if (!\array_key_exists($fieldName, $indexProperties)) {
                $this->addValidationError(
                    \sprintf('%sProperty \'%s\' does not exist', $isSort ? '[Sort] ' : '', $fieldName),
                    $value
                );
            }
        }
    }

    private function checkRange(string $key, $value): void
    {
        if ('range' === $key) {
            if (\gettype($value[0]) !== \gettype($value[1])) {
                $this->addValidationError(
                    \sprintf(
                        'range values [%s, %s] must have the same type',
                        $value[0],
                        $value[1]
                    )
                );
            } elseif (\is_string($value[0])) { // date
                foreach ($value as $item) {
                    if (!$this->isDateValid($item)) {
                        $this->addValidationError(\sprintf('range value [%s] is not a valid date', $item));
                    }
                }
            }
        }
    }

    private function checkGtLtFields(string $key, $value): void
    {
        if (\in_array($key, ['gt', 'gte', 'lt', 'lte'], true)) {
            if (\is_string($value)) { // date
                if (!$this->isDateValid($value)) {
                    $this->addValidationError(\sprintf('%s value [%s] is not a valid date', $key, $value));
                }
            }
        }
    }

    private function isDateValid(string $dateString): bool
    {
        $formats = ['Y-m-d', 'Y-m-d\TH', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format($format) === $dateString) {
                return true;
            }
        }

        return false;
    }
}
