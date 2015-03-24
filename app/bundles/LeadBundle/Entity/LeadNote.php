<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class LeadNote
 *
 * @package Mautic\LeadBundle\Entity
 *
 * @Serializer\ExclusionPolicy("all")
 */
class LeadNote extends FormEntity
{

    /**
     * @var int
     *
     * @Serializer\Expose
     * @Serializer\Groups({"leadNoteDetails"})
     */
    private $id;

    /**
     * @var \Mautic\LeadBundle\Entity\Lead
     */
    private $lead;

    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"leadNoteDetails"})
     */
    private $text;

    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"leadNoteDetails"})
     */
    private $type = 'general';

    /**
     * @var \DateTime
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"leadNoteDetails"})
     */
    private $dateTime;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata (ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('lead_notes')
            ->setCustomRepositoryClass('Mautic\LeadBundle\Entity\LeadNoteRepository');

        $builder->addId();

        $builder->addLead(false, 'CASCADE', false, 'notes');

        $builder->addField('text', 'text');

        $builder->createField('type', 'string')
            ->length(50)
            ->nullable()
            ->build();

        $builder->createField('dateTime', 'datetime')
            ->columnName('date_time')
            ->nullable()
            ->build();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId ()
    {
        return $this->id;
    }

    /**
     * Set text
     *
     * @param string $text
     *
     * @return LeadNote
     */
    public function setText ($text)
    {
        $this->isChanged('text', $text);
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText ()
    {
        return $this->text;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return LeadNote
     */
    public function setType ($type)
    {
        $this->isChanged('type', $type);
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType ()
    {
        return $this->type;
    }


    /**
     * Form validation rules
     *
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata (ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('text', new NotBlank(
            array('message' => 'mautic.lead.note.text.notblank')
        ));
    }

    /**
     * @return Lead
     */
    public function getLead ()
    {
        return $this->lead;
    }

    /**
     * @param Lead $lead
     */
    public function setLead (Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * @return array
     */
    public function convertToArray ()
    {
        return get_object_vars($this);
    }

    /**
     * @return mixed
     */
    public function getDateTime ()
    {
        return $this->dateTime;
    }

    /**
     * @param mixed $dateTime
     */
    public function setDateTime ($dateTime)
    {
        $this->dateTime = $dateTime;
    }
}