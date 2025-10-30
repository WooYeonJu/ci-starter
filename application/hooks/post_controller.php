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

    private function _view()
    {
        # 공통 레이아웃 (header, footer 있음)
        if ($this->ci->template_->defined('layout_common')) {
            /* layout 파일 정의 */
            $this->ci->template_->viewDefine('layout', 'common/layout_common.tpl');

            /* 공통 모듈 로드 */
            $aCommonModules = $this->getCommonModules();
            $this->ci->load->library('common_modules', $aCommonModules);

            /* css, js Assign */
            $this->ci->template_->viewAssign($this->ci->optimizer->makeOptimizerScriptTag());

            /* 출력 */
            $this->ci->template_->viewPrint('layout');

            # 기본 레이아웃 (header, footer 없음)
        } else if ($this->ci->template_->defined('layout_empty')) {
            $this->ci->output->enable_profiler(false);
            $this->ci->template_->viewDefine('layout', 'common/layout_empty.tpl');
            $this->ci->template_->viewAssign($this->ci->optimizer->makeOptimizerScriptTag());

            $this->ci->template_->viewPrint('layout');
        } else {
            $this->ci->output->enable_profiler(true);
        }
    }

    private function getCommonModules()
    {
        return array();
    }
}
