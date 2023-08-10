<?php

namespace Mautic\LeadBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Length as SymfonyLength;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Length extends SymfonyLength
{
    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return \get_class($this).'Validator';
    }
}
