<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\Query;

use Nzo\ElasticQueryBundle\Security\SearchAccessChecker;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Nzo\ElasticQueryBundle\Manager\SearchManager;
use Nzo\ElasticQueryBundle\Validator\SchemaValidator;
use Nzo\ElasticQueryBundle\Validator\QueryValidator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;

class ElasticQuerySearch
{
    private $queryValidator;
    private $schemaValidator;
    private $searchManager;
    private $searchAccessChecker;
    private $repositoryManager;
    private $options;

    public function __construct(
        QueryValidator $queryValidator,
        SchemaValidator $schemaValidator,
        SearchManager $searchManager,
        SearchAccessChecker $searchAccessChecker,
        RepositoryManagerInterface $repositoryManager,
        array $options
    ) {
        $this->queryValidator = $queryValidator;
        $this->schemaValidator = $schemaValidator;
        $this->searchManager = $searchManager;
        $this->searchAccessChecker = $searchAccessChecker;
        $this->repositoryManager = $repositoryManager;
        $this->options = $options;
    }

    /**
     * @param string|array|object $query (json, array or object)
     * @param string $entityNamespace The FQCN (fully qualified class name) of the entity to execute the search on.
     * @param null|int $page
     * @param null|int $limit
     * @param array $accessOptions The role must be valid in order to execute the search. The exception "message" is optional: ['role' => '..', 'message' => '..']
     * @return array
     */
    public function search($query, $entityNamespace, $page = null, $limit = null, array $accessOptions = [])
    {
        $this->searchAccessChecker->handleSearchAccess($accessOptions);

        // $query must be or become an object
        if (\is_array($query)) {
            $query = \json_decode(\json_encode($query));
        } elseif (\is_string($query)) {
            $query = \json_decode($query);
        }

        if (!empty($query->query)) {
            if (empty($query->query->search) || \is_array($query->query->search)) {
                $query->query->search = new \stdClass;
            }
        }

        $this->queryValidator->resetValidationErrors();
        if (!$this->schemaValidator->isJsonSchemaValid($query)) {
            $this->getValidationErrorResponse(
                $this->queryValidator->getFormattedValidationErrors()
            );
        }

        $this->queryValidator->checkSearchQuery(\get_object_vars($query->query->search), $entityNamespace);
        if (!empty($query->query->sort)) {
            $this->queryValidator->checkSortQuery($query->query->sort, $entityNamespace);
        }

        if (!$this->queryValidator->isSearchQueryValid()) {
            $this->getValidationErrorResponse(
                $this->queryValidator->getFormattedValidationErrors()
            );
        }

        try {
            $elasticQuery = $this->searchManager->resolveQueryMapping($query, $entityNamespace);

            return $this->repositoryManager->getRepository($entityNamespace)->executeSearch(
                $elasticQuery,
                $page,
                $limit,
                $this->options
            );
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            if (stripos($message, 'failed to create query') !== false) {
                $message = 'Failed to create query';
            }

            $this->getValidationErrorResponse(
                [
                    'title' => 'Validation Failed',
                    'detail' => $message,
                ]
            );
        }
    }

    public function resetValidationErrors()
    {
        $this->queryValidator->resetValidationErrors();
    }

    private function getValidationErrorResponse(array $formattedErrors)
    {
        throw new BadRequestHttpException(\json_encode($formattedErrors), null, Response::HTTP_BAD_REQUEST);
    }
}
