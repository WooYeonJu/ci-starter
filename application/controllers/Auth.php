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
        // 이미 로그인 상태면 메인으로
        if ($this->session->userdata('user')) {
            return redirect('/post'); // 이미 로그인 된 상태라면 post 페이지로 이동
        }
        $this->load->view('auth/login');
    }

    // 로그인 (POST)
    public function do_login() {
        $this->form_validation->set_rules('login_id', 'Login ID', 'trim|required');
        $this->form_validation->set_rules('password', 'Password', 'trim|required');

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

        $this->session->set_flashdata('success', '로그인 성공! 환영합니다 ' . $user->name . '님 🎉');

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
        // 세션 삭제
        $this->session->sess_destroy();
        return redirect('auth/login');
    }

    // 회원가입 폼
    public function register() {
        if ($this->session->userdata('user')) {
            return redirect('post'); // 로그인 상태면 게시판으로
        }
        $this->load->view('auth/register');
    }

    // 회원가입 요청(POST)
    public function do_register() {
        // 유효성 검사
        $this->form_validation->set_rules('name', '이름', 'trim|required|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('login_id', '아이디', 'trim|required|min_length[4]|max_length[50]');
        $this->form_validation->set_rules('password', '비밀번호', 'trim|required|min_length[8]|max_length[255]');
        $this->form_validation->set_rules('password_confirm', '비밀번호 확인', 'trim|required|matches[password]');

        if (!$this->form_validation->run()) {
            return $this->load->view('auth/register');
        }

        $name      = $this->input->post('name', TRUE);
        $login_id  = $this->input->post('login_id', TRUE);
        $password  = $this->input->post('password');

        $result = $this->users->create_if_not_exists($name, $login_id, $password);

        if (!$result['ok']) {
            if ($result['error'] === 'DUPLICATE') {
                $this->session->set_flashdata('error', '이미 사용 중인 아이디입니다.');
                log_message('error', '회원가입 실패(중복): login_id='.$login_id);
                return redirect('auth/register');
            }
            $this->session->set_flashdata('error', '회원가입 처리 중 오류가 발생했습니다.');
            return redirect('auth/register');
        }

        log_message('info', '회원가입 성공: user_id='.$result['user_id'].' login_id='.$login_id);

        // 가입 후 자동 로그인 (선호에 따라 비활성화 가능)
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
