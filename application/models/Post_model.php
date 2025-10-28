<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Post_model extends MY_Model
{
    private $table = 'post';

    /**
     * 목록 조회 (카테고리 전체, 최신순, 페이지네이션)
     * @return array [rows => [], total => int]
     */
    public function list_all($page = 1, $per_page = 10, $category_id = null, $keyword = null)
    {
        $page      = max(1, (int)$page);
        $per_page  = max(1, (int)$per_page);
        $offset    = ($page - 1) * $per_page;
        $where     = "p.is_deleted = 0";

        // 카테고리 분류
        if (!empty($category_id)) {
            $where .= " AND p.category_id = " . (int)$category_id;
        }
        
        // 제목 키워드 (LIKE)
        if ($keyword !== null && $keyword !== '') {
            // LIKE 안전 이스케이프
            $kw = $this->db->escape_like_str($keyword);
            // 양쪽 와일드카드
            $like = "'%{$kw}%'";
            $where .= " AND p.title LIKE {$like} ESCAPE '!'";
        }

        // 총 개수
        $sql_count = "
            SELECT COUNT(*) AS cnt
            FROM {$this->table} p
            WHERE {$where}
        ";
        $count_row = $this->excute($sql_count, 'row');
        $total     = (int)($count_row['cnt'] ?? 0);

        // 목록 (최신순)
        $sql_list = "
            SELECT
                p.post_id,
                p.title,
                p.created_at,
                u.name AS author_name
            FROM {$this->table} p
            JOIN users u ON u.user_id = p.user_id
            WHERE {$where}
            ORDER BY p.created_at DESC
            LIMIT {$offset}, {$per_page}
        ";
        $rows = $this->excute($sql_list, 'rows');

        return ['rows' => $rows, 'total' => $total];
    }

    // 카테고리 목록 뽑는 함수
    public function get_categories()
    {
        $sql = "SELECT category_id, category_name
                FROM category
                ORDER BY category_name ASC";
        return $this->excute($sql, 'rows');
    }

    // 게시물 생성 함수
    public function create_post($user_id, $category_id, $title, $detail)
    {
        $sql = self::getInsertQuery($this->table, [
            'category_id' => (int)$category_id,
            'user_id'     => (int)$user_id,
            'title'       => $title,
            'detail'      => $detail,
            'is_deleted'  => 0,
            'created_at'  => 'now()',
            'updated_at'  => 'now()'
        ]);

        return $this->excute($sql, 'rtn'); // insert_id 반환
    }

    // 단건 조회(작성자까지)
    public function get_post($post_id) {
        $post_id = (int)$post_id;
        $sql = "
        SELECT p.*, u.name AS author_name, c.category_name
        FROM {$this->table} p
        JOIN users u ON u.user_id = p.user_id
        JOIN category c ON c.category_id = p.category_id
        WHERE p.post_id = {$post_id} AND p.is_deleted = 0
        LIMIT 1";
        $row = $this->excute($sql, 'row');
        return $row ?: null;
    }

    // 파일 목록
    public function get_files($post_id) {
        $post_id = (int)$post_id;
        return $this->excute("
        SELECT file_id, original_name, stored_name, path, mime_type, size_bytes
        FROM file WHERE post_id = {$post_id} ORDER BY file_id ASC
        ", 'rows');
    }

    // 게시글 업데이트
    public function update_post($post_id, $title, $detail, $category_id) {
        $sql = self::getUpdateQuery($this->table, [
            'title'      => $title,
            'detail'     => $detail,
            'category_id'=> (int)$category_id,
            'updated_at' => 'now()'
        ], "post_id = ".(int)$post_id." AND is_deleted = 0");
        return $this->excute($sql, 'exec');
    }

    // 삭제(delete가 아니라 update로 is_deleted 변수를 true로 변환)
    public function soft_delete_post($post_id) {
        $sql = self::getUpdateQuery($this->table, [
            'is_deleted' => 1,
            'updated_at' => 'now()'
        ], "post_id = ".(int)$post_id);
        return $this->excute($sql, 'exec');
    }

    // 파일 1건 삭제(메타 반환해서 컨트롤러에서 unlink)
    public function get_file($file_id) {
        $file_id = (int)$file_id;
        return $this->excute("SELECT * FROM file WHERE file_id = {$file_id} LIMIT 1",'row');
    }
    public function delete_file_row($file_id) {
        $sql = self::getDeleteQuery('file', ['file_id' => (int)$file_id]);
        return $this->excute($sql, 'exec');
    }




}
