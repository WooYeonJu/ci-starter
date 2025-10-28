<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_model extends MY_Model
{
    private $table = 'file';

    public function insert_file($post_id, $data)
    {
        // $data: original_name, stored_name, path, mime_type, size_bytes
        $sql = self::getInsertQuery($this->table, [
            'post_id'       => (int)$post_id,
            'original_name' => $data['original_name'],
            'stored_name'   => $data['stored_name'],
            'path'          => $data['path'],
            'mime_type'     => $data['mime_type'],
            'size_bytes'    => (int)$data['size_bytes'],
        ]);
        return $this->excute($sql, 'rtn');
    }
}
