<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\Form\Type;

use Mautic\CoreBundle\Factory\MauticFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class PageListType
 */
class PageListType extends AbstractType
{

    /**
     * @var array
     */
    private $choices = array();

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory)
    {
        $viewOther = $factory->getSecurity()->isGranted('page:pages:viewother');
        $choices = $factory->getModel('page')->getRepository()
            ->getPageList('', 0, 0, $viewOther, 'variant');
        foreach ($choices as $page) {
            $this->choices[$page['language']][$page['id']] = "{$page['title']} ({$page['id']})";
        }

        //sort by language
        ksort($this->choices);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'choices'       => $this->choices,
            'empty_value'   => false,
            'expanded'      => false,
            'multiple'      => true,
            'required'      => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'page_list';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }
}
