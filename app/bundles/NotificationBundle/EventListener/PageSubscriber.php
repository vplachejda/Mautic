<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\NotificationBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\PageBundle\Event\PageDisplayEvent;
use Mautic\PageBundle\PageEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class PageSubscriber
 *
 * @package Mautic\NotificationBundle\EventListener
 */
class PageSubscriber extends CommonSubscriber
{
    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            PageEvents::PAGE_ON_DISPLAY => array('onPageDisplay', 0)
        );
    }

    /**
     * @param PageDisplayEvent $event
     */
    public function onPageDisplay(PageDisplayEvent $event)
    {
        if (! $this->factory->getParameter('notification_enabled')) {
            return;
        }

        $router = $this->factory->getRouter();
        $appId = $this->factory->getParameter('notification_app_id');
        $safariWebId = $this->factory->getParameter('notification_safari_web_id');

        /** @var \Mautic\CoreBundle\Templating\Helper\AssetsHelper $assetsHelper */
        $assetsHelper = $this->factory->getHelper('template.assets');

        $assetsHelper->addScript($router->generate('mautic_js'), 'onPageDisplay_headClose', true);
        $assetsHelper->addScript('https://cdn.onesignal.com/sdks/OneSignalSDK.js', 'onPageDisplay_headClose');

        $manifestUrl = $router->generate('mautic_onesignal_manifest');
        $assetsHelper->addCustomDeclaration('<link rel="manifest" href="' . $manifestUrl . '" />', 'onPageDisplay_headClose');

        $leadAssociationUrl = $router->generate('mautic_subscribe_notification', array(), UrlGeneratorInterface::ABSOLUTE_URL);

        $oneSignalInit = <<<JS

    var OneSignal = OneSignal || [];
    
    OneSignal.push(["init", {
        appId: "{$appId}",
        safari_web_id: "{$safariWebId}",
        autoRegister: true,
        notifyButton: {
            enable: false // Set to false to hide
        }
    }]);

    var postUserIdToMautic = function(userId) {
        var xhr = new XMLHttpRequest();

        xhr.open('post', '{$leadAssociationUrl}', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('osid=' + userId);
    };

    OneSignal.getUserId(function(userId) {
        if (! userId) {
            OneSignal.on('subscriptionChange', function(isSubscribed) {
                if (isSubscribed) {
                    OneSignal.getUserId(function(newUserId) {
                        postUserIdToMautic(newUserId);
                    });
                }
            });
        } else {
            postUserIdToMautic(userId);
        }
    });
    
    // Just to be sure we've grabbed the ID
    window.onbeforeunload = function() {
        OneSignal.getUserId(function(userId) {
            if (userId) {
                postUserIdToMautic(userId);
            }        
        });    
    };
JS;

        $assetsHelper->addScriptDeclaration($oneSignalInit, 'onPageDisplay_headClose');
    }
}
