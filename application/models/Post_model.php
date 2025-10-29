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
        $where     = "p.is_deleted = 0";    // is_deleted가 false인것만

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

    // 파일 1건 조회
    public function get_file($file_id) {
        $file_id = (int)$file_id;
        return $this->excute("SELECT * FROM file WHERE file_id = {$file_id} LIMIT 1",'row');
    }

    // 특정 파일 하나만 삭제
    public function delete_file_row($file_id) {
        $sql = self::getDeleteQuery('file', ['file_id' => (int)$file_id]);
        return $this->excute($sql, 'exec');
    }

    // 게시글 생성 과정 중 db에 파일 경로 저장까지 완료된 후 임시 폴더에서 최종 폴더로 이동 과정에서 오류 난 경우 파일 및 포스트 삭제
    public function delete_post_cascade($post_id)
    {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;

        // 게시글 존재 유무 빠른 체크
        $exists = $this->db
            ->select('post_id')
            ->from($this->table)
            ->where('post_id', $post_id)
            ->limit(1)
            ->get()->row();
        if (!$exists) return true; // 이미 없으면 성공으로 간주

        // 삭제 대상 파일 메타 선조회 (커밋 후 실제 파일 삭제에 사용)
        $file_rows = $this->files->get_by_post_id($post_id); // 아래 Files_model 참고

        // 2) 트랜잭션 시작
        $this->db->trans_begin();

        // 2-1) 파일 메타 레코드 삭제
        $this->db->where('post_id', $post_id)->delete($this->files->getTable()); // files 테이블명 사용
        // 2-2) 게시글 삭제
        $this->db->where('post_id', $post_id)->delete($this->table);

        // 3) 트랜잭션 종료
        if ($this->db->trans_status() === FALSE) {
            $err = $this->db->error(); // ['code'=>..., 'message'=>...]
            $this->db->trans_rollback();
            log_message('error', 'delete_post_cascade DB 롤백: post_id='.$post_id.' err='.json_encode($err));
            return false;
        }

        $this->db->trans_commit();

        // 4) 실제 파일 삭제 (커밋 후 시도) — 실패해도 DB는 이미 정리됨
        $base_uploads     = FCPATH.'uploads/';
        $base_uploads_tmp = FCPATH.'uploads_tmp/';

        foreach ($file_rows as $row) {
            // 저장명/경로 방어적 처리
            $stored = isset($row->stored_name) ? basename($row->stored_name) : null;
            if (!$stored) {
                // path 컬럼만 있는 스키마라면 path에서 파일명 추출 시도
                if (!empty($row->path)) {
                    $stored = basename($row->path);
                }
            }
            if (!$stored) continue;

            // 업로드/임시 양쪽 모두 시도 (어느 쪽에 있든 삭제되게)
            $candidates = [
                $base_uploads.$stored,
                $base_uploads_tmp.$stored,
            ];

            foreach ($candidates as $absPath) {
                // 경로 공격 방지: 프로젝트 루트 밖으로 나가지 않도록 간단 보정
                if (strpos(realpath(dirname($absPath)) ?: '', realpath(FCPATH)) !== 0) {
                    log_message('error', 'delete_post_cascade: 비정상 경로 감지 skip='.$absPath);
                    continue;
                }
                if (is_file($absPath)) {
                    if (!@unlink($absPath)) {
                        log_message('error', 'delete_post_cascade: 파일 삭제 실패 '.$absPath.' (post_id='.$post_id.')');
                    } else {
                        log_message('debug', 'delete_post_cascade: 파일 삭제 ok '.$absPath.' (post_id='.$post_id.')');
                    }
                }
            }
        }

        return true;
    }

}
