<?php

/**
 * This file is part of the pdAdmin package.
 *
 * @package     pdAdmin
 *
 * @author      Ramazan APAYDIN <iletisim@ramazanapaydin.com>
 * @copyright   Copyright (c) 2018 pdAdmin
 * @license     LICENSE
 *
 * @link        https://github.com/rmznpydn/pd-admin
 */

namespace App\Admin\Manager;

use App\Admin\Entity\Account\User;
use Doctrine\Common\Annotations\AnnotationReader;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get All Metod Permissions.
 *
 * @author  Ramazan Apaydın <iletisim@ramazanapaydin.com>
 */
class SecurityManager
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get ACL Roles.
     *
     * @return array
     */
    public function getACL(): array
    {
        // Default Roles
        $roles = [
            User::ROLE_DEFAULT => User::ROLE_DEFAULT,
            User::ROLE_ADMIN => User::ROLE_ADMIN,
            User::ROLE_ALL_ACCESS => User::ROLE_ALL_ACCESS,
        ];

        return $roles;
    }

    /**
     * Get All Method Roles.
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     *
     * @return array
     */
    public function getRoles(): array
    {
        // Finds Route Class
        $routes = $this->container->get('router')->getRouteCollection()->all();
        $classMethods = [];
        foreach ($routes as $route) {
            // Check Action
            if (isset($route->getDefaults()['_controller']) && (2 === \count($controller = explode('::', $route->getDefaults()['_controller'])))) {
                if (!class_exists($controller[0])) {
                    continue;
                }

                if (!isset($classMethods[$controller[0]])) {
                    $classMethods[$controller[0]] = [];
                }
                $classMethods[$controller[0]][] = $controller[1];
            }
        }

        // Find Class Roles
        $reader = new AnnotationReader();
        $roles = [];
        foreach ($classMethods as $class => $methods) {
            // Class Reflection
            $reflection = new \ReflectionClass($class);

            // Read Class Annotation
            if ($customRoles = $reflection->getConstant('CUSTOM_ROLES')) {
                foreach ($customRoles as $role) {
                    $roleObject = explode('_', $role);
                    if (3 === \count($roleObject)) {
                        $access = $roleObject[2];
                        $roleObject = $roleObject[0].'_'.$roleObject[1];

                        if (isset($roles[$roleObject])) {
                            $roles[$roleObject][$access] = $roleObject.'_'.$access;
                        } else {
                            $roles[$roleObject] = [$access => $roleObject.'_'.$access];
                        }
                    }
                }
            }

            // Read Method Annotation
            foreach ($methods as $method) {
                if (!$reflection->hasMethod($method)) {
                    continue;
                }

                foreach ($reader->getMethodAnnotations($reflection->getMethod($method)) as $access) {
                    if ($access instanceof IsGranted) {
                        $roleObject = explode('_', $access->getAttributes());
                        if (3 === \count($roleObject)) {
                            $access = $roleObject[2];
                            $roleObject = $roleObject[0].'_'.$roleObject[1];

                            if (isset($roles[$roleObject])) {
                                $roles[$roleObject][$access] = $roleObject.'_'.$access;
                            } else {
                                $roles[$roleObject] = [$access => $roleObject.'_'.$access];
                            }
                        }
                    }
                }
            }
        }

        // Add Widget Roles
        $widgets = $this->container->get('pd_widget.core')->getWidgets(false);
        foreach ($widgets as $widget) {
            if ($widget->getRole()) {
                foreach ($widget->getRole() as $role) {
                    $access = explode('_', $role);

                    // Set Main
                    if (!isset($roles[$access[0].'_'.$access[1]])) {
                        $roles[$access[0].'_'.$access[1]] = [];
                    }

                    // Add Role Access
                    $roles[$access[0].'_'.$access[1]][$access[2]] = $role;
                }
            }
        }

        return $roles;
    }
}
