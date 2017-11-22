<?php

/*
 * @copyright   2015 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Helper\InputHelper;

class Tag
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $tag;

    /**
     * @param string $tag
     */
    public function __construct($tag = null)
    {
        $this->tag = $this->validateTag($tag);
    }

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadMetadata(ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('lead_tags')
            ->setCustomRepositoryClass(TagRepository::class)
            ->addIndex(['tag'], 'lead_tag_search');

        $builder->addId();
        $builder->addField('tag', Type::STRING);
    }

    /**
     * @param ApiMetadataDriver $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('tag')
            ->addListProperties(
                [
                    'id',
                    'tag',
                ]
            )
            ->build();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int
     *
     * @return Tag
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @param string $tag
     *
     * @return Tag
     *
     * @deprecated in 2.12.0. To be removed in 3.0.0. Use constructor instead
     */
    public function setTag($tag)
    {
        $this->tag = $this->validateTag($tag);

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return Tag
     */
    protected function validateTag($tag)
    {
        return InputHelper::clean($tag);
    }
}
