<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$collection = new RouteCollection();

$collection->add('mautic_api_getusers', new Route('/users.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:getEntities',
        '_format' => 'json'
    ),
    array(
        '_method' => 'GET',
        '_format' => 'json|xml'
    )
));

$collection->add('mautic_api_getuser', new Route('/users/{id}.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:getEntity',
        '_format' => 'json'
    ),
    array(
        '_method' => 'GET',
        '_format' => 'json|xml',
        'id'      => '\d+'
    )
));

$collection->add('mautic_api_getself', new Route('/users/self.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:getSelf',
        '_format' => 'json'
    ),
    array(
        '_method' => 'GET',
        '_format' => 'json|xml'
    )
));

/*
$collection->add('mautic_api_newuser', new Route('/users/new.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:newEntity',
        '_format' => 'json'
    ),
    array(
        '_method' => 'POST',
        '_format' => 'json|xml'
    )
));

$collection->add('mautic_api_editputuser', new Route('/users/{id}/edit.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:editEntity',
        '_format' => 'json'
    ),
    array(
        '_method' => 'PUT',
        '_format' => 'json|xml',
        'id'      => '\d+'
    )
));

$collection->add('mautic_api_editpatchuser', new Route('/users/{id}/edit.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:editEntity',
        '_format' => 'json'
    ),
    array(
        '_method' => 'PATCH',
        '_format' => 'json|xml',
        'id'      => '\d+'
    )
));

$collection->add('mautic_api_deleteuser', new Route('/users/{id}/delete.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:deleteEntity',
        '_format' => 'json'
    ),
    array(
        '_method' => 'DELETE',
        '_format' => 'json|xml',
        'id'      => '\d+'
    )
));
*/

$collection->add('mautic_api_checkpermission', new Route('/users/{id}/permissioncheck.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:isGranted',
        '_format' => 'json'
    ),
    array(
        '_method' => 'POST',
        '_format' => 'json|xml',
        'id'      => '\d+'
    )
));

$collection->add('mautic_api_getuserroles', new Route('/users/list/roles.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\UserApi:getRoles',
        '_format' => 'json'
    ),
    array(
        '_method' => 'GET',
        '_format' => 'json|xml'
    )
));

$collection->add('mautic_api_getroles', new Route('/roles.{_format}',
    array(
        '_controller' => 'MauticUserBundle:Api\RoleApi:getEntities',
        '_format' => 'json'
    ),
    array(
        '_method' => 'GET',
        '_format' => 'json|xml'
    )
));

$collection->add('mautic_api_getrole', new Route('/roles/{id}.{_format}', array(
        '_controller' => 'MauticUserBundle:Api\RoleApi:getEntity',
        '_format' => 'json'
    ),
    array(
        '_method' => 'GET',
        '_format' => 'json|xml',
        'id'      => '\d+'
    )
));

return $collection;