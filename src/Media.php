<?php

namespace Imon\WP\Demo;

use Imon\WP\Demo\Manager;

class Media
{
    protected $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * 本地文件添加到媒体库
     *
     * @param string $filename
     * @return int|\WP_Error
     */
    public function upload($filename)
    {
        if (!is_file($from = $this->manager->uploadPath($filename))) {
            $this->manager->addFail('file does not exist > ' . $from, true);

            return 0;
        }

        $filename  = basename($filename);
        $uploaddir = \wp_upload_dir();
        $file      = rtrim($uploaddir['path'], '\/') . \DIRECTORY_SEPARATOR . $filename;

        copy($from, $file);

        $filetype = \wp_check_filetype($filename, null);
        $attachID = \wp_insert_attachment([
            'guid'           => $uploaddir['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $file);

        if (!\is_wp_error($attachID)) {
            \wp_update_attachment_metadata($attachID, \wp_generate_attachment_metadata($attachID, $file));
            $this->manager->logPost('attachment', $attachID);
        }

        return $attachID;
    }

    /**
     * 文件不在媒体库时移到媒体库
     *
     * @param int|string $filename 提供ID将跳过检查
     * @return int
     */
    public function uploadIf($filename)
    {
        if (is_numeric($filename)) {
            return (int) $filename;
        }

        $ids = \get_posts([
            'post_type'      => 'attachment',
            'name'           => basename($filename),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (empty($ids)) {
            $id = $this->upload($filename);

            return \is_wp_error($id) ? 0 : $id;
        }

        return (int) current($ids);
    }
}