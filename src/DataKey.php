<?php

namespace Imon\WP\Demo;

class DataKey
{
    public static $post = [
        'title'            => 'post_title',
        'author'           => 'post_author',
        'date'             => 'post_date',
        'date_gmt'         => 'post_date_gmt',
        'content'          => 'post_content',
        'content_filtered' => 'post_content_filtered',
        'excerpt'          => 'post_excerpt',
        'status'           => 'post_status',
        'type'             => 'post_type',
        'password'         => 'post_password',
        'name'             => 'post_name',
        'modified'         => 'post_modified',
        'modified_gmt'     => 'post_modified_gmt',
        'parent'           => 'post_parent',
        'mime_type'        => 'post_mime_type',
        'category'         => 'post_category',
    ];

    public static $comment = [
        'agent'        => 'comment_agent',
        'approved'     => 'comment_approved',
        'author'       => 'comment_author',
        'author_email' => 'comment_author_email',
        'author_IP'    => 'comment_author_IP',
        'author_url'   => 'comment_author_url',
        'content'      => 'comment_content',
        'date'         => 'comment_date',
        'date_gmt'     => 'comment_date_gmt',
        'karma'        => 'comment_karma',
        'parent'       => 'comment_parent',
        'post_ID'      => 'comment_post_ID',
        'type'         => 'comment_type',
        'meta'         => 'comment_meta',
    ];

    public static $user = [
        'pass'           => 'user_pass',
        'login'          => 'user_login',
        'nicename'       => 'user_nicename',
        'url'            => 'user_url',
        'email'          => 'user_email',
        'registered'     => 'user_registered',
        'activation_key' => 'user_activation_key',
    ];

    public static $menu = [
        'db-id'         => 'menu-item-db-id',
        'object-id'     => 'menu-item-object-id',
        'object'        => 'menu-item-object',
        'parent-id'     => 'menu-item-parent-id',
        'position'      => 'menu-item-position',
        'type'          => 'menu-item-type',
        'title'         => 'menu-item-title',
        'url'           => 'menu-item-url',
        'description'   => 'menu-item-description',
        'attr-title'    => 'menu-item-attr-title',
        'target'        => 'menu-item-target',
        'classes'       => 'menu-item-classes',
        'xfn'           => 'menu-item-xfn',
        'status'        => 'menu-item-status',
        'post-date'     => 'menu-item-post-date',
        'post-date-gmt' => 'menu-item-post-date-gmt',
    ];

    /**
     * 替换数组键名
     *
     * @param array $array
     */
    public static function replaceKeys(&$array, $type)
    {
        $array = array_combine(array_map(function ($key) use ($type) {
            return static::${$type}[$key] ?? $key;
        }, array_keys($array)), array_values($array));
    }
}