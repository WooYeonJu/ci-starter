<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends MY_Model {

    // db 테이블 연결
    private $table = 'users';

    /**
     * login_id로 유저 정보 조회
     */
    public function get_by_login_id($login_id)
    {
        // sql 인젝션 방지용 이스케이프 처리
        $login_id_esc = $this->db->escape($login_id);

        // 조회 쿼리 생성
        $sql = "SELECT *
                FROM {$this->table}
                WHERE login_id = {$login_id_esc}
                LIMIT 1";

        $row = $this->excute($sql, 'row');  // 사용자 정보 조회한 결과값 배열 혹은 null로 반환
        return $row ? (object)$row : null;  // 결과가 있으면 객체로, 없으면 그대로 null로 반환
    }

    /**
     * 첫 로그인(회원가입)시 회원 추가
     */
    public function create($name, $login_id, $password_plain)
    {
        // 비밀번호 해시값 변환
        $hash = password_hash($password_plain, PASSWORD_DEFAULT);

        // sql insert문 생성
        $sql = self::getInsertQuery($this->table, array(
            'name'       => $name,
            'login_id'   => $login_id,
            'password'   => $hash,
            'created_at' => 'now()'  
        ));

        // INSERT 실행 후 insert_id 반환
        return $this->excute($sql, 'rtn'); 
    }

    public function create_if_not_exists($name, $login_id, $password_plain)
    {
        // 1) 사전 중복 검사 (빠른 피드백)
        if ($this->get_by_login_id($login_id)) {
            return array('ok' => false, 'error' => 'DUPLICATE');
        }

        // 2) 동시성 대비 위해 UNIQUE 제약에 의존하며 에러코드 검사
        $hash = password_hash($password_plain, PASSWORD_DEFAULT);
        $sql = self::getInsertQuery($this->table, array(
            'name'       => $name,
            'login_id'   => $login_id,
            'password'   => $hash,
            'created_at' => 'now()'
        ));

        // CI 에러 숨기고 수동 확인
        $old = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        $insert_id = $this->excute($sql, 'rtn');
        $db_err = $this->db->error(); // ['code'=> int, 'message' => string]

        $this->db->db_debug = $old;

        if (!empty($db_err['code'])) {
            if ((int)$db_err['code'] === 1062) { // Duplicate entry
                return array('ok' => false, 'error' => 'DUPLICATE');
            }
            log_message('error', '회원가입 DB 에러: code='.$db_err['code'].' msg='.$db_err['message']);
            return array('ok' => false, 'error' => 'DB');
        }

        if (!$insert_id) {
            return array('ok' => false, 'error' => 'UNKNOWN');
        }

        return array('ok' => true, 'user_id' => (int)$insert_id);
    }

}
