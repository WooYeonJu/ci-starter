<?php

/**
 * Template_ 클래스를 확장합니다.
 */
class MY_Template_ extends CI_Template_
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 템플릿 관련 설정(컴파일 경로, 캐시, 권한 등)을 초기화
     * Initialize Preferences
     * @access    public
     */
    public function initialize()
    {
        // 템플릿을 php로 컴파일해 캐시 디렉토리에 저장, 다음 요청 시 빠르게 로드하도록 함
        $this->compile_check = TRUE;                        // 템플릿 수정 시 자동 재컴파일 여부
        $this->compile_dir = APPPATH . "cache/_compile";    // 컴파일된 php 캐시 저장 경로
        $this->compile_ext = 'php';
        $this->skin = '';
        $this->notice = FALSE;
        $this->path_digest = FALSE;

        $this->template_dir = APPPATH . "views";            // 실제 뷰 파일 경로
        $this->prefilter = '';
        $this->postfilter = '';
        $this->permission = 0755;
        $this->safe_mode = FALSE;
        $this->auto_constant = FALSE;

        $this->caching = FALSE;                             // 캐싱 사용 여부
        $this->cache_dir = APPPATH . "cache/_cache";
        $this->cache_expire = 3600;                         // 캐시 만료 시간(초)

        $this->scp_ = '';
        $this->var_ = array('' => array());
        $this->obj_ = array();
    }

    /**
     * 특정 뷰 파일을 식별자(ID)로 등록
     * $this -> viewDefine('main', 'post/list.html) => 이런 식으로 등록해두면 이후 viewPrint('main') 했을 때 해당 파일이 렌더링됨
     */
    public function viewDefine($id, $file)
    {
        $this->define(array($id => $file));
    }

    /**
     * 특정 뷰가 정의되어있는지 여부 확인
     */
    public function viewDefined($id)
    {
        return $this->defined($id);
    }

    /**
     * 템플릿 변수(뷰에서 사용할 데이터) 바인딩
     * $this -> viewAssign('title', '게시글 목록')
     * $this -> viewAssign(['user' => $userData])
     * 
     * => 어떤 페이지든 템플릿 안에서 $CONTROLLERS, $METHOD 등을 바로 사용할 수 있게
     */
    public function viewAssign($key, $value = NULL)
    {
        if (is_array($key) && $value == NULL) {
            $this->assign($key);
        } else {
            $this->assign(array($key => $value));
        }

        $this->defaultAssign();         //기본값 선언
    }

    /**
     * 공통 상수를 자동 바인딩
     */
    public function defaultAssign()
    {
        // 기본값(상수)선언해줌
        $this->assign("CONTROLLERS", _CONTROLLERS);
        $this->assign("METHOD", _METHOD);
        $this->assign("IS_MOBILE", _IS_MOBILE);
    }

    /**
     * 등록된 뷰를 화면에 출력
     * if($this -> viewDefined('main')){ $this -> pring_('main'); }
     * => 화면에 main으로 등록된 파일 바로 출력
     */
    public function viewPrint($id)
    {
        if ($this->viewDefined($id)) {
            $this->print_($id);
        }
    }

    /**
     * 출력하지 않고 html 내용을 변수로 담을 수 있다.
     * = 렌더링 결과를 화면에 출력하지 않고 문자열로 반환
     * (AJAX, 이메일 템플릿, PDF 생성 등에 사용)
     */
    public function viewFetch($fid)
    {
        if ($this->defined($fid)) {
            return $this->fetch($fid);
        }
    }
}
