<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\FormBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Class Submission
 * @ORM\Entity(repositoryClass="Mautic\FormBundle\Entity\SubmissionRepository")
 * @ORM\Table(name="form_submissions")
 * @Serializer\ExclusionPolicy("all")
 */
class Submission
{

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Form", inversedBy="submissions")
     * @ORM\JoinColumn(name="form_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     **/
    private $form;

    /**
     * @ORM\ManyToOne(targetEntity="Mautic\CoreBundle\Entity\IpAddress", cascade={"merge", "persist"})
     * @ORM\JoinColumn(name="ip_id", referencedColumnName="id", nullable=false)
     */
    private $ipAddress;

    /**
     * @ORM\ManyToOne(targetEntity="Mautic\LeadBundle\Entity\Lead")
     * @ORM\JoinColumn(name="lead_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    private $lead;

    /**
     * @ORM\Column(name="tracking_id", type="string", nullable=true)
     */
    private $trackingId;

    /**
     * @ORM\Column(name="date_submitted", type="datetime")
     */
    private $dateSubmitted;

    /**
     * @ORM\Column(type="string")
     */
    private $referer;

    /**
     * @ORM\ManyToOne(targetEntity="Mautic\PageBundle\Entity\Page", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="page_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    private $page;

    /**
     * @var array
     */
    private $results = array();

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dateSubmitted
     *
     * @param \DateTime $dateSubmitted
     *
     * @return Submission
     */
    public function setDateSubmitted($dateSubmitted)
    {
        $this->dateSubmitted = $dateSubmitted;

        return $this;
    }

    /**
     * Get dateSubmitted
     *
     * @return \DateTime
     */
    public function getDateSubmitted()
    {
        return $this->dateSubmitted;
    }

    /**
     * Set referer
     *
     * @param string $referer
     *
     * @return Submission
     */
    public function setReferer($referer)
    {
        $this->referer = $referer;

        return $this;
    }

    /**
     * Get referer
     *
     * @return string
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * Set form
     *
     * @param Form $form
     *
     * @return Submission
     */
    public function setForm(Form $form)
    {
        $this->form = $form;

        return $this;
    }

    /**
     * Get form
     *
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Set ipAddress
     *
     * @param \Mautic\CoreBundle\Entity\IpAddress $ipAddress
     *
     * @return Submission
     */
    public function setIpAddress(\Mautic\CoreBundle\Entity\IpAddress $ipAddress = null)
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * Get ipAddress
     *
     * @return \Mautic\CoreBundle\Entity\IpAddress
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * Get results
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get results
     *
     * @param $results
     *
     * @return Submission
     */
    public function setResults($results)
    {
        $this->results = $results;
    }

    /**
     * Set page
     *
     * @param \Mautic\PageBundle\Entity\Page $page
     *
     * @return Submission
     */
    public function setPage(\Mautic\PageBundle\Entity\Page $page = null)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Get page
     *
     * @return \Mautic\PageBundle\Entity\Page
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @return mixed
     */
    public function getLead ()
    {
        return $this->lead;
    }

    /**
     * @param mixed $lead
     */
    public function setLead (Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * @return mixed
     */
    public function getTrackingId ()
    {
        return $this->trackingId;
    }

    /**
     * @param mixed $trackingId
     */
    public function setTrackingId ($trackingId)
    {
        $this->trackingId = $trackingId;
    }
}
