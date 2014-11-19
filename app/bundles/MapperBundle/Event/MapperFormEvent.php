<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\MapperBundle\Event;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class MapperFormEvent
 *
 * @package Mautic\MapperBundle\Event
 */
class MapperFormEvent extends Event
{
    /**
     * @var
     */
    protected $fields = array();

    protected $security;

    protected $application;

    /**
     */
    public function __construct(CorePermissions $security)
    {
        $this->security = $security;
    }

    public function setApplication($application)
    {
        $this->application = $application;
    }

    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @return mixed
     */
    public function getSecurity()
    {
        return $this->security;
    }

    /**
     * Add icon
     */
    public function addField($config)
    {
        $this->fields[] = $config;
    }

    /**
     * Return the icons
     *
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }
}
