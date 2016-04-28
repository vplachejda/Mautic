<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PluginBundle\Event;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class PluginIntegrationRequestEvent
 */
class PluginIntegrationRequestEvent extends Event
{
    /**
     * @var AbstractIntegration
     */
    private $integration;

    /**
     * @var
     */
    private $url;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var
     */
    private $headers;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var string
     */
    private $authType;

    /**
     * @var
     */
    private $response;

    /**
     * PluginIntegrationRequestEvent constructor.
     *
     * @param AbstractIntegration $integration
     * @param                     $url
     * @param                     $parameters
     * @param                     $headers
     * @param                     $method
     * @param                     $settings
     * @param                     $authType
     */
    public function __construct(AbstractIntegration $integration, $url, $parameters, $headers, $method, $settings, $authType)
    {
        $this->integration = $integration;
        $this->url         = $url;
        $this->parameters  = $parameters;
        $this->headers     = $headers;
        $this->method      = $method;
        $this->settings    = $settings;
        $this->authType    = $authType;
    }

    /**
     * Get the integration's name
     *
     * @return mixed
     */
    public function getIntegrationName()
    {
        return $this->getIntegrationName();
    }

    /**
     * Get the integration object
     *
     * @return AbstractIntegration
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @return string
     */
    public function getAuthType()
    {
        return $this->authType;
    }

    /**
     * @param $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @param $response
     *
     * @return mixed
     */
    public function getResponse($response)
    {
        return $response;
    }

    /**
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }
}
