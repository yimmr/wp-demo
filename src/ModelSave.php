<?php

namespace Imon\WP\Demo;

use Imon\WP\Demo\DataKey;
use Imon\WP\Demo\Manager;

class ModelSave
{
    protected $manager;

    protected $WPTaxonomies = ['post_category' => 'category', 'tags_input' => 'post_tag'];

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 创建并添加菜单
     *
     * @param string $location 此位置下的菜单将被清除
     * @param array $params
     * @param bool $cover
     * @return int[]|\WP_Error[]
     */
    public function menu($location, $params, $cover = true)
    {
        $locations = \get_nav_menu_locations();

        if (!isset($locations[$location]) || !($menu = \wp_get_nav_menu_object($locations[$location]))) {;
            $params['name'] = $params['name'] ?? __('Menu');

            if (\is_wp_error($menu = \wp_update_nav_menu_object(0, ['menu-name' => $params['name']]))) {
                $menu = $params['name'];
            } else {
                $this->manager->logPost('nav_menu', $menu);
            }

            if (!$menu = \wp_get_nav_menu_object($menu)) {
                return false;
            }
        }

        $locations[$location] = $menu->term_id;
        \set_theme_mod('nav_menu_locations', $locations);

        if ($cover) {
            array_map(function ($id) {
                \is_nav_menu_item($id) && \wp_delete_post($id);
            }, \get_objects_in_term($menu->term_id, 'nav_menu'));
        }

        return array_map(function ($item) use (&$menu) {
            DataKey::replaceKeys($item, 'menu');

            $item['menu-item-status'] = 'publish';

            return \wp_update_nav_menu_item($menu->term_id, 0, $item);
        }, $params['items']);
    }

    /**
     * 创建多个位置的菜单
     *
     * @param array $locations 位置对应菜单名和菜单项数组的键值对数组
     * @param bool $cover
     * @return int[]|\WP_Error[] 返回各位置执行结果数组
     */
    public function menuMany($locations, $cover = true)
    {
        $results = [];

        foreach ($locations as $location => $items) {
            $results[$location] = $this->menu($location, $items, $cover);
        }

        return $results;
    }

    /**
     * 保存帖子
     *
     * @param array $data
     * @param bool $wpError
     * @param bool $fireAfterHooks
     * @return int|\WP_Error
     */
    public function post($data, $wpError = false, $fireAfterHooks = true)
    {
        DataKey::replaceKeys($data, 'post');

        $data['post_status'] = $data['post_status'] ?? 'publish';

        foreach ($this->WPTaxonomies as $key => $taxonomy) {
            if (isset($data[$key]) && \is_object_in_taxonomy($data['post_type'], $taxonomy)) {
                $data[$key] = $this->termIf($data[$key], $taxonomy);
            } else {
                unset($data[$key]);
            }
        }

        if (isset($data['tax_input'])) {
            foreach ($data['tax_input'] as $taxonomy => $terms) {
                if (\is_object_in_taxonomy($data['post_type'], $taxonomy)) {
                    $data['tax_input'][$taxonomy] = $this->termIf($terms, $taxonomy);
                } else {
                    unset($data['tax_input'][$taxonomy]);
                }
            }
        }

        if (isset($data['_thumbnail_id'])) {
            $data['_thumbnail_id'] = $this->manager->media->uploadIf($data['_thumbnail_id']);
        }

        if (($result = \wp_insert_post($data, $wpError, $fireAfterHooks)) && !\is_wp_error($result)) {
            $this->manager->logPost('post', $result);
        }

        return $result;
    }

    /**
     * 帖子不存在时新增
     *
     * @param array $data
     * @param array $args 查询参数，默认只有title和type
     * @return int
     */
    public function postIf($data, $args = [])
    {
        if (empty($data)) {
            return 0;
        }

        $args = array_merge($this->dataFilter($data, ['post_title', 'post_type'], 'post_'), $args);

        $args['posts_per_page'] = 1;
        $args['fields']         = 'ids';

        if ($ids = current(\get_posts($args))) {
            return (int) $ids;
        }

        return (int) $this->post($data, false);
    }

    /**
     * 保存术语
     *
     * @param string|array $term
     * @param string $taxonomy
     * @param array $args 选项 `parent` 支持术语名称，不存在时新增父项
     * @return array|\WP_Error SuccessArrayKeys[term_id, term_taxonomy_id]
     */
    public function term($term, $taxonomy = '', $args = [])
    {
        if (is_array($term)) {
            $args     = $term;
            $term     = $args['term'];
            $taxonomy = $args['taxonomy'];
            unset($args['term'], $args['taxonomy']);
        }

        if (isset($args['parent']) && ($parentID = $this->termIf($args['parent'], $taxonomy))) {
            $args['parent'] = $parentID;
        }

        if (!\is_wp_error($result = \wp_insert_term($term, $taxonomy, $args))) {
            $this->manager->logPost('term.' . $taxonomy, $result['term_id']);
        }

        return $result;
    }

    /**
     * 术语不存在时新增术语
     *
     * @param string|array $term 提供正确参数可递归创建父子级
     * @param string $taxonomy
     * @param int $parent
     * @return int|int[] 返回成功的术语ID
     */
    public function termIf($term, $taxonomy = '', $parent = null)
    {
        if (!$term) {
            return 0;
        }

        // 处理子集
        if (is_array($term)) {
            $results = [];

            foreach ($term as $location) {
                if (is_array($location)) {
                    $child    = $location['child'] ?? '';
                    $location = $location['name'] ?? '';
                }

                // 父项存在时才添加子项
                if ($termid = $this->termIf($location, $taxonomy, $parent)) {
                    $results[] = $termid;

                    if (isset($child)) {
                        array_map(function ($id) use (&$results) {
                            if ($id) {
                                $results[] = $id;
                            }
                        }, (array) $this->termIf($child, $taxonomy, $termid));
                    }
                }
            }

            return $results;
        }

        // 术语已存在则返回ID
        if ($termid = \term_exists($term, $taxonomy)) {
            return intval(is_array($termid) ? $termid['term_id'] : $termid);
        }

        // 新增术语成功时返回ID
        return \is_wp_error($term = $this->term($term, $taxonomy, ['parent' => $parent])) ? 0 : intval($term['term_id']);
    }

    /**
     * 保存评论
     *
     * @param array $data
     * @return int|false
     */
    public function comment($data)
    {
        DataKey::replaceKeys($data, 'comment');

        $result = \wp_insert_comment($data);

        if ($result && !isset($data['comment_post_ID'])) {
            $this->manager->logPost('comment', $result);
        }

        $result;
    }

    /**
     * 评论不存在时新增
     *
     * @param array $data
     * @param array $args
     * @return int
     */
    public function commentIf($data, $args = [])
    {
        if (empty($data)) {
            return 0;
        }

        if ($args) {
            $args['number'] = 1;
            $args['count']  = false;

            if ($comment = current(\get_comments($args))) {
                return (int) $comment->comment_ID;
            }
        } else {
            global $wpdb;

            $wheres = [];

            foreach (['user_id', 'parent', 'comment_content'] as $key) {
                if (isset($data[$key])) {
                    $field    = $key == 'parent' ? 'comment_' . $key : $key;
                    $wheres[] = "{$field}=" . stripslashes($data[$key]);
                }
            }

            if ($comment = $wpdb->get_var("SELECT comment_ID FROM $wpdb->comments WHERE " . implode(' AND ', $wheres))) {
                return (int) $comment;
            }
        }

        return (int) $this->comment($data);
    }

    /**
     * 保存用户数据
     *
     * @param array $data
     * @return int|\WP_Error
     */
    public function user($data)
    {
        DataKey::replaceKeys($data, 'user');

        if (!\is_wp_error($result = \wp_insert_user($data))) {
            $this->manager->logPost('user', $result);
        }

        return $result;
    }

    /**
     * 用户不存在时新增
     *
     * @param array $data
     * @param array $args
     * @return int
     */
    public function userIf($data, $args = [])
    {
        if (empty($data)) {
            return 0;
        }

        $default = [];

        foreach ($this->dataFilter($data, ['user_login', 'user_nicename', 'user_email'], 'user_') as $key => $value) {
            $default[(substr($key, strlen('user_')))] = $value;
        }

        if (isset($default['email'])) {
            $default['search']         = $default['email'];
            $default['search_columns'] = 'user_email';

            unset($default['email']);
        }

        $args           = array_merge($default, $args);
        $args['fields'] = 'ID';
        $args['number'] = 1;

        if ($ids = current(\get_users($args))) {
            return (int) $ids;
        }

        $ids = $this->user($data);

        return \is_wp_error($ids) ? 0 : $ids;
    }

    /**
     * 保存元数据
     *
     * @param string $type
     * @param int $objectID
     * @param string $key
     * @param mixed $value
     * @param bool $unique
     * @return int|false
     */
    public function metadata($type, $objectID, $key, $value, $unique = false)
    {
        if ($result = \add_metadata($type, $objectID, $key, $value, $unique)) {
            $this->manager->logPost('metadata', [$type, $objectID, $key, $value]);
        }

        return $result;
    }

    /**
     * 使用键名过滤数据数组
     *
     * @param array $data
     * @param array $keys
     * @param string $prefix
     * @return array
     */
    public function dataFilter(&$data, $keys, $prefix = '')
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $result[$key] = $data[$key];
                continue;
            }

            if ($prefix) {
                $_key = substr($key, strlen($prefix));

                if (isset($data[$_key])) {
                    $result[$key] = $data[$_key];
                }
            }
        }

        return $result;
    }
}