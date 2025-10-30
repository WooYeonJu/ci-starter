<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Main extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // 사용자가 로그인되어있는지 여부 확인
    public function index()
    {
        $user = $this->session->userdata('user');
        if ($user) {
            return redirect('post');         // 로그인됨 → 게시판
        }
        return redirect('login');       // 비로그인 → 로그인 페이지
    }
}
