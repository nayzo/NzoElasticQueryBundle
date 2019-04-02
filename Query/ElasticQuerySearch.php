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
    /**
     * @var SearchQueryValidator
     */
    private $searchQueryValidator;
    /**
     * @var SchemaValidator
     */
    private $schemaValidator;
    /**
     * @var SearchManager
     */
    private $searchManager;
    /**
     * @var RepositoryManagerInterface
     */
    private $repositoryManager;

    public function __construct(
        SearchQueryValidator $searchQueryValidator,
        SchemaValidator $schemaValidator,
        SearchManager $searchManager,
        RepositoryManagerInterface $repositoryManager
    ) {
        $this->searchQueryValidator = $searchQueryValidator;
        $this->schemaValidator = $schemaValidator;
        $this->searchManager = $searchManager;
        $this->repositoryManager = $repositoryManager;
    }

    /**
     * @param string|array $request
     * @param string $entityNamespace
     * @param int $page
     * @param string $limit
     * @return array
     */
    public function search($query, $entityNamespace, $page = null, $limit = null)
    {
        try {
            $query = !is_array($query) ? json_decode($query) : $query;
            $this->searchQueryValidator->resetValidationErrors();

            if (!empty($query->query)) {
                if (empty($query->query->search) || is_array($query->query->search)) {
                    $query->query->search = new \stdClass;
                }
            }

            if (!$this->schemaValidator->isJsonSchemaValid($query)) {
                $this->getValidationErrorResponse(
                    $this->searchQueryValidator->getFormattedValidationErrors()
                );
            }

            $this->searchQueryValidator->checkSearchQuery(get_object_vars($query->query->search), $entityNamespace);
            if (!$this->searchQueryValidator->isSearchQueryValid()) {
                $this->getValidationErrorResponse(
                    $this->searchQueryValidator->getFormattedValidationErrors()
                );
            }

            $elasticQuery = $this->searchManager->resolveQueryMapping($query, $entityNamespace);

            return $this->repositoryManager->getRepository($entityNamespace)->search($elasticQuery, $page, $limit);
        } catch (\Exception $exception) {
            $this->getValidationErrorResponse(
                [
                    'title' => 'Validation Failed',
                    'detail' => $exception->getMessage(),
                ]
            );
        }
    }

    private function getValidationErrorResponse(array $formattedErrors)
    {
        throw new BadRequestHttpException(json_encode($formattedErrors), null, Response::HTTP_BAD_REQUEST);
    }
}
