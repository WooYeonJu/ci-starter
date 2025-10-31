<!-- TODO: 게시글 삭제 시 서버에 업로드 된 파일까지 함께 삭제되도록 로직 수정 -->

<?php
defined('BASEPATH') or exit('No direct script access allowed');

class File_model extends MY_Model
{
    private $table = 'file';

    // 파일 추가 함수
    public function insert_file($post_id, $data)
    {
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
