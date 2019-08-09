<?php

namespace Nzo\ElasticQueryBundle\Query;

use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Nzo\ElasticQueryBundle\Manager\SearchManager;
use Nzo\ElasticQueryBundle\Validator\SchemaValidator;
use Nzo\ElasticQueryBundle\Validator\SearchQueryValidator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;

class ElasticQuerySearch
{
    private $searchQueryValidator;
    private $schemaValidator;
    private $searchManager;
    private $repositoryManager;
    private $options;

    public function __construct(
        SearchQueryValidator $searchQueryValidator,
        SchemaValidator $schemaValidator,
        SearchManager $searchManager,
        RepositoryManagerInterface $repositoryManager,
        array $options
    ) {
        $this->searchQueryValidator = $searchQueryValidator;
        $this->schemaValidator = $schemaValidator;
        $this->searchManager = $searchManager;
        $this->repositoryManager = $repositoryManager;
        $this->options = $options;
    }

    /**
     * @param string|array|object $query (json, array or object)
     * @param string $entityNamespace The FQCN (fully qualified class name) of the entity to execute the search on.
     * @param null|int $page
     * @param null|int $limit
     * @return array
     */
    public function search($query, $entityNamespace, $page = null, $limit = null)
    {
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

        $this->searchQueryValidator->resetValidationErrors();
        if (!$this->schemaValidator->isJsonSchemaValid($query)) {
            $this->getValidationErrorResponse(
                $this->searchQueryValidator->getFormattedValidationErrors()
            );
        }

        $this->searchQueryValidator->checkSearchQuery(\get_object_vars($query->query->search), $entityNamespace);
        if (!$this->searchQueryValidator->isSearchQueryValid()) {
            $this->getValidationErrorResponse(
                $this->searchQueryValidator->getFormattedValidationErrors()
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
            $this->getValidationErrorResponse(
                [
                    'title' => 'Validation Failed',
                    'detail' => $exception->getMessage(),
                ]
            );
        }
    }

    public function resetValidationErrors()
    {
        $this->searchQueryValidator->resetValidationErrors();
    }

    private function getValidationErrorResponse(array $formattedErrors)
    {
        throw new BadRequestHttpException(\json_encode($formattedErrors), null, Response::HTTP_BAD_REQUEST);
    }
}
