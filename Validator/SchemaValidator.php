<?php

namespace Nzo\ElasticQueryBundle\Validator;

use JsonSchema;

class SchemaValidator extends AbstractValidator
{
    /**
     * @param object $query
     * @param string $jsonSchemaFile
     * @return bool
     */
    public function isJsonSchemaValid($query, $jsonSchemaFile = 'schema.json')
    {
        $schema = sprintf('%s/../Json/Validation/%s', realpath(__DIR__), $jsonSchemaFile);

        // Validate
        $validator = new JsonSchema\Validator;
        $validator->validate($query, (object)['$ref' => 'file://'.$schema]);

        if ($validator->isValid()) {
            return true;
        }
        foreach ($validator->getErrors() as $error) {
            $this->addValidationError($error['message'], $error['property']);
        }

        if (!empty($this->getValidationErrors())) {
            foreach ($this->getValidationErrors() as $key => $error) {
                if (
                    strpos($error['message'], 'Failed to match exactly one schema') !== false
                    || $this->isInvalidErrorMessage($error['message'], $error['propertyPath'])
                ) {
                    $this->unsetValidationError($key);
                }
            }
        }

        return false;
    }

    /**
     * @param string $errorMsg
     * @param string $propertyPath
     * @return bool
     */
    private function isInvalidErrorMessage($errorMsg, $propertyPath)
    {
        $objToArrayMsg = 'Object value found, but an array is required';
        $arrayToObjMsg = 'Array value found, but an object is required';
        if ('query.search.and' === $propertyPath) {
            return strpos($errorMsg, $arrayToObjMsg) !== false || strpos($errorMsg, $objToArrayMsg) !== false;
        }
        if ('query.search.and' === $propertyPath) {
            return strpos($errorMsg, $arrayToObjMsg) !== false || strpos($errorMsg, $objToArrayMsg) !== false;
        }

        if ('query.search.or' === $propertyPath) {
            return strpos($errorMsg, $arrayToObjMsg) !== false || strpos($errorMsg, $objToArrayMsg) !== false;
        }
        if ('query.search.or' === $propertyPath) {
            return strpos($errorMsg, $arrayToObjMsg) !== false || strpos($errorMsg, $objToArrayMsg) !== false;
        }

        return false;
    }
}
