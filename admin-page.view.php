<?php

if (!current_user_can('manage_options')) {
    return;
}

$imDemoManager = new \Imon\WP\Demo\Manager(__DIR__, $args['page_slug']);

?>

<div class="wrap">
    <h1><?php _e('管理演示数据', 'imondemo');?><span>beta</span></h1>

    <?php $imDemoManager->outputFail();?>

    <table class="imd-actions" border="0">
        <thead>
            <th><?php _e('状态', 'imondemo');?></th>
            <th><?php _e('类型', 'imondemo');?></th>
            <th><?php _e('动作', 'imondemo');?></th>
        </thead>
        <tbody>
            <?php foreach ($imDemoManager->getActions(true) as $action) {?>
            <tr>
                <?php $exists = $imDemoManager->exists($action);?>
                <td class="status<?php echo $exists ? ' status--ok' : ''; ?>">
                    <?php if ($exists) {?>
                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 32 32">
                        <path
                            d="M2.286 4.571v22.857h27.429v-18.286h-14.643l-4.571-4.571h-8.214zM1.143 2.286h10.286l4.571 4.571h14.857c0.333 0 0.607 0.107 0.821 0.321s0.321 0.488 0.321 0.821v20.571c0 0.333-0.107 0.607-0.321 0.821s-0.488 0.321-0.821 0.321h-29.714c-0.333 0-0.607-0.107-0.821-0.321s-0.321-0.488-0.321-0.821v-25.143c0-0.333 0.107-0.607 0.321-0.821s0.488-0.321 0.821-0.321zM15.929 20.214l6.464-6.464 1.607 1.607-8.071 8.107-5.643-5.679 1.607-1.607 4.036 4.036z">
                        </path>
                    </svg>
                    <?php } else {?>
                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 32 32">
                        <path id="svg_1"
                            d="m2.286,4.5725l0,22.857l27.429,0l0,-18.286l-14.643,0l-4.571,-4.571l-8.214,0l-0.001,0zm-1.143,-2.285l10.286,0l4.571,4.571l14.857,0c0.333,0 0.607,0.107 0.821,0.321s0.321,0.488 0.321,0.821l0,20.571c0,0.333 -0.107,0.607 -0.321,0.821s-0.488,0.321 -0.821,0.321l-29.714,0c-0.333,0 -0.607,-0.107 -0.821,-0.321s-0.321,-0.488 -0.321,-0.821l0,-25.143c0,-0.333 0.107,-0.607 0.321,-0.821s0.488,-0.321 0.821,-0.321l0,0.001z" />
                    </svg>
                    <?php }?>
                </td>
                <td><?php _e($imDemoManager->convertToTitle($action), 'imondemo');?></td>
                <td>
                    <a class="imd-button imd-button--sm imd-button--post"
                        href="<?php echo $imDemoManager->getActionURL('post', $action); ?>"><?php _e('填充', 'imondemo');?></a>
                    <a class="imd-button imd-button--sm imd-button--delete"
                        href="<?php echo $imDemoManager->getActionURL('delete', $action); ?>"><?php _e('删除', 'imondemo');?></a>
                </td>
            </tr>
            <?php }?>
        </tbody>
    </table>

    <div class="imd-buttons">
        <button class="imd-button imd-button--post" type="button"
            onclick="window.location.href='<?php echo $imDemoManager->getActionURL('post'); ?>'">
            <?php echo __('全部', 'imondemo') . __('填充', 'imondemo'); ?>
        </button>
        <button class="imd-button imd-button--delete"
            onclick="window.location.href='<?php echo $imDemoManager->getActionURL('delete'); ?>'">
            <?php echo __('全部', 'imondemo') . __('删除', 'imondemo'); ?>
        </button>
    </div>
</div>