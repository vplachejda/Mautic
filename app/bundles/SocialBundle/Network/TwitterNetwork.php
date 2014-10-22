<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SocialBundle\Network;

class TwitterNetwork extends AbstractNetwork
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
        return 'Twitter';
    }

    /**
     * {@inheritdoc}
     *
     * @return int|mixed
     */
    public function getPriority()
    {
        return 5000;
    }


    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getIdentifierFields()
    {
        return 'twitter';
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
            'public_activity',
            'share_button'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getOAuthLoginUrl()
    {
        return $this->factory->getRouter()->generate('mautic_social_callback', array('network' => $this->getName()));
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|string
     */
    public function getAccessTokenUrl()
    {
        return 'https://api.twitter.com/oauth2/token';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function oAuthCallback($clientId = '', $clientSecret = '')
    {
        $url      = $this->getAccessTokenUrl();
        $keys     = $this->settings->getApiKeys();

        if (!empty($clientId)) {
            //callback from JS
            $keys['clientId']     = $clientId;
            $keys['clientSecret'] = $clientSecret;
        }

        if (!$url || !isset($keys['clientId']) || !isset($keys['clientSecret'])) {
            return array(false, $this->factory->getTranslator()->trans('mautic.social.missingkeys'));
        }

        $bearer = $this->getBearerToken($keys);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Basic {$bearer}",
            "Content-Type: application/x-www-form-urlencoded;charset=UTF-8"
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        $curlError = curl_error($ch);
        $data = curl_exec($ch);

        //get the body response
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body        = substr($data, $header_size);

        curl_close($ch);

        //check to see if an entity exists
        $entity = $this->getSettings();
        if ($entity == null) {
            $entity = new SocialNetwork();
            $entity->setName($this->getName());
        }

        if (empty($curlError)) {
            $values = json_decode($body, true);

            if (isset($values['access_token'])) {
                $keys['access_token'] = $values['access_token'];
                $error                = false;
            } else {
                $error = $this->parseResponse($values);
            }

            $entity->setApiKeys($keys);
        } else {
            $error = $curlError;
        }

        //save the data
        $em = $this->factory->getEntityManager();
        $em->persist($entity);
        $em->flush();

        return array($entity, $error);
    }

    /**
     * Generate a Twitter bearer token
     *
     * @param $keys
     * @return string
     */
    private function getBearerToken($keys)
    {
        //Per Twitter's recommendations
        $consumer_key    = rawurlencode($keys['clientId']);
        $consumer_secret = rawurlencode($keys['clientSecret']);

        return base64_encode($consumer_key . ':' . $consumer_secret);
    }

    /**
     * {@inheritdoc}
     *
     * @param $response
     * @return string
     */
    public function getErrorsFromResponse($response)
    {
        if (is_array($response) && isset($response['errors'])) {
            $errors = array();
            foreach ($response->errors as $e) {
                $errors[] = $e['message'] . ' ('.$e['code'].')';
            }

            return implode('; ', $errors);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getRequiredKeyFields()
    {
        return array(
            'clientId'      => 'mautic.social.keyfield.clientid',
            'clientSecret'  => 'mautic.social.keyfield.clientsecret'
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
     * @param $endpoint
     *
     * @return string
     */
    public function getApiUrl($endpoint)
    {
        return "https://api.twitter.com/1.1/$endpoint.json";
    }

    /**
     * {@inheritdoc}
     *
     * @param $identifier
     * @param $socialCache
     * @return array
     */
    public function getUserData($identifier, &$socialCache)
    {
        //tell getUserId to return a user array if it obtains it
        $this->preventDoubleCall = true;

        if ($id = $this->getUserId($identifier, $socialCache)) {
            if (is_array($id)) {
                //getUserId has alread obtained the data
                $data = $id;
            } else {
                $url  = $this->getApiUrl("users/lookup") . "?user_id={$id}&include_entities=false";
                $data = $this->makeCall($url);
            }

            if (isset($data[0])) {
                $info                  = $this->matchUpData($data[0]);
                $info['profileHandle'] = $data[0]['screen_name'];
                //remove the size variant
                $image = $data[0]['profile_image_url_https'];
                $image = str_replace(array('_normal', '_bigger', '_mini'), '', $image);
                $info['profileImage'] = $image;

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
     * @return array
     */
    public function getPublicActivity($identifier, &$socialCache)
    {
        if ($id = $this->getUserId($identifier, $socialCache)) {
            //due to the way Twitter filters, get more than 10 tweets
            $url  = $this->getApiUrl("/statuses/user_timeline")  . "?user_id={$id}&exclude_replies=true&count=25&trim_user=true";
            $data = $this->makeCall($url);

            if (!empty($data) && count($data)) {
                $socialCache['has']['activity'] = true;
                $socialCache['activity'] = array(
                    'tweets' => array(),
                    'photos' => array(),
                    'tags'   => array()
                );

                foreach ($data as $k => $d) {
                    if ($k == 10) {
                        break;
                    }

                    $tweet = array(
                        'tweet'       => $d['text'],
                        'url'         => "https://twitter.com/{$id}/status/{$d['id']}",
                        'coordinates' => $d['coordinates'],
                        'published'   => $d['created_at'],
                    );
                    $socialCache['activity']['tweets'][] = $tweet;

                    //images
                    if (isset($d['entities']['media'])) {
                        foreach ($d['entities']['media'] as $m) {
                            if ($m['type'] == 'photo') {
                                $photo = array(
                                    'url' => (isset($m['media_url_https']) ? $m['media_url_https'] : $m['media_url'])
                                );

                                $socialCache['activity']['photos'][] = $photo;
                            }
                        }
                    }

                    //hastags
                    if (isset($d['entities']['hashtags'])) {
                        foreach ($d['entities']['hashtags'] as $h) {
                            if (isset($socialCache['activity']['tags'][$h['text']])) {
                                $socialCache['activity']['tags'][$h['text']]['count']++;
                            } else {
                                $socialCache['activity']['tags'][$h['text']] = array(
                                    'count' => 1,
                                    'url'   => 'https://twitter.com/search?q=%23'.$h['text']
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param $url
     * @return mixed
     */
    public function makeCall($url) {
        $referer = $this->getRefererUrl();

        $keys = $this->settings->getApiKeys();
        if (empty($keys['access_token'])) {
            return null;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$keys['access_token']}",
            "Content-Type: application/x-www-form-urlencoded;charset=UTF-8"
        ));

        $data = curl_exec($ch);

        //get the body response
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body        = substr($data, $header_size);

        curl_close($ch);

        $values = json_decode($body, true);
        return $values;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getAvailableFields()
    {
        return array(
            "profileHandle" => array("type" => "string"),
            "name"          => array("type" => "string"),
            "location"      => array("type" => "string"),
            "description"   => array("type" => "string"),
            "url"           => array("type" => "string"),
            "time_zone"     => array("type" => "string"),
            "lang"          => array("type" => "string")
        );
    }

    /**
     * Gets the ID of the user for the network
     *
     * @param $identifier
     * @param $socialCache
     * @return mixed|null
     */
    public function getUserId($identifier, &$socialCache)
    {
        if (!empty($socialCache['id'])) {
            return $socialCache['id'];
        } elseif (empty($identifier)) {
            return false;
        }

        $url  = $this->getApiUrl("users/lookup") . "?screen_name={$identifier}&include_entities=false";
        $data = $this->makeCall($url);
        if (isset($data[0])) {
            $socialCache['id'] = $data[0]['id'];

            //return the entire data set if the function has been called from getUserData()
            return ($this->preventDoubleCall) ? $data : $socialCache['id'];
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param $identifier
     * @return null|string
     */
    public function cleanIdentifier($identifier)
    {
        if (strpos($identifier, 'http') !== false) {
            //extract the handle
            $identifier = substr(strrchr(rtrim($identifier, '/'), '/'), 1);
        }

        return urlencode($identifier);
    }
}