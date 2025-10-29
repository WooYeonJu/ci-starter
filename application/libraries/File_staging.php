<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * File_staging
 * - 업로드 입력을 uploads_tmp(스테이징)로 먼저 저장
 * - DB 메타는 최종경로(uploads/...) 기준으로 기록
 * - 이후 스테이징 → 최종 폴더로 승격(promote)
 * - 실패 시 스테이징 파일 정리 및 예외 던짐
 */
class File_staging
{
    /** @var CI_Controller */
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * 1) 입력에서 스테이징으로 업로드
     * @param string $input_name ex) 'files'
     * @param string $staging_dir ex) FCPATH.'uploads_tmp/'
     * @param string $allowed_types ex) 'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx'
     * @param int    $max_size 0 = 서버 제한
     * @return array ['metas' => [CI upload data...], 'staged_fullpaths' => [abs paths]]
     * @throws RuntimeException
     */
    public function stage_from_input($input_name, $staging_dir, $allowed_types, $max_size = 0)
    {
        if (!is_dir($staging_dir)) @mkdir($staging_dir, 0777, true);

        $metas = [];
        $staged_fullpaths = [];

        if (empty($_FILES[$input_name]['name'][0])) {
            return ['metas' => [], 'staged_fullpaths' => []]; // 업로드 없음
        }

        $files = $_FILES[$input_name];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $this->cleanup($staged_fullpaths);
                throw new RuntimeException('파일 업로드 오류 코드: ' . $files['error'][$i]);
            }

            $_FILES['__one'] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];

            $config = [
                'upload_path'   => $staging_dir,
                'allowed_types' => $allowed_types,
                'max_size'      => $max_size,
                'encrypt_name'  => TRUE,
                'detect_mime'   => TRUE,
            ];

            // 업로드 라이브러리 매 파일마다 초기화
            $this->CI->load->library('upload');
            $this->CI->upload->initialize($config, TRUE);

            if (!$this->CI->upload->do_upload('__one')) {
                $this->cleanup($staged_fullpaths);
                throw new RuntimeException('파일 업로드 실패: ' . $this->CI->upload->display_errors('', ''));
            }

            $up = $this->CI->upload->data();
            if (empty($up['full_path']) || empty($up['file_name'])) {
                $this->cleanup($staged_fullpaths);
                throw new RuntimeException('업로드 메타 파싱 실패');
            }

            $staged_fullpaths[] = $up['full_path'];
            $metas[] = $up;
        }

        return ['metas' => $metas, 'staged_fullpaths' => $staged_fullpaths];
    }

    /**
     * 2) 스테이징 → 최종 폴더로 승격
     * @param array  $metas stage_from_input에서 받은 metas (각 원소에 file_name 포함)
     * @param string $staging_dir ex) FCPATH.'uploads_tmp/'
     * @param string $final_dir   ex) FCPATH.'uploads/'
     * @throws RuntimeException
     */
    public function promote(array $metas, $staging_dir, $final_dir)
    {
        if (!is_dir($final_dir)) @mkdir($final_dir, 0777, true);
        foreach ($metas as $m) {
            $from = rtrim($staging_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $m['file_name'];
            $to   = rtrim($final_dir,   DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $m['file_name'];

            if (!@rename($from, $to)) {
                if (!@copy($from, $to)) {
                    // 승격 실패 → 이미 DB는 기록되었을 수 있음 (호출부에서 롤백 처리)
                    throw new RuntimeException('파일 승격 실패: ' . $m['file_name']);
                }
                @unlink($from);
            }
        }
    }

    /**
     * 스테이징 파일 정리
     * @param array $staged_fullpaths 절대경로 배열
     */
    public function cleanup(array $staged_fullpaths)
    {
        foreach ($staged_fullpaths as $p) {
            if (is_file($p)) @unlink($p);
        }
    }
}
