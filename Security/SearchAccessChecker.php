<?php

namespace Nzo\ElasticQueryBundle\Security;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class SearchAccessChecker
{
    private $authorizationChecker;

    public function __construct(AuthorizationChecker $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function handleSearchAccess(array $roles)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(
            [
                'roles' => [],
                'message' => 'Access Denied.',
            ]
        );

        $options = $resolver->resolve($roles);

        if (empty($roles['roles'])) {
            return;
        }

        if (!$this->authorizationChecker->isGranted($options['roles'])) {
            throw new AccessDeniedException($options['message']);
        }
    }
}
