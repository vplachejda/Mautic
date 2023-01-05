<?php

declare(strict_types=1);

namespace Mautic\ChannelBundle\Twig;

use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\LeadBundle\Exception\UnknownDncReasonException;
use Mautic\LeadBundle\Templating\Helper\DncReasonHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ChannelExtension extends AbstractExtension
{
    private DncReasonHelper $dncReasonHelper;
    private ChannelListHelper $channelListHelper;

    public function __construct(DncReasonHelper $dncReasonHelper, ChannelListHelper $channelListHelper)
    {
        $this->dncReasonHelper   = $dncReasonHelper;
        $this->channelListHelper = $channelListHelper;
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getChannelDncText', [$this, 'getChannelDncText']),
            new TwigFunction('getChannelLabel', [$this, 'getChannelLabel']),
        ];
    }

    public function getChannelDncText(int $reasonId): string
    {
        try {
            return $this->dncReasonHelper->toText($reasonId);
        } catch (UnknownDncReasonException $e) {
            return $e->getMessage();
        }
    }

    public function getChannelLabel(string $channel): string
    {
        return $this->channelListHelper->getChannelLabel($channel);
    }
}
