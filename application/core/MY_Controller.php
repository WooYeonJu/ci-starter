<?php

class MY_Controller extends CI_Controller
{
    # Parameter reference
    public $params = array();

    public $cookies = array();

    public function __construct()
    {

        parent::__construct();
        # Parameter
        $this->params = $this->getParams();
        $this->cookies = $this->getCookies();

        // 로그인 유저 전역 주입
        $me = $this->session->userdata('user') ?: [];
        $this->load->vars([
            'auth_user' => $me,
            'user_name' => isset($me['user_name']) ? $me['user_name'] : (isset($me['name']) ? $me['name'] : ''),
        ]);
    }

    protected function render($view, $data = [])
    {

        // 로그인/회원가입 컨트롤러는 레이아웃 스킵
        $class = $this->router->fetch_class();
        $skip = in_array($class, ['auth', 'login', 'register']);

        if ($this->use_layout && !$skip) {
            $this->load->view('template/header', $data);
        }

        $this->load->view($view, $data);

        if ($this->use_layout && !$skip) {
            $this->load->view('template/footer', $data);
        }
    }

    private function getParams()
    {

        $aParams = array_merge($this->doGet(), $this->doPost());

        //$this->sql_injection_filter($aParams);

        return $aParams;
    }


    private function getCookies()
    {

        return $this->doCookie();
    }


    private function doGet()
    {
        $aGetData = $this->input->get(NULL, TRUE);
        return (empty($aGetData)) ? array() : $aGetData;
    }

    private function doPost()
    {
        $aPostData = $this->input->post(NULL, TRUE);
        return (empty($aPostData)) ? array() : $aPostData;
    }

    private function doCookie()
    {
        $aCookieData = $this->input->cookie(NULL, TRUE);

        return (empty($aCookieData)) ? array() : $aCookieData;
    }

    public function js($file, $v = '')
    {
        if (is_array($file)) {
            foreach ($file as $iKey => $sValue) {
                $this->optimizer->setJs($sValue, $v);
            }
        } else {
            $this->optimizer->setJs($file, $v);
        }
    }

    public function externaljs($file)
    {
        if (is_array($file)) {
            foreach ($file as $iKey => $sValue) {
                $this->optimizer->setExternalJs($sValue);
            }
        } else {
            $this->optimizer->setExternalJs($file);
        }
    }

    public function css($file, $v = '')
    {
        if (is_array($file)) {
            foreach ($file as $iKey => $sValue) {
                $this->optimizer->setCss($sValue, $v);
            }
        } else {
            $this->optimizer->setCss($file, $v);
        }
    }

    /**
     *  변수 셋팅
     */
    public function setVars($arr = array())
    {
        foreach ($arr as $val) {
            $aVars;
        }

        $this->load->vars($aVars);
    }

    /**
     *  공통 전역 변수 셋팅
     */
    public function setCommonVars()
    {
        $aVars = array();

        $aVars['test'] = array("test1" => "test1");

        $this->load->vars($aVars);
    }

    /**
     * 허가되지 않은 HTTP 메서드로 요청 보낼 때 반영
     */
    protected function deny(array $allowed)
    {
        $this->output
            ->set_status_header(405)
            ->set_header('Allow: ' . implode(', ', $allowed))
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'status' => 'error',
                'error'  => ['code' => 'METHOD_NOT_ALLOWED', 'message' => '허용된 메서드만 사용할 수 있습니다.'],
            ], JSON_UNESCAPED_UNICODE));
        return; // CI 컨벤션상 return으로 종료
    }
}
