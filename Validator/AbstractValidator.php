<?php

namespace Nzo\ElasticQueryBundle\Validator;

abstract class AbstractValidator
{
    /**
     * @var array
     */
    protected static $validationErrors = [];

    /**
     * @return array
     */
    public function resetValidationErrors()
    {
        static::$validationErrors = [];
    }

    /**
     * @return array
     */
    public function getValidationErrors()
    {
        return static::$validationErrors;
    }

    /**
     * @param string $errorMsg
     * @param string $propertyPath
     */
    public function addValidationError($errorMsg, $propertyPath = null)
    {
        static::$validationErrors[] = [
            'propertyPath' => $propertyPath,
            'message' => $errorMsg,
        ];
    }

    /**
     * @param string $keyIndex
     */
    public function unsetValidationError($keyIndex)
    {
        unset(static::$validationErrors[$keyIndex]);
    }

    /**
     * @return array
     */
    public function getFormattedValidationErrors()
    {
        return [
            'title' => 'Validation Failed',
            'violations' => static::$validationErrors,
        ];
    }
}
