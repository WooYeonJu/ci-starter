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

    /**
     * [신규] define() 없이 파일을 바로 렌더하여 문자열로 반환
     * - $file: 'comment/_items' 또는 'comment/_items.tpl'처럼 경로/파일
     * - $data: 템플릿에 바인딩할 배열
     * - $autoExt: 확장자가 없으면 '.tpl'을 자동으로 붙일지 여부
     *
     * 사용 예:
     *   $html = $this->template_->viewFetchDirect('comment/_items', ['comments' => [$row]]);
     */
    public function viewFetchDirect(string $file, array $data = [], bool $autoExt = true): string
    {

        $dataPreview = print_r($data, true); // 배열을 문자열로 보기 좋게 변환
        log_message('debug', "[Template_] viewFetchDirect called: file={$file}, data=" . $dataPreview);

        // 확장자 자동 보정 (없을 때만)
        if ($autoExt && !preg_match('/\.\w+$/', $file)) {
            $file .= '.tpl';
        }

        // 임시 아이디 (define용)
        $tmpId = '__tmp_' . uniqid('tpl_', true);

        // 현재 바인딩된 변수 스냅샷
        $prevVar = $this->var_;

        try {
            // 데이터 바인딩
            if (!empty($data)) {
                $this->assign($data);
            }
            // 공통 상수/기본값 보장
            $this->defaultAssign();

            // 파일을 임시 ID로 정의 후 fetch
            $this->define([$tmpId => $file]);
            $html = $this->fetch($tmpId);

            // 문자열 보장
            return (string) $html;
        } catch (Throwable $e) {
            // 필요 시 로깅
            if (function_exists('log_message')) {
                log_message('error', 'viewFetchDirect error: ' . $e->getMessage());
            }
            // 예외를 다시 던져도 되고, 빈 문자열 반환도 가능. 여기선 빈 문자열 반환.
            return '';
        } finally {
            // 이전 바인딩 복구 (임시 assign에 의한 오염 방지)
            $this->var_ = $prevVar;
            // define 해제는 CI_Template_ 내부 상태에 접근해야 하므로 생략
            // (임시 define이 남아 있어도 ID 충돌 방지를 위해 uniqid 사용)
        }
    }

    /**
     * [신규] define() 없이 파일을 바로 렌더해서 바로 출력 (echo)
     *  - 문자열이 필요 없고 즉시 출력하고 싶을 때 사용
     */
    public function viewPrintDirect(string $file, array $data = [], bool $autoExt = true): void
    {
        $html = $this->viewFetchDirect($file, $data, $autoExt);
        if ($html !== '') {
            echo $html;
        }
    }

    /**
     * [신규] JSON 응답용 헬퍼: 특정 템플릿을 즉시 렌더해 'html' 필드로 담아 반환
     *  컨트롤러에서 한 줄로 편하게 쓰도록 제공 (선택사항)
     *
     * 사용 예:
     *   return $this->template_->jsonWithHtml('comment/_items', ['comments' => [$row]], [
     *     'status' => 'success',
     *     'comment_id' => $new_id,
     *     'message' => '댓글이 등록되었습니다.'
     *   ]);
     */
    public function jsonWithHtml(string $file, array $data = [], array $payload = [], bool $autoExt = true)
    {
        $html = $this->viewFetchDirect($file, $data, $autoExt);

        $resp = array_merge($payload, ['html' => $html]);

        // CI 출력기 사용 (컨트롤러 컨텍스트 가정)
        if (function_exists('get_instance')) {
            $ci = &get_instance();
            return $ci->output
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($resp, JSON_UNESCAPED_UNICODE));
        }

        // 혹시 get_instance를 못 쓸 환경이면 그냥 JSON 문자열 반환
        return json_encode($resp, JSON_UNESCAPED_UNICODE);
    }
}
