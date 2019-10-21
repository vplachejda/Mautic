<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ($item = ((isset($event['extra'])) ? $event['extra']['stat'] : false)):
    $type = isset($event['extra']['type']) ? $event['extra']['type'] : null;
    ?>
    <p>
        <?php if (!empty($item['isFailed']) && $type == 'failed') :
            $details = json_decode($item['details'], true);
            $errors  = '';
            if (isset($details['failed'])) {
                $failedDetails = $details['failed'];
                if (!is_array($failedDetails)) {
                    $failedDetails = [$failedDetails];
                }
                $errors = implode('<br />', $failedDetails);
            }
            ?>
        <span class="text-danger mt-0 mb-10"><i class="fa fa-warning"></i>
            <?php
            if (!empty($errors)) {
                echo $errors;
            } else {
                echo $view['translator']->trans('mautic.sms.timeline.event.failed');
            }
        ?></span>

        <?php endif; ?>
        <?php if (!empty($item['list_name']) && $type != 'failed') : ?>
            <br /><?php echo $view['translator']->trans('mautic.sms.timeline.event.list', ['%list%' => $item['list_name']]); ?>
        <?php endif; ?>
    </p>
<?php endif; ?>
