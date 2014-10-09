<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'report');

$header = ($report->getId()) ?
    $view['translator']->trans('mautic.report.report.header.edit',
        array('%name%' => $view['translator']->trans($report->getTitle()))) :
    $view['translator']->trans('mautic.report.report.header.new');

$view['slots']->set("headerTitle", $header);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h3 class="panel-title">
			<?php echo $header; ?>
		</h3>
	</div>
	<div class="panel-body">
    	<?php echo $view['form']->form($form); ?>
    </div>
</div>
