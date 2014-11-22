<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SocialBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class SocialMediaDetailsType
 *
 * @package Mautic\FormBundle\Form\Type
 */
class DetailsType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm (FormBuilderInterface $builder, array $options)
    {
        $builder->add('isPublished', 'button_group', array(
            'choice_list' => new ChoiceList(
                array(false, true),
                array('mautic.core.form.no', 'mautic.core.form.yes')
            ),
            'expanded'      => true,
            'label_attr'    => array('class' => 'control-label'),
            'multiple'      => false,
            'label'         => 'mautic.social.form.enabled',
            'empty_value'   => false,
            'required'      => false
        ));

        $keys = $options['sm_object']->getRequiredKeyFields();
        $builder->add('apiKeys', 'socialmedia_keys', array(
            'label'    => false,
            'required' => false,
            'sm_keys'  => $keys,
            'data'     => $options['data']->getApiKeys()
        ));

        if ($options['sm_object']->getAuthenticationType() == 'oauth2') {
            $url      = $options['sm_object']->getOAuthLoginUrl();
            $keys     = $options['data']->getApiKeys();
            $disabled = false;
            $label    = (isset($keys['access_token'])) ? 'reauthorize' : 'authorize';

            //find what key is needed from the URL and pass it to the JS function
            preg_match('/{(.*)}/', $url, $match);
            if (!empty($match[1])) {
                $key = $match[1];
            } else {
                $key = '';
            }

            $builder->add('authButton', 'standalone_button', array(
                'attr'     => array(
                    'class'   => 'btn btn-primary',
                    'onclick' => 'Mautic.loadAuthModal("' . $url . '", "'.$key.'", "' . $options['sm_network'] . '");'
                ),
                'label'    => 'mautic.social.form.' . $label,
                'disabled' => $disabled
            ));
        }

        //@todo - add event so that other bundles can plug in custom features
        $features = $options['sm_object']->getSupportedFeatures();
        if (!empty($features)) {
            $labels = array();
            foreach ($features as $f) {
                $labels[] = 'mautic.social.form.feature.' . $f;
            }
            $builder->add('supportedFeatures', 'choice', array(
                'choice_list'   => new ChoiceList($features, $labels),
                'expanded'      => true,
                'label_attr'    => array('class' => 'control-label'),
                'multiple'      => true,
                'label'         => 'mautic.social.form.features',
                'required'      => false
            ));
        }

        $builder->add('featureSettings', 'socialmedia_featuresettings', array(
            'label'       => 'mautic.social.form.feature.settings',
            'required'    => false,
            'data'        => $options['data']->getFeatureSettings(),
            'label_attr'  => array('class' => 'control-label'),
            'sm_network'  => $options['sm_network'],
            'lead_fields' => $options['lead_fields']
        ));

        $builder->add('name', 'hidden', array('data' => $options['sm_network']));
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Mautic\SocialBundle\Entity\SocialNetwork'
        ));

        $resolver->setRequired(array('sm_network', 'sm_object', 'lead_fields'));
    }

    /**
     * @return string
     */
    public function getName() {
        return "socialmedia_details";
    }
}