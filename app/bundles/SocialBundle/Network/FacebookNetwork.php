<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SocialBundle\Network;

class FacebookNetwork extends AbstractNetwork
{

    /**
     * Used in getUserData to prevent a double user search call with getUserId
     *
     * @var bool
     */
    private $preventDoubleCall = false;

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'Facebook';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getIdentifierFields()
    {
        return array(
            'facebook'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getSupportedFeatures()
    {
        return array(
            'public_profile',
            'share_button'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getAuthenticationUrl()
    {
        return 'https://www.facebook.com/dialog/oauth';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenUrl()
    {
        return 'https://graph.facebook.com/oauth/access_token';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getRequiredKeyFields()
    {
        return array(
            'clientId'      => 'mautic.social.keyfield.appid',
            'clientSecret'  => 'mautic.social.keyfield.appsecret'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'oauth2';
    }

    /**
     * Extract the tokens returned by the oauth2 callback
     *
     * @param $data
     * @return mixed
     */
    protected function parseCallbackResponse($data)
    {
        parse_str($data, $values);
        return $values;
    }

    /**
     * @param $endpoint
     *
     * @return string
     */
    public function getApiUrl($endpoint)
    {
        return "https://graph.facebook.com/$endpoint";
    }

    /**
     * Get public data
     *
     * @param $identifier
     * @param $socialCache
     *
     * @return array
     */
    public function getUserData($identifier, &$socialCache)
    {
        //tell getUserId to return a user array if it obtains it
        $this->preventDoubleCall = true;

        if ($id = $this->getUserId($identifier, $socialCache)) {
            if (is_object($id)) {
                //getUserId has already obtained the data
                $data = $id;
            } else {
                $fields = array_merge(array_keys($this->getAvailableFields()), array('id', 'link'));
                $url    = $this->getApiUrl("$id") . "?fields=" . implode(',',$fields);
                $data   = $this->makeCall($url);
            }

            if (is_object($data) && !isset($data->error)) {
                $info                  = $this->matchUpData($data);
                if (isset($data->username)) {
                    $info['profileHandle'] = $data->username;
                } elseif (isset($data->link)) {
                    $info['profileHandle'] = str_replace('https://www.facebook.com/', '', $data->link);
                } else {
                    $info['profileHandle'] = $data->id;
                }

                $info['profileImage']  = "https://graph.facebook.com/{$data->id}/picture?type=large";

                $socialCache['profile'] = $info;
            }
            $this->preventDoubleCall = false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param $identifier
     * @param $socialCache
     * @return bool
     */
    public function getUserId($identifier, &$socialCache)
    {
        if (!empty($socialCache['id'])) {
            return $socialCache['id'];
        } elseif (empty($identifier)) {
            return false;
        }

        $identifiers = $this->cleanIdentifier($identifier);

        //try a global search first using the twitter handle if
        if (isset($identifiers['facebook'])) {
            $fields = array_merge(array_keys($this->getAvailableFields()), array('id', 'link'));
            $url    = $this->getApiUrl($identifiers["facebook"]) . "?fields=" . implode(',',$fields);
            $data   = $this->makeCall($url);

            if ($data && isset($data->id)) {
                $socialCache['id'] = $data->id;

                //return the entire data set if the function has been called from getUserData()
                return ($this->preventDoubleCall) ? $data : $socialCache['id'];
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param $response
     * @return string
     */
    public function getErrorsFromResponse($response)
    {
        if (is_object($response) && isset($response->error)) {
            return $response->error->message . ' (' . $response->error->code . ')';
        }
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getAvailableFields()
    {
        return array(
            'first_name' => array('type' => 'string'),
            'last_name'  => array('type' => 'string'),
            'name'       => array('type' => 'string'),
            'gender'     => array('type' => 'string'),
            'locale'     => array('type' => 'string')
        );
    }
}
