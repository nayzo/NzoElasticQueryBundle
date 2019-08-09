<?php

namespace Nzo\ElasticQueryBundle\Validator;

use Doctrine\ORM\EntityManagerInterface;

class SearchQueryValidator extends AbstractValidator
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return bool
     */
    public function isSearchQueryValid()
    {
        return empty($this->getValidationErrors());
    }

    /**
     * @param array $query
     * @param string $entityNamespace
     * @param string $jsonSchemaFile
     * @return bool
     */
    public function checkSearchQuery(array $query, $entityNamespace)
    {
        foreach ($query as $key => $value) {

            if (\in_array($key, ['or', 'and'], true)) {
                if (\is_array($value)) {
                    $this->checkSearchQuery($value, $entityNamespace);
                } else {
                    $this->checkSearchQuery(\get_object_vars($value), $entityNamespace);
                }
            }

            if (\is_object($value)) {
                $this->checkSearchQuery(\get_object_vars($value), $entityNamespace);
            }

            $this->checkFieldExist($key, $value, $entityNamespace);
            $this->checkRange($key, $value);
            $this->checkGtLtFields($key, $value);
        }
    }

    /**
     * @param string $key
     * @param string $value
     * @param string $entityNamespace
     */
    private function checkFieldExist($key, $value, $entityNamespace)
    {
        if ('field' === $key) {
            if (\strpos($value, '.') !== false) {
                $entityFieldName = \explode('.', $value)[0];
                $fieldName = \explode('.', $value)[1];
                $metadata = $this->entityManager->getClassMetadata($entityNamespace);
                if (!\array_key_exists($entityFieldName, $metadata->associationMappings)) {
                    $this->addValidationError(
                        \sprintf(
                            'No association exist with the nested property \'%s\', found in \'%s\'',
                            $entityFieldName,
                            $value
                        )
                    );
                } else {
                    $entityFieldFQCN = $metadata->associationMappings[$entityFieldName]['targetEntity'];
                    $metadata = $this->entityManager->getClassMetadata($entityFieldFQCN);
                }
            } else {
                $metadata = $this->entityManager->getClassMetadata($entityNamespace);
                $fieldName = $value;
            }

            if (!\in_array($fieldName, $metadata->getFieldNames())) {
                $this->addValidationError(\sprintf('Property \'%s\' does not exist', $value));
            }
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    private function checkRange($key, $value)
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

    /**
     * @param string $key
     * @param mixed $value
     */
    private function checkGtLtFields($key, $value)
    {
        if (\in_array($key, ['gt', 'gte', 'lt', 'lte'], true)) {
            if (\is_string($value)) { // date
                if (!$this->isDateValid($value)) {
                    $this->addValidationError(\sprintf('%s value [%s] is not a valid date', $key, $value));
                }
            }
        }
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return bool
     */
    private function isDateValid($dateString)
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
