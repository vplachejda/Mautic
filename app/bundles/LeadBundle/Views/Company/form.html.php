<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'company');

$header = ($entity->getId())
    ?
    $view['translator']->trans(
        'mautic.company.menu.edit',
        array('%name%' => $view['translator']->trans($entity->getName()))
    )
    :
    $view['translator']->trans('mautic.company.menu.new');
$view['slots']->set("headerTitle", $header);
$groups = array_keys($fields);
sort($groups);
echo $view['form']->start($form);
?>
    <!-- start: box layout -->
    <div class="box-layout">
        <div class="col-md-3 bg-white height-auto">
            <div class="pr-lg pl-lg pt-md pb-md">


            <div>
                <?php
                echo $view['form']->row($form['isPublished']);
                echo $view['form']->row($form['publishUp']);
                echo $view['form']->row($form['publishDown']);
                echo $view['form']->row($form['owner']);
                ?>
            </div>
                <hr/>
                <ul class="list-group list-group-tabs">
                    <?php $step = 1; ?>
                    <?php foreach ($groups as $g): ?>
                        <?php if (!empty($fields[$g])): ?>
                            <li class="list-group-item <?php if ($step === 1) echo "active"; ?>">
                                <a href="#<?php echo $g; ?>" class="steps" data-toggle="tab">
                                    <?php echo $view['translator']->trans('mautic.lead.field.group.' . $g); ?>
                                </a>
                            </li>
                            <?php $step++; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <!-- step container -->
            </div>
        </div>
        <!-- container -->
        <div class="col-md-9 bg-auto height-auto bdr-l">
            <div class="tab-content">
                <!-- pane -->
                <?php
                foreach ($groups as $key => $group):
                if (isset($fields[$group])):
                $groupFields = $fields[$group];
                if (!empty($groupFields)): ?>
                <div class="tab-pane fade<?php if ($key === 0) { echo ' in active';} ?> bdr-rds-0 bdr-w-0" id="<?php echo $group; ?>">
                    <div class="pa-md bg-auto bg-light-xs bdr-b">
                        <h4 class="fw-sb"><?php echo $view['translator']->trans(
                                'mautic.lead.field.group.'.$group
                            ); ?></h4>
                    </div>
                    <div class="pa-md">
                        <?php if ($group == 'core'): ?>
                            <?php if (isset($form['companyname'])): ?>
                                <div class="form-group mb-0">
                                    <label
                                        class="control-label mb-xs"><?php echo $view['translator']->trans('mautic.core.company'); ?></label>
                                    <div class="row">
                                        <?php if (isset($form['companyname'])): ?>
                                            <div class="col-sm-4">
                                                <?php echo $view['form']->widget($form['companyname'], array('attr' => array('placeholder' => $view['translator']->trans('mautic.core.company')))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            <?php endif; ?>
                            <div class="form-group mb-0">
                                <label
                                    class="control-label mb-xs"><?php echo $form['companyemail']->vars['label']; ?></label>
                                <div class="row">
                                    <div class="col-sm-8">
                                        <?php echo $view['form']->widget($form['companyemail'], array('attr' => array('placeholder' => $form['companyemail']->vars['label']))); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($form['companynameaddress1']) || isset($form['companynameaddress2']) || isset($form['companynamecity']) || isset($form['companynamestate']) || isset($form['companynamezipcode']) || isset($form['companynamecountry'])): ?>
                                <div class="form-group mb-0">
                                    <label
                                        class="control-label mb-xs"><?php echo $view['translator']->trans('mautic.lead.field.address'); ?></label>
                                    <?php if (isset($form['companynameaddress1'])): ?>
                                        <div class="row mb-xs">
                                            <div class="col-sm-8">
                                                <?php echo $view['form']->widget($form['companynameaddress1'], array('attr' => array('placeholder' => $form['companynameaddress1']->vars['label']))); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($form['companynameaddress2'])): ?>
                                        <div class="row mb-xs">
                                            <div class="col-sm-8">
                                                <?php echo $view['form']->widget($form['companynameaddress2'], array('attr' => array('placeholder' => $form['companynameaddress2']->vars['label']))); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="row mb-xs">
                                        <?php if (isset($form['companycity'])): ?>
                                            <div class="col-sm-4">
                                                <?php echo $view['form']->widget($form['companycity'], array('attr' => array('placeholder' => $form['companycity']->vars['label']))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($form['companystate'])): ?>
                                            <div class="col-sm-4">
                                                <?php echo $view['form']->widget($form['companystate'], array('attr' => array('placeholder' => $form['companystate']->vars['label']))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="row">
                                        <?php if (isset($form['companyzipcode'])): ?>
                                            <div class="col-sm-4">
                                                <?php echo $view['form']->widget($form['companyzipcode'], array('attr' => array('placeholder' => $form['companyzipcode']->vars['label']))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($form['companycountry'])): ?>
                                            <div class="col-sm-4">
                                                <?php echo $view['form']->widget($form['companycountry'], array('attr' => array('placeholder' => $form['companycountry']->vars['label']))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr class="mnr-md mnl-md">
                            <?php endif; ?>
                            <hr class="mnr-md mnl-md">
                        <?php endif; ?>
                        <div class="form-group mb-0">
                            <div class="row">
                                <?php foreach ($groupFields as $alias => $field): ?>
                                    <?php
                                    if ($form[$alias]->isRendered()) {
                                        continue;
                                    } ?>
                                    <div class="col-sm-8">
                                        <?php echo $view['form']->row($form[$alias]); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>

            <?php
            endif;
            endif;
            endforeach;
            ?>
            </div>
            <!--/ #pane -->
        </div>

    </div>


<?php echo $view['form']->end($form); ?>