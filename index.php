<?php
/**
 * Plugin Name: 演示数据管理试用版
 * Description: 插件用于添加/删除演示数据，用不上时应该删除插件
 * Version: 0.1.0
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: imon
 * Author URI: https://wp.yimmr.com/
 * License: MIT
 * License URI: https://github.com/yimmr/wp-demo/blob/b580589501fd769dc34b93baa5254e5470437f5b/LICENSE
 * Text Domain: imondemo
 * Domain Path: /languages
 */

spl_autoload_register(function ($className) {
    $namespace = 'Imon\WP\Demo\\';

    if (strpos($className, $namespace) === 0) {
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . substr($className, strlen($namespace)) . '.php';
    }
});

add_action('init', function () {
    load_plugin_textdomain('imondemo', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('admin_menu', function () {
    $pageSlug   = 'imon-demo';
    $hookSuffix = add_theme_page(
        __('管理演示数据', 'imondemo'),
        __('演示数据', 'imondemo'),
        'manage_options',
        $pageSlug,
        function () use ($pageSlug) {
            load_template(plugin_dir_path(__FILE__) . 'admin-page.view.php', true, ['page_slug' => $pageSlug]);
        }
    );

    add_action('admin_enqueue_scripts', function ($pageHook) use ($hookSuffix, $pageSlug) {
        if ($pageHook == $hookSuffix) {
            wp_enqueue_style($pageSlug, plugins_url('admin-page.css', __FILE__));
        }
    });
});