<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Comment_model extends MY_Model
{
    private $table = 'comment';
    private $SEG_LEN = 6; // 경로 세그먼트 길이(000001)

    // 경로 생성(6자리 숫자 - 아이디 앞에 빈 자리수 전부 0으로 채워주는 함수)
    private function padSeg($id)
    {
        return str_pad((string)$id, $this->SEG_LEN, '0', STR_PAD_LEFT);
    }

    /** 게시물의 모든 댓글 조회 */
    public function count_by_post($post_id)
    {
        $post_id = (int)$post_id;

        $sql = "
        SELECT COUNT(*) AS cnt
        FROM {$this->table} c
        WHERE c.post_id = {$post_id}
          AND c.is_deleted = 0
    ";

        $row = $this->excute($sql, 'row');
        return (int)($row['cnt'] ?? 0);
    }


    /** 게시물 페이지: afterPath 이후 N개 */
    public function get_by_post_page($post_id, $afterPath = '', $limit = 200)
    {
        $post_id = (int)$post_id;
        $limit   = (int)$limit;

        $cond = $afterPath !== '' ? "AND c.path > " . $this->db->escape($afterPath) : "";
        $sql = "
            SELECT c.comment_id, c.post_id, c.user_id, c.parent_id,
                   c.root_id, c.depth, c.path, c.comment_detail, c.created_at,
                   u.name AS author_name
            FROM {$this->table} c
            JOIN users u ON u.user_id = c.user_id
            WHERE c.post_id = {$post_id} AND c.is_deleted = 0
            {$cond}
            ORDER BY c.path
            LIMIT {$limit}
        ";
        return $this->excute($sql, 'rows');
    }

    public function get_by_post_page_fetch_plus($post_id, $afterPath = '', $limit = 200)
    {
        $post_id = (int)$post_id;
        $fetch   = $limit + 1;

        $cond = $afterPath !== '' ? "AND c.path > " . $this->db->escape($afterPath) : "";
        $sql = "
            SELECT c.comment_id, c.post_id, c.user_id, c.parent_id,
                c.root_id, c.depth, c.path, c.comment_detail, c.created_at,
                u.name AS author_name
            FROM {$this->table} c
            JOIN users u ON u.user_id = c.user_id
            WHERE c.post_id = {$post_id} AND c.is_deleted = 0
            {$cond}
            ORDER BY c.path
            LIMIT {$fetch}
        ";
        $rows = $this->excute($sql, 'rows');

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $nextCursor = $rows ? end($rows)['path'] : null;

        return [
            'items'      => $rows,
            'hasMore'    => $hasMore,
            'nextCursor' => $nextCursor,
        ];
    }



    /** 루트 스레드 페이지: afterPath 이후 N개 */
    public function get_thread_page($root_id, $afterPath = '', $limit = 200)
    {
        $root_id = (int)$root_id;
        $limit   = (int)$limit;
        $cond = $afterPath !== '' ? "AND c.path > " . $this->db->escape($afterPath) : "";

        $sql = "
            SELECT c.*
            FROM {$this->table} c
            WHERE c.root_id = {$root_id} AND c.is_deleted = 0
            {$cond}
            ORDER BY c.path
            LIMIT {$limit}
        ";
        return $this->excute($sql, 'rows');
    }

    /** parent_id 직계 자식만 키셋 페이지네이션 (created_at, id) */
    public function get_children_page($parent_id, $lastCreated = null, $lastId = 0, $limit = 50)
    {
        $parent_id = (int)$parent_id;
        $limit     = (int)$limit;

        $cursor = '';
        if ($lastCreated !== null) {
            $created = $this->db->escape($lastCreated);
            $id      = (int)$lastId;
            $cursor  = "AND (c.created_at, c.comment_id) > ({$created}, {$id})";
        }

        $sql = "
            SELECT c.*
            FROM {$this->table} c
            WHERE c.parent_id = {$parent_id} AND c.is_deleted = 0
            {$cursor}
            ORDER BY c.created_at, c.comment_id
            LIMIT {$limit}
        ";
        return $this->excute($sql, 'rows');
    }

    /** 댓글 작성 (Materialized Path) */
    public function create($post_id, $user_id, $detail, $parent_id = null)
    {
        $post_id   = (int)$post_id;
        $user_id   = (int)$user_id;
        $parent_id = ($parent_id !== null && (int)$parent_id > 0) ? (int)$parent_id : null;

        $this->db->trans_begin();

        if ($parent_id === null) {
            // 1) 임시 insert (루트)
            $this->db->set('post_id', $post_id)
                ->set('user_id', $user_id)
                ->set('comment_detail', $detail)
                ->set('created_at', 'NOW()', false)
                ->set('updated_at', 'NOW()', false)
                ->set('is_deleted', 0)
                ->set('parent_id', null)
                ->set('root_id', 0)
                ->set('depth', 0)
                ->set('path', '')
                ->insert($this->table);

            $new_id = (int)$this->db->insert_id();
            if ($new_id <= 0) {
                $err = $this->db->error();
                $this->db->trans_rollback();
                throw new RuntimeException('INSERT 실패: ' . $err['code'] . ' ' . $err['message']);
            }

            // 2) path/root 갱신
            $seg  = $this->padSeg($new_id);
            $path = '/' . $seg;

            $this->db->where('comment_id', $new_id)
                ->update($this->table, [
                    'root_id' => $new_id,
                    'depth'   => 0,
                    'path'    => $path,
                ]);

            if ($this->db->affected_rows() !== 1) {
                $err = $this->db->error();
                $this->db->trans_rollback();
                throw new RuntimeException('루트 path 갱신 실패: ' . $err['code'] . ' ' . $err['message']);
            }
        } else {
            // 부모 검증
            $parent = $this->db->select('comment_id, post_id, root_id, depth, path')
                ->from($this->table)
                ->where('comment_id', $parent_id)
                ->get()->row_array();
            if (!$parent) {
                $this->db->trans_rollback();
                throw new RuntimeException('부모 댓글이 존재하지 않습니다.');
            }
            if ((int)$parent['post_id'] !== $post_id) {
                $this->db->trans_rollback();
                throw new RuntimeException('부모 댓글과 게시물이 일치하지 않습니다.');
            }

            // 1) 임시 insert (자식)
            $this->db->set('post_id', $post_id)
                ->set('user_id', $user_id)
                ->set('parent_id', $parent_id)
                ->set('root_id', 0)
                ->set('depth', 0)
                ->set('path', '')
                ->set('comment_detail', $detail)
                ->set('created_at', 'NOW()', false)
                ->set('updated_at', 'NOW()', false)
                ->set('is_deleted', 0)
                ->insert($this->table);

            $new_id = (int)$this->db->insert_id();
            if ($new_id <= 0) {
                $err = $this->db->error();
                $this->db->trans_rollback();
                throw new RuntimeException('INSERT 실패: ' . $err['code'] . ' ' . $err['message']);
            }

            // 2) 경로 갱신
            $seg  = $this->padSeg($new_id);
            $path = $parent['path'] . '/' . $seg;

            $this->db->where('comment_id', $new_id)
                ->update($this->table, [
                    'root_id' => (int)$parent['root_id'],
                    'depth'   => ((int)$parent['depth']) + 1,
                    'path'    => $path,
                ]);

            if ($this->db->affected_rows() !== 1) {
                $err = $this->db->error();
                $this->db->trans_rollback();
                throw new RuntimeException('자식 path 갱신 실패: ' . $err['code'] . ' ' . $err['message']);
            }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            throw new RuntimeException('댓글 작성 트랜잭션 실패');
        }
        $this->db->trans_commit();
        return $new_id;
    }

    /** 특정 루트 스레드 전체 */
    public function get_thread($root_id)
    {
        $root_id = (int)$root_id;
        $sql = "
            SELECT c.comment_id, c.post_id, c.user_id, c.parent_id,
                   c.root_id, c.depth, c.path, c.comment_detail, c.created_at,
                   u.name AS author_name
            FROM {$this->table} c
            JOIN users u ON u.user_id = c.user_id
            WHERE c.root_id = {$root_id} AND c.is_deleted = 0
            ORDER BY c.path ASC
        ";
        return $this->excute($sql, 'rows');
    }

    /** 특정 댓글의 서브트리(자기 포함/제외) */
    public function get_subtree($comment_id, $include_self = true)
    {
        $comment_id = (int)$comment_id;
        $row = $this->db->select('path')
            ->from($this->table)
            ->where('comment_id', $comment_id)
            ->get()->row_array();
        if (!$row) return [];

        $prefix = $row['path'];
        $like   = $include_self ? $prefix . '%' : $prefix . '/%';

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE path LIKE " . $this->db->escape($like) . "
              AND is_deleted = 0
            ORDER BY path ASC
        ";
        return $this->excute($sql, 'rows');
    }

    /** 소프트 삭제: 자기만 */
    public function soft_delete($comment_id)
    {
        $comment_id = (int)$comment_id;
        return $this->db->where('comment_id', $comment_id)
            ->update($this->table, ['is_deleted' => 1]);
    }

    /** 소프트 삭제: 자기 + 하위 전부 */
    public function soft_delete_subtree($comment_id)
    {
        $comment_id = (int)$comment_id;
        $row = $this->db->select('path')
            ->from($this->table)
            ->where('comment_id', $comment_id)
            ->get()->row_array();
        if (!$row) return 0;

        $prefix = $row['path'];
        $this->db->like('path', $prefix, 'after')
            ->set('is_deleted', 1)
            ->update($this->table);

        return $this->db->affected_rows();
    }
}
