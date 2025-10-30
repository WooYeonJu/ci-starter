<?php

/**
 * post_controller
 *
 * 컨트롤러가 실행된 후 필요한 처리
 */
class post_controller
{

    private $ci = NULL;

    public function init()
    {
        $this->ci = &get_instance();
        # 최종 화면 출력
        $this->_view();
    }

    /**
     * 모든 컨트롤러에서 viewPrint()를 안 써도 훅으로 감싸서 공통 레이아웃 자동 출력
     */
    private function _view()
    {
        # 기본 레이아웃 (header, footer 없음)
        if ($this->ci->template_->defined('layout_empty')) {
            $this->ci->output->enable_profiler(false);
            $this->ci->template_->viewDefine('layout', 'common/layout_empty.tpl');
            $this->ci->template_->viewAssign($this->ci->optimizer->makeOptimizerScriptTag());
            // $this->ci->template_->viewAssign((array) $this->ci->optimizer->makeOptimizerScriptTag());


            $this->ci->template_->viewPrint('layout');
        }

        # 공통 레이아웃 (header, footer 있음)
        else if ($this->ci->template_->defined('layout_common')) {
            /* layout 파일 정의 */
            $this->ci->template_->viewDefine('layout', 'common/layout_common.tpl');

            /* 공통 모듈 로드 */
            $aCommonModules = $this->getCommonModules();
            $this->ci->load->library('common_modules', $aCommonModules);

            $this->ci->template_->viewDefine('layout_header', 'template/header.tpl');

            $user = $this->ci->session->userdata('user');
            $this->ci->template_->viewAssign([
                'user_name' => is_array($user) ? ($user['name'] ?? null) : null,
            ]);
            // 공통 레이아웃 CSS도 여기서 한 번만 주입 가능
            $this->ci->optimizer->setCss('layout.css');


            /* css, js Assign */
            $this->ci->template_->viewAssign($this->ci->optimizer->makeOptimizerScriptTag());

            /* 출력 */
            $this->ci->template_->viewPrint('layout');
        } else {
            $this->ci->output->enable_profiler(false);
        }
    }

    private function getCommonModules()
    {
        return array();
    }
}
