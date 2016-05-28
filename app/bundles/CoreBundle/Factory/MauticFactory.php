<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Factory;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Exception\FileNotFoundException;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\CoreBundle\Templating\Helper\ThemeHelper;
use Mautic\UserBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * Mautic's Factory
 */
class MauticFactory
{

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var
     */
    private $database = null;

    /**
     * @var
     */
    private $entityManager = null;

    /**
     * @var MailHelper
     */
    private $mailHelper = null;


    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $name
     *
     * @return \Mautic\CoreBundle\Model\CommonModel
     * @throws NotAcceptableHttpException
     */
    public function getModel($name)
    {
        static $models = array();

        //shortcut for models with same name as bundle
        if (strpos($name, '.') === false) {
            $name = "$name.$name";
        }

        if (!array_key_exists($name, $models)) {
            $parts = explode('.', $name);

            // @deprecated support for addon in 1.1.4; to be removed in 2.0
            if ($parts[0] == 'addon' && $parts[1] != 'addon') {
                // @deprecated 1.1.4; to be removed in 2.0; BC support for MauticAddon
                $namespace = 'MauticAddon';
                array_shift($parts);
            } elseif ($parts[0] == 'plugin' && $parts[1] != 'plugin') {
                $namespace = 'MauticPlugin';
                array_shift($parts);
            } else {
                $namespace = 'Mautic';
            }

            if (count($parts) !== 2) {
                throw new NotAcceptableHttpException($name." is not an acceptable model name.");
            }

            $modelClass = '\\'.$namespace.'\\'.ucfirst($parts[0]).'Bundle\\Model\\'.ucfirst($parts[1]).'Model';
            if (!class_exists($modelClass)) {
                throw new NotAcceptableHttpException($name." is not an acceptable model name.");
            }

            $models[$name] = new $modelClass($this);

            if (method_exists($models[$name], 'initialize')) {
                $models[$name]->initialize();
            }
        }

        return $models[$name];
    }

    /**
     * Retrieves Mautic's security object
     *
     * @return \Mautic\CoreBundle\Security\Permissions\CorePermissions
     */
    public function getSecurity()
    {
        return $this->container->get('mautic.security');
    }

    /**
     * Retrieves Symfony's security context
     *
     * @return \Symfony\Component\Security\Core\SecurityContext
     */
    public function getSecurityContext()
    {
        return $this->container->get('security.context');
    }

    /**
     * Retrieves user currently logged in
     *
     * @param bool $nullIfGuest
     *
     * @return null|User
     */
    public function getUser($nullIfGuest = false)
    {
        $token = $this->getSecurityContext()->getToken();
        $user  = ($token !== null) ? $token->getUser() : null;

        if (!$user instanceof User) {
            if ($nullIfGuest) {
                return null;
            } else {
                $user          = new User();
                $user->isGuest = true;
            }
        }

        return $user;
    }

    /**
     * Retrieves session object
     *
     * @return \Symfony\Component\HttpFoundation\Session\Session
     */
    public function getSession()
    {
        return $this->container->get('session');
    }

    /**
     * Retrieves Doctrine EntityManager
     *
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return ($this->entityManager) ? $this->entityManager : $this->container->get('doctrine')->getManager();
    }

    /**
     * @param EntityManager $em
     */
    public function setEntityManager(EntityManager $em)
    {
        $this->entityManager = $em;
    }

    /**
     * Retrieves Doctrine database connection for DBAL use
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDatabase()
    {
        return ($this->database) ? $this->database : $this->container->get('database_connection');
    }

    /**
     * @param $db
     */
    public function setDatabase($db)
    {
        $this->database = $db;
    }

    /**
     * Gets a schema helper for manipulating database schemas
     *
     * @param string $type
     * @param string $name Object name; i.e. table name
     *
     * @return mixed
     */
    public function getSchemaHelper($type, $name = null)
    {
        static $schemaHelpers = array();

        if (empty($schemaHelpers[$type])) {
            $className = "\\Mautic\\CoreBundle\\Doctrine\\Helper\\".ucfirst($type).'SchemaHelper';
            if ($type == "table") {
                //get the column helper as well
                $columnHelper         = $this->getSchemaHelper('column');
                $schemaHelpers[$type] = new $className($this->getDatabase(), MAUTIC_TABLE_PREFIX, $columnHelper);
            } else {
                $schemaHelpers[$type] = new $className($this->getDatabase(), MAUTIC_TABLE_PREFIX);
            }

        }

        if ($name !== null) {
            $schemaHelpers[$type]->setName($name);
        }

        return $schemaHelpers[$type];
    }

    /**
     * Retrieves Translator
     *
     * @return \Mautic\CoreBundle\Translation\Translator
     */
    public function getTranslator()
    {
        if (defined('IN_MAUTIC_CONSOLE')) {
            /** @var \Mautic\CoreBundle\Translation\Translator $translator */
            $translator = $this->container->get('translator');

            $translator->setLocale(
                $this->getParameter('locale')
            );

            return $translator;
        }

        return $this->container->get('translator');
    }

    /**
     * Retrieves serializer
     *
     * @return \JMS\Serializer\Serializer
     */
    public function getSerializer()
    {
        return $this->container->get('jms_serializer');
    }

    /**
     * Retrieves templating service
     *
     * @return \Symfony\Bundle\FrameworkBundle\Templating\DelegatingEngine
     */
    public function getTemplating()
    {
        if (defined('IN_MAUTIC_CONSOLE')) {
            //enter the request scope in order to be use the templating.helper.assets service
            $this->container->enterScope('request');
            $this->container->set('request', new Request(), 'request');
        }

        return $this->container->get('templating');
    }

    /**
     * Retrieves event dispatcher
     *
     * @return \Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
     */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * Retrieves request
     *
     * @return \Symfony\Component\HttpFoundation\Request|null
     */
    public function getRequest()
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if (empty($request)) {
            //likely in a test as the request is not populated for outside the container
            $request      = Request::createFromGlobals();
            $requestStack = new RequestStack();
            $requestStack->push($request);
            $this->requestStack = $requestStack;
        }

        return $request;
    }

    /**
     * Retrieves Symfony's validator
     *
     * @return \Symfony\Component\Validator\Validator
     */
    public function getValidator()
    {
        return $this->container->get('validator');
    }

    /**
     * Retrieves Mautic system parameters
     *
     * @return array
     */
    public function getSystemParameters()
    {
        return $this->container->getParameter('mautic.parameters');
    }

    /**
     * Retrieves a Mautic parameter
     *
     * @param       $id
     * @param mixed $default
     *
     * @return bool|mixed
     */
    public function getParameter($id, $default = false)
    {
        if ($id == 'db_table_prefix' && defined('MAUTIC_TABLE_PREFIX')) {
            //use the constant in case in the installer
            return MAUTIC_TABLE_PREFIX;
        }

        return ($this->container->hasParameter('mautic.'.$id)) ? $this->container->getParameter('mautic.'.$id) : $default;
    }

    /**
     * Get DateTimeHelper
     *
     * @param string $string
     * @param string $format
     * @param string $tz
     *
     * @return DateTimeHelper
     */
    public function getDate($string = null, $format = null, $tz = 'local')
    {
        static $dates;

        if (!empty($string)) {
            if ($string instanceof \DateTime) {
                $key = $string->format('U').".$format.$tz";
            } else {
                $key = "$string.$format.$tz";
            }

            if (empty($dates[$key])) {
                $dates[$key] = new DateTimeHelper($string, $format, $tz);
            }

            return $dates[$key];
        }

        //now so generate a new helper
        return new DateTimeHelper($string, $format, $tz);
    }

    /**
     * Get Router
     *
     * @return Router
     */
    public function getRouter()
    {
        return $this->container->get('router');
    }

    /**
     * Get the path to specified area.  Returns relative by default with the exception of cache and log
     * which will be absolute regardless of $fullPath setting
     *
     * @param string $name
     * @param bool   $fullPath
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getSystemPath($name, $fullPath = false)
    {
        $paths = $this->getParameter('paths');

        if ($name == 'currentTheme' || $name == 'current_theme') {
            $theme = $this->getParameter('theme');
            $path  = $paths['themes']."/$theme";
        } elseif ($name == 'cache' || $name == 'log') {
            //these are absolute regardless as they are configurable
            return $this->container->getParameter("kernel.{$name}_dir");
        } elseif ($name == 'images') {
            $path = $this->getParameter('image_path');
            if (substr($path, -1) === '/') {
                $path = substr($path, 0, -1);
            }
        } elseif ($name == 'dashboard.user' || $name == 'dashboard.global') {
            //these are absolute regardless as they are configurable
            $globalPath = $this->getParameter('dashboard_import_dir');
            if (substr($globalPath, -1) === '/') {
                $globalPath = substr($globalPath, 0, -1);
            }

            if ($name == 'dashboard.global') {

                return $globalPath;
            }

            if (!$userPath = $this->getParameter('dashboard_import_user_dir')) {
                $userPath = $globalPath;
            } elseif (substr($userPath, -1) === '/') {
                $userPath = substr($userPath, 0, -1);
            }

            $user      = $this->getUser();
            $userPath .= '/'.$user->getId();

            // @todo check is_writable
            if (!is_dir($userPath) && !file_exists($userPath)) {
                mkdir($userPath, 0755);
            }

            return $userPath;
        } elseif (isset($paths[$name])) {
            $path = $paths[$name];
        } elseif (strpos($name, '_root') !== false) {
            // Assume system root if one is not set specifically
            $path = $paths['root'];
        } else {
            throw new \InvalidArgumentException("$name does not exist.");
        }

        if ($fullPath) {
            $rootPath = (!empty($paths[$name . '_root'])) ? $paths[$name . '_root'] : $paths['root'];

            return $rootPath . '/' . $path;
        }

        return $path;
    }

    /**
     * Returns local config file path
     *
     * @param bool $checkExists If true, returns false if file doesn't exist
     *
     * @return bool
     */
    public function getLocalConfigFile($checkExists = true)
    {
        /** @var \AppKernel $kernel */
        $kernel = $this->container->get('kernel');

        return $kernel->getLocalConfigFile($checkExists);
    }

    /**
     * Get the current environment
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->container->getParameter('kernel.environment');
    }

    /**
     * Returns if Symfony is in debug mode
     *
     * @return mixed
     */
    public function getDebugMode()
    {
        return $this->container->getParameter('kernel.debug');
    }

    /**
     * returns a ThemeHelper instance for the given theme
     *
     * @param string $theme
     * @param bool   $throwException
     *
     * @return mixed
     * @throws FileNotFoundException
     * @throws \Exception
     */
    public function getTheme($theme = 'current', $throwException = false)
    {
        static $themeHelpers = array();

        if (empty($themeHelpers[$theme])) {
            try {
                $themeHelpers[$theme] = new ThemeHelper($this, $theme);
            } catch (\Exception $e) {
                if (!$throwException) {
                    if ($e instanceof FileNotFoundException) {
                        //theme wasn't found so just use the first available
                        $themes = $this->getInstalledThemes();

                        if ($theme !== 'current') {
                            //first try the default theme
                            $default = $this->getParameter('theme');
                            if (isset($themes[$default])) {
                                $themeHelpers[$default] = new ThemeHelper($this, $default);
                                $found                  = true;
                            }
                        }

                        if (empty($found)) {
                            foreach ($themes as $installedTheme => $name) {
                                try {
                                    if (isset($themeHelpers[$installedTheme])) {
                                        //theme found so return it
                                        return $themeHelpers[$installedTheme];
                                    } else {
                                        $themeHelpers[$installedTheme] = new ThemeHelper($this, $installedTheme);
                                        //found so use this theme
                                        $theme = $installedTheme;
                                        $found = true;
                                        break;
                                    }
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }
                    }
                }

                if (empty($found)) {
                    //if we get to this point then no template was found so throw an exception regardless
                    if ($throwException) {
                        throw ($e);
                    }
                }
            }
        }

        return $themeHelpers[$theme];
    }

    /**
     * Gets a list of installed themes
     *
     * @param string $specificFeature limits list to those that support a specific feature
     *
     * @return array
     */
    public function getInstalledThemes($specificFeature = 'all')
    {
        static $themes = array();

        if (empty($themes[$specificFeature])) {
            $dir = $this->getSystemPath('themes', true);

            $finder = new Finder();
            $finder->directories()->depth('0')->ignoreDotFiles(true)->in($dir);

            $themes[$specificFeature] = array();
            foreach ($finder as $theme) {
                if (file_exists($theme->getRealPath().'/config.json')) {
                    $config = json_decode(file_get_contents($theme->getRealPath() . '/config.json'), true);
                }
                // @deprecated Remove support for theme config.php in 2.0
                elseif (file_exists($theme->getRealPath() . '/config.php')) {
                    $config = include $theme->getRealPath() . '/config.php';
                } else {
                    continue;
                }

                if ($specificFeature != 'all') {
                    if (isset($config['features']) && in_array($specificFeature, $config['features'])) {
                        $themes[$specificFeature][$theme->getBasename()] = $config['name'];
                    }
                } else {
                    $themes[$specificFeature][$theme->getBasename()] = $config['name'];
                }
            }
        }

        return $themes[$specificFeature];
    }

    /**
     * Returns MailHelper wrapper for Swift_Message via $helper->message
     *
     * @param bool $cleanSlate False to preserve current settings, i.e. to process batched emails
     *
     * @return MailHelper
     */
    public function getMailer($cleanSlate = true)
    {
        if ($this->mailHelper == null) {
            $this->mailHelper = new MailHelper(
                $this, $this->container->get('mailer'), array(
                    $this->getParameter('mailer_from_email') => $this->getParameter('mailer_from_name')
                )
            );
        } else {
            $this->mailHelper->reset($cleanSlate);
        }

        return $this->mailHelper;
    }

    /**
     * Guess the IP address from current session.
     *
     * @return string
     */
    public function getIpAddressFromRequest()
    {
        $request   = $this->getRequest();
        $ipHolders = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ipHolders as $key) {
            if ($request->server->get($key)) {
                $ip = $request->server->get($key);

                if (strpos($ip, ',') !== false) {
                    // Multiple IPs are present so use the last IP which should be the most reliable IP that last connected to the proxy
                    $ips = explode(',', $ip);
                    array_walk($ips, create_function('&$val', '$val = trim($val);'));

                    if ($internalIps = $this->getParameter('do_not_track_internal_ips')) {
                        $ips = array_diff($ips, $internalIps);
                    }

                    $ip = end($ips);
                }

                $ip = trim($ip);

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {

                    return $ip;
                }
            }
        }

        // if everything else fails
        return '127.0.0.1';
    }

    /**
     * Get an IpAddress entity for current session or for passed in IP address
     *
     * @param string $ip
     *
     * @return IpAddress
     */
    public function getIpAddress($ip = null)
    {
        static $ipAddresses = array();

        if ($ip === null) {
            $ip = $this->getIpAddressFromRequest();
        }

        if (empty($ip)) {
            //assume local as the ip is empty
            $ip = '127.0.0.1';
        }

        if (empty($ipAddress[$ip])) {
            $repo      = $this->getEntityManager()->getRepository('MauticCoreBundle:IpAddress');
            $ipAddress = $repo->findOneByIpAddress($ip);
            $saveIp    = ($ipAddress === null);

            if ($ipAddress === null) {
                $ipAddress = new IpAddress();
                $ipAddress->setIpAddress($ip);
            }

            // Ensure the do not track list is inserted
            $doNotTrack  = $this->getParameter('do_not_track_ips', array());
            if (!is_array($doNotTrack)) {
                $doNotTrack = array();
            }
            $internalIps = $this->getParameter('do_not_track_internal_ips', array());
            if (!is_array($internalIps)) {
                $internalIps = array();
            }
            $doNotTrack  = array_merge(array('127.0.0.1', '::1'), $doNotTrack, $internalIps);
            $ipAddress->setDoNotTrackList($doNotTrack);

            $details = $ipAddress->getIpDetails();
            if ($ipAddress->isTrackable() && empty($details['city']))  {
                // Get the IP lookup service

                // Fetch the data
                /** @var \Mautic\CoreBundle\IpLookup\AbstractLookup $ipLookup */
                $ipLookup = $this->container->get('mautic.ip_lookup');

                if ($ipLookup) {
                    $details = $ipLookup->setIpAddress($ip)
                        ->getDetails();

                    $ipAddress->setIpDetails($details);

                    // Save new details
                    $saveIp = true;
                }
            }

            if ($saveIp) {
                $repo->saveEntity($ipAddress);
            }

            $ipAddresses[$ip] = $ipAddress;
        }

        return $ipAddresses[$ip];
    }

    /**
     * Retrieves the application's version number
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->container->get('kernel')->getVersion();
    }

    /**
     * Get Symfony's logger
     *
     * @param bool|false $system
     *
     * @return \Monolog\Logger
     */
    public function getLogger($system = false)
    {
        if ($system) {
            return $this->container->get('logger');
        } else {
            return $this->container->get('monolog.logger.mautic');
        }
    }

    /**
     * Get a mautic helper service
     *
     * @param $helper
     *
     * @return object
     */
    public function getHelper($helper)
    {
        switch ($helper) {
            case 'template.assets':
                return $this->container->get('templating.helper.assets');
            case 'template.slots':
                return $this->container->get('templating.helper.slots');
            case 'template.form':
                return $this->container->get('templating.helper.form');
            case 'template.translator':
                return $this->container->get('templating.helper.translator');
            case 'template.router':
                return $this->container->get('templating.helper.router');
            default:
                return $this->container->get('mautic.helper.'.$helper);
        }
    }

    /**
     * Get's the Symfony kernel
     *
     * @return \AppKernel
     */
    public function getKernel()
    {
        return $this->container->get('kernel');
    }

    /**
     * Get's an array of details for Mautic core bundles
     *
     * @param bool|false $includePlugins
     *
     * @return array|mixed
     */
    public function getMauticBundles($includePlugins = false)
    {
        $bundles = $this->container->getParameter('mautic.bundles');
        if ($includePlugins) {
            $plugins  = $this->container->getParameter('mautic.plugin.bundles');
            $bundles = array_merge($bundles, $plugins);
        }

        return $bundles;
    }

    /**
     * Get's an array of details for enabled Mautic plugins
     *
     * @return array
     */
    public function getPluginBundles()
    {
        return $this->getKernel()->getPluginBundles();
    }

    /**
     * Gets an array of a specific bundle's config settings
     *
     * @param        $bundleName
     * @param string $configKey
     * @param bool   $includePlugins
     *
     * @return mixed
     * @throws \Exception
     */
    public function getBundleConfig($bundleName, $configKey = '', $includePlugins = false)
    {
        // get the configs
        $configFiles = $this->getMauticBundles($includePlugins);

        // if no bundle name specified we throw
        if (!$bundleName) {
            throw new \Exception('Bundle name not supplied');
        }

        // check for the bundle config requested actually exists
        if (!array_key_exists($bundleName, $configFiles)) {
            throw new \Exception('Bundle '.$bundleName.' does not exist');
        }

        // get the specific bundle's configurations
        $bundleConfig = $configFiles[$bundleName]['config'];

        // no config key supplied so just return the bundle's config
        if (!$configKey) {
            return $bundleConfig;
        }

        // check that the key exists
        if (!array_key_exists($configKey, $bundleConfig)) {
            throw new \Exception('Key '.$configKey.' does not exist in bundle '.$bundleName);
        }

        // we didn't throw so we can send the key value
        return $bundleConfig[$configKey];
    }

    /**
     * @param $service
     *
     * @return bool
     */
    public function serviceExists($service)
    {
        return $this->container->has($service);
    }
}
