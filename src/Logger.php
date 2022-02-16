<?php

namespace Imon\WP\Demo;

use Imon\WP\Demo\Manager;

class Logger
{
    protected $manager;

    protected $filename;

    protected $logs = [];

    /**
     * 简单的数据记录功能
     *
     * @param Manager $manager
     * @param string $filename 用此文件保存数据
     */
    public function __construct(Manager $manager, string $filename)
    {
        $this->manager  = $manager;
        $this->filename = $filename;

        // 即使程序出现异常也能保存
        register_shutdown_function([$this, 'save']);
    }

    /**
     * 添加想要储存的数据
     *
     * @param string $key 可用点分隔键名
     * @param mixed $value
     * @param bool $single 默认可复用相同键名添加多个值
     */
    public function log($key, $value, $single = false)
    {
        $keys  = explode('.', $key);
        $array = &$this->logs;

        foreach ($keys as $i => $key) {
            unset($keys[$i]);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        if ($single) {
            $array = $value;
        } else {
            array_push($array, $value);
        }
    }

    /**
     * 持久化存储新数据记录（应做为最终操作）
     *
     * @param bool $append 覆盖或追加
     */
    public function save($append = false)
    {
        if ($this->logs && $this->filename) {
            is_dir($this->manager->logPath()) || mkdir($this->manager->logPath() . '/', 0755);

            file_put_contents(
                $this->manager->logPath($this->filename),
                json_encode($this->logs),
                $append ? \FILE_APPEND : 0
            );

            $this->logs = [];
        }
    }

    /**
     * 判断是否有记录
     *
     * @return bool
     */
    public function isClean()
    {
        return empty($this->logs);
    }

    /**
     * 判断日志文件是否存在
     *
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->manager->logPath($this->filename));
    }
}