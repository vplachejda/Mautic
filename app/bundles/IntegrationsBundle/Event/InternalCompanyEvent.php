<?php

declare(strict_types=1);

namespace Mautic\IntegrationsBundle\Event;

use Mautic\LeadBundle\Entity\Company;
use Symfony\Contracts\EventDispatcher\Event;

class InternalCompanyEvent extends Event
{
    private string $integrationName;

    private Company $company;

    public function __construct(string $integrationName, Company $company)
    {
        $this->integrationName = $integrationName;
        $this->company         = $company;
    }

    public function getIntegrationName(): string
    {
        return $this->integrationName;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }
}
