<?php

namespace Mautic\LeadBundle\Model\Service\DeviceTrackingService;

use DeviceDetector\DeviceDetector;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CookieHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadDevice;
use Mautic\LeadBundle\Entity\LeadDeviceRepository;
use Mautic\LeadBundle\Model\Service\DeviceTrackingService\DeviceTrackingServiceInterface;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class DeviceTrackingService.
 */
final class DeviceTrackingService implements DeviceTrackingServiceInterface
{
    /** @var CookieHelper */
    private $cookieHelper;

    /** @var EntityManager */
    private $entityManager;

    /** @var LeadDeviceRepository */
    private $leadDeviceRepository;

    /** @var Request|null */
    private $request;

    /**
     * DeviceTrackingService constructor.
     *
     * @param CookieHelper         $cookieHelper
     * @param EntityManager        $entityManager
     * @param LeadDeviceRepository $leadDeviceRepository
     * @param RequestStack         $requestStack
     */
    public function __construct(
        CookieHelper $cookieHelper,
        EntityManager $entityManager,
        LeadDeviceRepository $leadDeviceRepository,
        RequestStack $requestStack
    ) {
        $this->cookieHelper         = $cookieHelper;
        $this->entityManager        = $entityManager;
        $this->leadDeviceRepository = $leadDeviceRepository;
        $this->request              = $requestStack->getCurrentRequest();
    }

    /**
     * @return bool
     */
    public function isTracked()
    {
        return $this->getTrackedDevice() !== null;
    }

    /**
     * @return LeadDevice
     */
    public function getTrackedDevice()
    {
        $trackingId = $this->getTrackedIdentifier();
        if ($trackingId === null) {
            return null;
        }

        return $this->leadDeviceRepository->getByTrackingId($trackingId);
    }

    /**
     * @param LeadDevice     $device
     * @param bool           $replaceExistingTracking
     *
     * @return LeadDevice
     */
    public function trackCurrentDevice(LeadDevice $device, $replaceExistingTracking = false)
    {
        $trackedDevice = $this->getTrackedDevice();
        if ($trackedDevice !== null && $replaceExistingTracking === false) {
            return $trackedDevice;
        }
        if($device->getTrackingId() === null || $replaceExistingTracking === true) {
            $device->setTrackingId($this->getUniqueTrackingIdentifier());
        }
        $this->entityManager->persist($device);
        $this->cookieHelper->setCookie('mautic_device_id', $device->getTrackingId(), 31536000);
        return $device;
    }

    /**
     * @return string|null
     */
    private function getTrackedIdentifier()
    {
        if ($this->request === null) {
            return null;
        }
        $deviceTrackingId = $this->request->cookies->get('mautic_device_id', null);
        if ($deviceTrackingId === null) {
            if ($this->request->getMethod() === 'GET') {
                $deviceTrackingId = $this->request->query->get('mautic_device_id', null);
            } else {
                $deviceTrackingId = $this->request->request->get('mautic_device_id', null);
            }
        }

        return $deviceTrackingId;
    }

    /**
     * @return string
     */
    private function getUniqueTrackingIdentifier()
    {
        do {
            $generatedIdentifier = hash('sha1', uniqid(mt_rand()));
            $device              = $this->leadDeviceRepository->getByTrackingId($generatedIdentifier);
        } while ($device !== null);

        return $generatedIdentifier;
    }
}
