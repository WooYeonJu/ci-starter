<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends MY_Controller
{

    // 생성자
    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model', 'users');
    }

    // 로그인 화면으로 이동
    public function login()
    {
        // 이미 로그인 상태면 메인으로
        if ($this->session->userdata('user')) {
            return redirect('/post'); // 이미 로그인 된 상태라면 post 페이지로 이동
        }
        // $this->load->view('auth/login');

        // 1) 빈 레이아웃을 쓰라고 '마커' 정의 (내용은 아무거나여도 됨)
        $this->template_->viewDefine('layout_empty', 'common/layout_empty.tpl');

        // 2) 실제 본문은 항상 layout_common 슬롯에 넣기
        $this->template_->viewDefine('layout_common', 'auth/login.tpl');

        $this->optimizer->setCss('auth.css');

        // 플래시 가져오기
        $flash_success = $this->session->flashdata('success');
        $flash_error   = $this->session->flashdata('error');

        // 페이지 메타/리소스/플래시 assign
        $this->template_->viewAssign([
            'title'         => '로그인',
            'flash_success' => $this->session->flashdata('success'),
            'flash_error'   => $this->session->flashdata('error'),
        ]);
    }

    // 로그인 (POST)
    public function do_login()
    {

        // 입력값 검증 - 공백 제거 등
        $this->form_validation->set_rules(
            'login_id',
            '아이디',
            'trim|required|min_length[2]|max_length[50]|regex_match[/^[A-Za-z0-9._-]+$/]',
            ['required' => '아이디를 입력해주세요.', 'regex_match' => '아이디는 영문/숫자/._-만 가능합니다.']
        );
        $this->form_validation->set_rules(
            'password',
            '비밀번호',
            'trim|required',
            ['required' => '비밀번호를 입력해주세요.']
        );


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
        $this->session->sess_regenerate(TRUE);
        $this->session->set_userdata('user', array(
            'user_id' => (int)$user->user_id,
            'name'    => $user->name,
            'login_id' => $user->login_id,
            'logged_in_at' => date('Y-m-d H:i:s')
        ));

        return redirect('post'); // 로그인 성공 후 이동
    }

    // 로그아웃
    public function logout()
    {
        // 세션 삭제
        $this->session->sess_destroy();
        return redirect('login');
    }

    // 회원가입 폼
    public function register()
    {
        if ($this->session->userdata('user')) {
            return redirect('post'); // 로그인 상태면 게시판으로
        }
        // $this->load->view('auth/register');

        // 빈 레이아웃 사용 신호 + 본문 tpl 지정
        $this->template_->viewDefine('layout_empty', 'common/_empty_marker.tpl');
        $this->template_->viewDefine('layout_common', 'auth/register.tpl');

        // 페이지 리소스(Optimizer 사용)
        $this->optimizer->setCss('auth.css'); // /assets/css/auth.css 기준 (Optimizer 보정 반영)

        // 플래시/검증 에러 전달
        $this->template_->viewAssign([
            'title'         => '회원가입',
            'flash_success' => $this->session->flashdata('success'),
            'flash_error'   => $this->session->flashdata('error'),
            'val_errors'    => validation_errors(), // CI Form Validation의 HTML 문자열
        ]);
    }

    // 회원가입 요청(POST)
    public function do_register()
    {

        // 유효성 검사
        $this->form_validation->set_rules(
            'name',
            '이름',
            'trim|required|min_length[2]|max_length[100]',
            [
                'required'   => '이름을 입력해주세요.',
                'min_length' => '이름은 최소 2자 이상이어야 합니다.',
                'max_length' => '이름은 최대 100자 이하로 입력해주세요.'
            ]
        );

        $this->form_validation->set_rules(
            'login_id',
            '아이디',
            'trim|required|min_length[2]|max_length[50]',
            [
                'required'   => '아이디를 입력해주세요.',
                'min_length' => '아이디는 최소 2자 이상이어야 합니다.',
                'max_length' => '아이디는 최대 50자 이하로 입력해주세요.'
            ]
        );

        $this->form_validation->set_rules(
            'password',
            '비밀번호',
            'trim|required|min_length[8]|max_length[255]',
            [
                'required'   => '비밀번호를 입력해주세요.',
                'min_length' => '비밀번호는 최소 8자 이상이어야 합니다.',
                'max_length' => '비밀번호는 너무 깁니다. (최대 255자)'
            ]
        );

        $this->form_validation->set_rules(
            'password_confirm',
            '비밀번호 확인',
            'trim|required|matches[password]',
            [
                'required' => '비밀번호 확인란을 입력해주세요.',
                'matches'  => '비밀번호와 비밀번호 확인란이 일치하지 않습니다.'
            ]
        );

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
                log_message('error', '회원가입 실패(중복): login_id=' . $login_id);
                return redirect('auth/register');
            }
            // 그 외 오류인 경우
            $this->session->set_flashdata('error', '회원가입 처리 중 오류가 발생했습니다.');
            return redirect('auth/register');
        }

        // 회원가입 성공시 로그 기록
        log_message('info', '회원가입 성공: user_id=' . $result['user_id'] . ' login_id=' . $login_id);

        // 가입 후 자동 로그인
        $this->session->sess_regenerate(TRUE);
        $this->session->set_userdata('user', array(
            'user_id' => (int)$result['user_id'],
            'name'    => $name,
            'login_id' => $login_id,
            'logged_in_at' => date('Y-m-d H:i:s')
        ));

        $this->session->set_flashdata('success', '회원가입이 완료되었습니다. 환영합니다!');
        return redirect('login'); // 로그인 페이지로 이동
    }
}
