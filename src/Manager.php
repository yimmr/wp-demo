<?php

namespace Imon\WP\Demo;

use ErrorException;
use RuntimeException;
use Throwable;

class Manager
{
    /** @var \Imon\WP\Demo\Media */
    public $media;

    protected $path;

    protected $pageSlug;

    /** @var \Imon\WP\Demo\Logger */
    protected $logger;

    protected $actions = [];

    protected $fail = [];

    public function __construct($path, $pageSlug = '')
    {
        $this->handleException();

        $this->path     = rtrim($path, '\/');
        $this->pageSlug = $pageSlug;
        $this->media    = new \Imon\WP\Demo\Media($this);

        $this->doActions();
    }

    public function handleException()
    {
        error_reporting(-1);

        set_error_handler(function ($level, $message, $file = '', $line = 0, $context = []) {
            if (error_reporting() & $level) {
                if (in_array($level, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE])) {
                    throw new ErrorException($message, 0, $level, $file, $line);
                }
            }
        });
    }

    /**
     * 处理任务请求
     */
    protected function doActions()
    {
        if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce($_GET['_wpnonce'], 'imon-demo-action')) {
            return;
        }

        if (!isset($_GET['action']) || !isset($_GET['file'])) {
            return;
        }

        if (!$this->isWritable()) {
            return $this->addFail(\__('任务要求此目录拥有可写的权限：', 'imondemo') . $this->logPath(), true);
        }

        if (method_exists($this, $method = "{$_GET['action']}Data")) {
            array_map([$this, $method], $_GET['file'] == 'all' ? $this->getActions() : [$_GET['file']]);
        }
    }

    /**
     * 填充指定数据
     *
     * @param string $name
     */
    protected function postData($filename)
    {
        // 判断任务文件不存在或已填充数据时退出
        if (!file_exists($file = $this->actionPath($filename)) || $this->exists($filename)) {
            return $this->addFail($filename);
        }

        if (!is_array($action = json_decode(file_get_contents($file), true))) {
            return $this->addFail($filename);
        }

        $this->logger = new Logger($this, $filename);

        try {
            call_user_func((function () use ($action) {
                unset($action['this']);
                extract($action);

                $manager   = $this;
                $media     = $this->media;
                $modelSave = new ModelSave($manager);
                $Multiple  = \Imon\WP\Demo\Multiple::class;

                if (isset($data_files) && is_array($data_files)) {
                    extract($manager->dataFiles($data_files));
                }

                if (isset($inc)) {
                    include_once $manager->incPath($inc);
                } elseif (isset($steps)) {
                    eval(is_array($steps) ? implode('', $steps) : $steps);
                } else {
                    throw new RuntimeException;
                }
            })->bindTo($this, null));
        } catch (Throwable $th) {
            if ($this->logger->isClean()) {
                return $this->addFail($filename);
            }
        }

        $this->logger->log('_filled', 1, true);
        $this->logger->save();
    }

    /**
     * 删除填充的数据
     *
     * @param string $filename
     */
    protected function deleteData($filename)
    {
        if (!file_exists($file = $this->logPath($filename))) {
            return $this->addFail($filename);
        }

        $context    = (array) json_decode(file_get_contents($file), true);
        $simpleType = ['post', 'attachment', 'comment'];

        // 每种数据删除后移除一个记录，记录全无时删除文件
        foreach ($context as $type => $value) {
            $results = $value;

            if (in_array($type == 'menu_item' ? 'post' : $type, $simpleType)) {
                $results = array_filter($value, function ($id) use ($type) {
                    return !call_user_func("wp_delete_{$type}", $id, true);
                });
            } elseif ($type == 'term') {
                $results = array_filter($value, function ($ids, $taxonomy) {
                    return !empty(array_filter($ids, function ($id) use ($taxonomy) {
                        return \is_wp_error($res = \wp_delete_term($id, $taxonomy)) || !$res;
                    }));
                }, \ARRAY_FILTER_USE_BOTH);
            } elseif ($type == 'metadata') {
                $results = array_filter($value, function ($args) {
                    return !\delete_metadata(...$args);
                });
            } elseif ($type == 'user') {
                $results = array_filter($value, function ($id) {
                    return !\wp_delete_user($id);
                });
            } elseif ($type == 'nav_menu') {
                $results = array_filter($value, function ($id) {
                    return \is_wp_error($result = \wp_delete_nav_menu($id)) || !$result;
                });
            }

            if (empty($results)) {
                unset($context[$type]);
            } else {
                $context[$type] = $results;
            }
        }

        if (file_exists($deleteFile = $this->incPath('delete.php'))) {
            call_user_func((function () use (&$context, $filename, $deleteFile) {
                require_once $deleteFile;
            })->bindTo($this, null));
        }

        if (empty($context) || (count($context) == 1 && isset($context['_filled']))) {
            @unlink($file);
            is_file($file) && $this->addFail(\__('已清空数据，但无法移除文件：', 'imondemo') . $file, true);
        } else {
            @file_put_contents($file, json_encode($context));
            $this->addFail(\__('遇到错误，未能彻底清除数据', 'imondemo') . " {$filename}: " . json_encode($context), true);
        }
    }

    /**
     * 获取所有任务
     *
     * @param bool $sort
     */
    public function getActions($sort = false)
    {
        if (empty($this->actions) && ($handle = opendir($this->actionPath()))) {
            while (($entry = readdir()) !== false) {
                if (
                    strtolower(pathinfo($entry, \PATHINFO_EXTENSION)) == 'json'
                    && is_file($this->actionPath($entry))) {
                    $this->actions[] = $entry;
                }
            }

            closedir($handle);
        }

        if (!$sort) {
            return $this->actions;
        }

        $actions = $this->actions;

        usort($actions, function ($a, $b) {
            return $this->exists($a) <=> $this->exists($b);
        });

        return $actions;
    }

    /**
     * 记录新增的数据，实现数据追踪和删除
     *
     * @param string $key
     * @param mixed $value
     */
    public function logPost($key, $value)
    {
        $this->logger->log($key, $value);
    }

    /**
     * 批量加载数据
     *
     * @param array $dataFiles
     * @return array
     */
    public function dataFiles($dataFiles)
    {
        $result = [];

        foreach ($dataFiles as $filename) {
            $name          = str_replace(' ', '', lcfirst(ucwords(preg_replace('/[-_]/', ' ', $filename))));
            $result[$name] = $this->data($filename);
        }

        return $result;
    }

    /**
     * 读取自定义数据
     *
     * @param string $filename
     * @return array
     */
    public function data($filename)
    {
        return json_decode(file_get_contents($this->actionPath('data', $this->filename($filename) . '.json')), true);
    }

    /**
     * 返回不带扩展名的文件名
     *
     * @param string $path
     * @return string
     */
    public function filename($path)
    {
        return pathinfo($path, \PATHINFO_FILENAME);
    }

    /**
     * 判断能否写入记录
     *
     * @return bool
     */
    public function isWritable()
    {
        return !file_exists($path = $this->logPath()) || (is_dir($path) && is_writable($path));
    }

    /**
     * 数据是否已填充
     *
     * @param string $filename
     * @return bool
     */
    public function exists($filename)
    {
        return file_exists($this->logPath($filename));
    }

    /**
     * 数据记录目录
     *
     * @param mixed $path
     * @return string
     */
    public function logPath(...$path)
    {
        return $this->pathJoin($this->path, 'logs', ...$path);
    }

    /**
     * 自定义任务目录
     *
     * @param mixed $path
     * @return string
     */
    public function actionPath(...$path)
    {
        return $this->pathJoin($this->path, 'actions', ...$path);
    }

    /**
     * 自定义静态资源目录
     *
     * @param mixed $path
     * @return string
     */
    public function uploadPath(...$path)
    {
        return $this->pathJoin($this->path, 'actions', 'uploads', ...$path);
    }

    /**
     * 自定义依赖目录
     *
     * @param mixed $path
     * @return string
     */
    public function incPath(...$path)
    {
        return $this->pathJoin($this->path, 'actions', 'inc', ...$path);
    }

    /**
     * 拼接路径
     *
     * @param mixed $path
     * @return string
     */
    public function pathJoin(...$path)
    {
        return implode(\DIRECTORY_SEPARATOR, $path);
    }

    public function getActionURL($action, $filename = 'all')
    {
        return \wp_nonce_url(\add_query_arg(['action' => $action, 'file' => $filename]), 'imon-demo-action');
    }

    /** 文件名转为常规标题 */
    public function convertToTitle(string $filename)
    {
        return implode(' ', array_map('ucfirst', explode('-', substr($filename, 0, -1 - strlen(pathinfo($filename, \PATHINFO_EXTENSION))))));
    }

    /**
     * 添加失败的任务
     *
     * @param string $filename
     */
    public function addFail(string $filename, $custom = false)
    {
        $this->fail[] = $custom ? $filename : $this->convertToTitle($filename);
    }

    /**
     * 输出失败的任务
     */
    public function outputFail()
    {
        if ($this->fail) {
            echo '<div  class="imd-notice imd-notice-error">';
            echo '<pre>';
            array_map(function ($filename) {
                echo \__('失败：', 'imondemo') . $filename . "\n";
            }, $this->fail);
            echo '</pre>';
            echo '<a href="' . \menu_page_url($this->pageSlug, false) . '" class="notice-dismiss"></a>';
            echo '</div>';
        }
    }
}