<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller {

    // 생성자
    public function __construct() {
        parent::__construct();
        $this->load->model('User_model', 'users');
    }

    // 로그인 화면으로 이동
    public function login() {

        // HTTP 요청이 GET 요청이 아닌 경우 거부
        if($this->input->method(TRUE) !== 'GET') return $this->deny(['GET']);

        // 이미 로그인 상태면 메인으로
        if ($this->session->userdata('user')) {
            return redirect('/post'); // 이미 로그인 된 상태라면 post 페이지로 이동
        }
        $this->load->view('auth/login');
    }

    // 로그인 (POST)
    public function do_login() {

        // HTTP 요청이 POST 요청이 아닌 경우 거부
        if($this->input->method(TRUE) !== 'POST') return $this->deny(['POST']);

        // 입력값 검증 - 공백 제거
        $this->form_validation->set_rules('login_id', 'Login ID', 'trim|required');
        $this->form_validation->set_rules('password', 'Password', 'trim|required');

        // 입력값 검증 실패(필수값 누락 등) 시 그대로 로그인 페이지 출력
        if (!$this->form_validation->run()) {
            return $this->load->view('auth/login');
        }

        // 폼에서 입력받은 id, password 받아올 변수 선언
        $login_id = $this->input->post('login_id', TRUE);
        $password = $this->input->post('password');

        // 입력한 아이디로 유저 정보 조회
        $user = $this->users->get_by_login_id($login_id);

        // 유저 정보가 조회되지 않거나, password 값이 틀렸을 경우
        if (!$user || !password_verify($password, $user->password)) {
            $this->session->set_flashdata('error', '아이디 또는 비밀번호가 올바르지 않습니다.');    
            $data['error'] = '아이디 또는 비밀번호가 올바르지 않습니다.';
            return $this->load->view('auth/login', $data);
        }

        // 세션 저장
        // user_id(auto_increment)
        // login_id(로그인용 아이디값 - string)
        // name(사용자 이름)
        // logged_in_at(로그인 시각)
        $this->session->set_userdata('user', array(
            'user_id' => (int)$user->user_id,
            'name'    => $user->name,
            'login_id'=> $user->login_id,
            'logged_in_at' => date('Y-m-d H:i:s')
        ));

        return redirect('post'); // 로그인 성공 후 이동
    }

    // 로그아웃
    public function logout() {

        // HTTP 요청이 POST 요청이 아닌 경우 거부
        if($this->input->method(TRUE) !== 'POST') return $this->deny(['POST']);

        // 세션 삭제
        $this->session->sess_destroy();
        return redirect('auth/login');
    }

    // 회원가입 폼
    public function register() {

        // HTTP 요청이 GET 요청이 아닌 경우 거부
        if($this->input->method(TRUE) !== 'GET') return $this->deny(['GET']);


        if ($this->session->userdata('user')) {
            return redirect('post'); // 로그인 상태면 게시판으로
        }
        $this->load->view('auth/register');
    }

    // 회원가입 요청(POST)
    public function do_register() {

        // HTTP 요청이 POST 요청이 아닌 경우 거부
        if($this->input->method(TRUE) !== 'POST') return $this->deny(['POST']);

        // 유효성 검사
        $this->form_validation->set_rules('name', '이름', 'trim|required|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('login_id', '아이디', 'trim|required|min_length[4]|max_length[50]');
        $this->form_validation->set_rules('password', '비밀번호', 'trim|required|min_length[8]|max_length[255]');
        $this->form_validation->set_rules('password_confirm', '비밀번호 확인', 'trim|required|matches[password]');

        // 유효성 검사 실패시 회원가입 화면 재로드
        if (!$this->form_validation->run()) {
            return $this->load->view('auth/register');
        }

        $name      = $this->input->post('name', TRUE);
        $login_id  = $this->input->post('login_id', TRUE);
        $password  = $this->input->post('password');

        // 아이디 중복 검사 이후 insert 진행
        $result = $this->users->create_if_not_exists($name, $login_id, $password);

        // 오류 발생시 처리
        if (!$result['ok']) {
            // 중복 오류인 경우
            // 사용자에게 alert(이미 사용 중인 아이디입니다.)
            // 회원가입 페이지 리다이렉트
            if ($result['error'] === 'DUPLICATE') {
                $this->session->set_flashdata('error', '이미 사용 중인 아이디입니다.');
                log_message('error', '회원가입 실패(중복): login_id='.$login_id);
                return redirect('auth/register');
            }
            // 그 외 오류인 경우
            $this->session->set_flashdata('error', '회원가입 처리 중 오류가 발생했습니다.');
            return redirect('auth/register');
        }

        // 회원가입 성공시 로그 기록
        log_message('info', '회원가입 성공: user_id='.$result['user_id'].' login_id='.$login_id);

        // 가입 후 자동 로그인
        $this->session->set_userdata('user', array(
            'user_id' => (int)$result['user_id'],
            'name'    => $name,
            'login_id'=> $login_id,
            'logged_in_at' => date('Y-m-d H:i:s')
        ));

        $this->session->set_flashdata('success', '회원가입이 완료되었습니다. 환영합니다!');
        return redirect('login'); // 로그인 페이지로 이동
    }

}
