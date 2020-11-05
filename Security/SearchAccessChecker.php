<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function handleSearchAccess(array $accessOptions = [])
    {
        if (empty($accessOptions)) {
            return;
        }

        $resolver = new OptionsResolver();
        $resolver->setDefaults(
            [
                'role' => '',
                'message' => 'Access Denied.',
            ]
        );

        $options = $resolver->resolve($accessOptions);

        if (empty($options['role'])) {
            return;
        }

        if (!$this->authorizationChecker->isGranted($options['role'])) {
            throw new AccessDeniedException($options['message']);
        }
    }
}
