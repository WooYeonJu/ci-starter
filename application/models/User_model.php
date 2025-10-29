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

    /**
     * 회원가입 시
     * - 아이디 중복 검사
     * - 회원가입 처리
     */
    public function create_if_not_exists($name, $login_id, $password_plain)
    {
        // 아이디 중복 검사
        if ($this->get_by_login_id($login_id)) {
            return array('ok' => false, 'error' => 'DUPLICATE');
        }

        // 비밀번호 해시값 변환(php 내장함수 활용)
        $hash = password_hash($password_plain, PASSWORD_DEFAULT);

        // sql 쿼리문 생성
        $sql = self::getInsertQuery($this->table, array(
            'name'       => $name,
            'login_id'   => $login_id,
            'password'   => $hash,
            'created_at' => 'now()'
        ));

        // CI 에러 숨기고 수동 확인
        // 이 쿼리가 실패할 경우 자동으로 오류 페이지 띄우고 이후 로직이 실행되지 않음
        // -> 개발자가 오류별 분기 처리 해야할 경우 FALSE로 설정해야함
        $old = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        // 쿼리 실행 후 에러 코드 확인
        $insert_id = $this->excute($sql, 'rtn');
        $db_err = $this->db->error();

        // 위에서 껐던 db_debug 설정을 기존 설정으로 원복
        $this->db->db_debug = $old;

        // unique로 처리된 login_id가 중복인 경우 처리
        if (!empty($db_err['code'])) {
            if ((int)$db_err['code'] === 1062) { 
                // 아이디 중복 오류
                return array('ok' => false, 'error' => 'DUPLICATE');
            }
            // 그 외 DB 오류
            // 로그에 DB 오류 기록(컬럼 누락 혹은 타입 불일치 등)
            log_message('error', '회원가입 DB 에러: code='.$db_err['code'].' msg='.$db_err['message']);
            return array('ok' => false, 'error' => 'DB');
        }

        // 알 수 없는 실패 시(insert_id가 반환되지 않은 경우)
        if (!$insert_id) {
            return array('ok' => false, 'error' => 'UNKNOWN');
        }

        // 성공 시 새로 등록한 유저 id 검사
        return array('ok' => true, 'user_id' => (int)$insert_id);
    }

}
