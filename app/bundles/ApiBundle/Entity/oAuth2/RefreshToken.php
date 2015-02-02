<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ApiBundle\Entity\oAuth2;

use FOS\OAuthServerBundle\Model\RefreshToken as BaseRefreshToken;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use FOS\OAuthServerBundle\Model\ClientInterface;

/**
 * @ORM\Table(name="oauth2_refreshtokens")
 * @ORM\Entity
 */
class RefreshToken extends BaseRefreshToken
{

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $client;

    /**
     * @ORM\ManyToOne(targetEntity="Mautic\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $user;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    protected $token;

    /**
     * @ORM\Column(type="integer", name="expires_at", nullable=true)
     */
    protected $expiresAt;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $scope;

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
     * Set client
     *
     * @param ClientInterface $client
     *
     * @return RefreshToken
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get client
     *
     * @return ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set user
     *
     * @param UserInterface $user
     *
     * @return RefreshToken
     */
    public function setUser(UserInterface $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return UserInterface
     */
    public function getUser()
    {
        return $this->user;
    }
}
