<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Post extends MY_Controller {

    public function __construct() {
        parent::__construct();

        $this->load->view('template/header', ['title' => '게시글 목록']);

        $this->load->model('Post_model', 'posts');      // 게시물 모델 로드
        $this->load->model('File_model', 'files');      // 파일 모델 로드
        $this->load->model('Comment_model', 'comment'); // 코멘트 모델 로드
        $this->load->helper(['form','url','download']); // download() 위해

        // 로그인 세션 확인 - 로그인 안 되어있으면 로그인 페이지로 리다이렉트
        if (!$this->session->userdata('user')) {
            redirect('auth/login');
        }
    }

    public function index()
    {
        $data = [
            'title' => '게시글',
        ];
        $this->render('post/list', $data); // header/footer 자동 포함
    }

    // 게시글 목록 출력
    public function list() {
        $page = (int)$this->input->get('page') ?: 1;

        // n개씩 보기
        $allowed  = [10, 20, 50, 100];
        $per_page = (int)$this->input->get('per_page');
        if (!in_array($per_page, $allowed, true)) $per_page = 10;

        // 카테고리 (전체는 null)
        $category_id = $this->input->get('category_id');
        $category_id = ctype_digit((string)$category_id) ? (int)$category_id : null;

        // 검색어
        $q = trim((string)$this->input->get('q'));

        // 데이터 조회
        $result = $this->posts->list_all($page, $per_page, $category_id, $q);
        $categories = $this->posts->get_categories();

        // 페이징 링크에 붙일 공통 쿼리스트링 (page 제외)
        $qs = http_build_query([
            'per_page'    => $per_page,
            'category_id' => $category_id,
            'q'           => $q,
        ]);

        $data = [
            'title'        => '게시글 목록',
            'rows'         => $result['rows'],
            'total'        => $result['total'],
            'page'         => $page,
            'per_page'     => $per_page,
            'total_pages'  => max(1, (int)ceil($result['total'] / $per_page)),
            'categories'   => $categories,
            'category_id'  => $category_id,
            `q`            => $q,
            'qs'           => $qs, // 페이징 링크에서 사용
        ];
        $this->load->view('post/list', $data);
    }

    // 게시글 상세 조회 + 첨부파일까지 함께 조회
    public function detail($post_id)
    {
        $post_id = (int)$post_id;

        // 게시물 조회
        $row = $this->posts->excute("
            SELECT p.post_id, p.title, p.detail, p.created_at, p.user_id,
                u.name AS author_name, c.category_name
            FROM post p
            JOIN users u ON u.user_id = p.user_id
            JOIN category c ON c.category_id = p.category_id
            WHERE p.post_id = {$post_id} AND p.is_deleted = 0
            LIMIT 1
        ", 'row');

        if (empty($row)) show_404();

        // 파일 조회
        // $files = $this->posts->excute("
        //     SELECT file_id, original_name
        //     FROM file
        //     WHERE post_id = {$post_id}
        //     ORDER BY file_id ASC
        // ", 'rows');
        $files = $this->posts->get_files($post_id);


        // 이 게시물 작성자가 본인인지 확인 후 반환
        $me = $this->session->userdata('user');
        $is_owner = $me && ((int)$me['user_id'] === (int)$row['user_id']);

        // 이 게시물의 댓글 조회
        // 초기 렌더에선 전체를 뿌리거나, 필요시 첫 페이지만:
        $comments = $this->comment->get_by_post_page($post_id, '', 10);
        $comment_cnt = count($this->comment->get_by_post($post_id, '', '500'));

        $this->load->view('post/detail', [
            'title' => $row['title'],
            'post'  => $row,
            'files' => $files,
            'is_owner'  => $is_owner,
            'comments' => $comments,
            'comment_cnt' => $comment_cnt,
        ]);
    }


    // 게시글 작성 페이지 이동
    public function create()
    {
        // 카테고리 목록 조회
        $categories = $this->posts->get_categories();

        $data = [
            'title'      => '게시글 작성',
            'categories' => $categories,
        ];
        $this->load->view('post/create', $data);
    }

    // 게시글 작성(POST)
    public function do_create()
    {
        // 1) 유효성 검사
        $this->form_validation->set_rules('category_id', '카테고리', 'required|integer');
        $this->form_validation->set_rules('title', '제목', 'trim|required|min_length[1]|max_length[200]');
        $this->form_validation->set_rules('detail', '내용', 'trim|required');

        if (!$this->form_validation->run()) {
            $categories = $this->posts->get_categories();
            return $this->load->view('post/create', [
                'title'      => '게시글 작성',
                'categories' => $categories,
            ]);
        }

        // 2) 입력값
        $category_id = (int)$this->input->post('category_id');
        $title       = (string)$this->input->post('title',  TRUE);
        $detail      = (string)$this->input->post('detail', TRUE);

        // 3) 작성자
        $user    = $this->session->userdata('user');
        $user_id = (int)$user['user_id'];

        // ----------------------------
        // A단계) 업로드: 스테이징(uploads_tmp/)으로만 저장
        // ----------------------------
        $staging_dir = FCPATH.'uploads_tmp/';
        $final_dir   = FCPATH.'uploads/';

        if (!is_dir($staging_dir)) @mkdir($staging_dir, 0777, true);
        if (!is_dir($final_dir))   @mkdir($final_dir,   0777, true);

        $staged_fullpaths = []; // 임시폴더에 저장된 실제 경로들
        $file_metas       = []; // 업로드 후 CI가 돌려준 메타 (DB 저장에 사용)

        try {
            if (!empty($_FILES['files']['name'][0])) {
                $files = $_FILES['files'];
                $count = count($files['name']);

                for ($i=0; $i<$count; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('파일 업로드 오류 코드: '.$files['error'][$i]);
                    }

                    // CI 업로드 포맷으로 단일 파일 셋업
                    $_FILES['__one'] = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];

                    $config = [
                        'upload_path'   => $staging_dir,       // ★ 임시 폴더(스테이징)
                        'allowed_types' => 'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx',
                        'max_size'      => 0,                  // 서버 제한까지
                        'encrypt_name'  => TRUE,               // 저장 파일명 랜덤
                        'detect_mime'   => TRUE,               // CI 3.1.5+ MIME 검증
                    ];

                    // 이전 설정 잔존 방지
                    $this->load->library('upload');
                    $this->upload->initialize($config, TRUE);

                    if (!$this->upload->do_upload('__one')) {
                        throw new RuntimeException('파일 업로드 실패: '.$this->upload->display_errors('', ''));
                    }

                    $up = $this->upload->data(); // 업로드 결과 메타
                    if (empty($up['full_path']) || empty($up['file_name'])) {
                        throw new RuntimeException('업로드 메타 파싱 실패');
                    }

                    $staged_fullpaths[] = $up['full_path']; // /.../uploads_tmp/abcd1234.png
                    $file_metas[]       = $up;              // 나중에 DB 저장/이동에 활용
                }
            }
            // 여기까지 왔으면 "모든 파일이 임시폴더로 정상 업로드 완료" 상태
        } catch (Throwable $e) {
            // 임시폴더에 쌓인 파일들 정리
            foreach ($staged_fullpaths as $p) { if (is_file($p)) @unlink($p); }
            log_message('error', '파일 스테이징 실패: '.$e->getMessage());
            $this->session->set_flashdata('error', '파일 업로드에 실패했습니다.');
            return redirect('post/create');
        }

        // ----------------------------
        // B단계) DB 저장: 게시글 → 파일 메타 (경로는 최종 경로 기준으로 저장)
        // ----------------------------
        // 주의: 현재 구조(excute 별도 커넥션)에서는 컨트롤러 트랜잭션이 안 먹으므로,
        //       DB 오류 시에도 최종 폴더는 아직 건드리지 않았기 때문에
        //       스테이징 파일만 삭제하면 정합성 유지됩니다.
        $post_id = 0;
        try {
            // 1) 게시글 저장
            $post_id = $this->posts->create_post($user_id, $category_id, $title, $detail);
            if (!$post_id) {
                throw new RuntimeException('게시글 등록 실패');
            }

            // 2) 파일 메타 저장(최종 경로 기준으로 DB에 기록)
            //    실제 파일 이동은 C단계에서 수행
            foreach ($file_metas as $m) {
                $ok = $this->files->insert_file($post_id, [
                    'original_name' => $m['client_name'],
                    'stored_name'   => $m['file_name'],             // 랜덤 저장명
                    'path'          => 'uploads/'.$m['file_name'],  // ★ 최종 경로 기준으로 저장
                    'mime_type'     => $m['file_type'],
                    'size_bytes'    => (int) round($m['file_size'] * 1024),
                ]);
                if (!$ok) {
                    throw new RuntimeException('파일 메타데이터 저장 실패');
                }
            }
        } catch (Throwable $e) {
            // DB가 실패했으나 /uploads 는 아직 건드린 적 없음 → 임시만 정리하면 끝
            foreach ($staged_fullpaths as $p) { if (is_file($p)) @unlink($p); }
            log_message('error', '게시글/파일메타 저장 실패: '.$e->getMessage());
            $this->session->set_flashdata('error', '게시글 등록에 실패했습니다.');
            return redirect('post/create');
        }

        // ----------------------------
        // C단계) 파일 승격: 임시폴더 → 최종폴더 로 이동(rename)
        // ----------------------------
        try {
            foreach ($file_metas as $m) {
                $from = $staging_dir.$m['file_name']; // 기존 경로(임시)
                $to   = $final_dir.$m['file_name'];   // 최종 경로

                // 동일 파일시스템이면 rename이 거의 원자적/고속
                if (!@rename($from, $to)) {
                    // 다른 파티션 등으로 인해 실패하면 copy+unlink로 폴백
                    if (!@copy($from, $to)) {
                        throw new RuntimeException('파일 이동 실패: '.$m['file_name']);
                    }
                    @unlink($from);
                }
            }
        } catch (Throwable $e) {
            // 이동 실패 시: 이미 DB는 들어간 상태 → 운영 정책에 따라 보정 필요
            // 1) 간단 보정: DB에서 방금 등록한 파일메타/게시글 삭제 + 임시파일 정리
            //    (필요 시 posts/files 모델에 삭제 메서드가 있다면 그것 사용)
            foreach ($staged_fullpaths as $p) { if (is_file($p)) @unlink($p); }

            // 예시) 최소한 안내 메시지와 로그 처리
            log_message('error', '파일 승격(임시→최종) 실패: '.$e->getMessage().' (post_id='.$post_id.')');

            // 선택 1) 사용자에게 실패 알리고 글 작성 취소 처리(권장: 정책 합의 필요)
            // $this->posts->delete_post_cascade($post_id); // 구현되어 있으면 사용
            // $this->session->set_flashdata('error', '파일 이동 중 오류가 발생하여 등록이 취소되었습니다.');
            // return redirect('post/create');

            // 선택 2) 글은 남기고, 실패한 파일만 제외/재시도 유도(UX 선택)
            $this->session->set_flashdata('error', '일부 파일 이동에 실패했습니다. 다시 시도해 주세요.');
            return redirect('post/detail/'.$post_id);
        }

        // 성공
        $this->session->set_flashdata('success', '게시글이 등록되었습니다.');
        return redirect('post/detail/'.$post_id);
    }



    // 파일 다운로드 함수
    public function download($file_id)
    {
        $file_id = (int)$file_id;

        // 파일 메타 조회
        $row = $this->posts->excute("
            SELECT file_id, original_name, stored_name, path, mime_type, size_bytes
            FROM file
            WHERE file_id = {$file_id}
            LIMIT 1
        ", 'row');

        if (empty($row)) {
            log_message('error', 'download: file row missing. id='.$file_id);
            show_404();
        }

        // 절대경로 생성 (경로 결합 안정화)
        $abs = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($row['path'], DIRECTORY_SEPARATOR);

        if (!is_file($abs)) {
            log_message('error', 'download: file not found at '.$abs);
            show_404();
        }

        // 원본 파일명으로 다운로드
        $data = file_get_contents($abs);
        force_download($row['original_name'], $data); // download 헬퍼 (로딩됨)
        // 이후 종료
    }


    // 수정 폼
    public function edit($post_id)
    {
        $post_id = (int)$post_id;
        $post = $this->posts->get_post($post_id);
        if (!$post) show_404();

        // URL 직접 접근 차단(작성자만)
        $me = $this->session->userdata('user');
        if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

        $categories = $this->posts->get_categories();
        $files = $this->posts->get_files($post_id);

        $this->load->view('post/edit', [
            'title'      => '게시글 수정',
            'post'       => $post,
            'categories' => $categories,
            'files'      => $files,
        ]);
    }

    // 수정 처리
    public function do_edit($post_id)
    {
        $post_id = (int)$post_id;
        $post = $this->posts->get_post($post_id);
        if (!$post) show_404();

        // 작성자만
        $me = $this->session->userdata('user');
        if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

        // 검증
        $this->form_validation->set_rules('category_id', '카테고리', 'required|integer');
        $this->form_validation->set_rules('title', '제목', 'trim|required|max_length[200]');
        $this->form_validation->set_rules('detail', '내용', 'trim|required');
        if (!$this->form_validation->run()) {
            $categories = $this->posts->get_categories();
            $files = $this->posts->get_files($post_id);
            return $this->load->view('post/edit', [
                'title'=>'게시글 수정','post'=>$post,'categories'=>$categories,'files'=>$files
            ]);
        }

        // 업데이트
        $this->posts->update_post(
            $post_id,
            (string)$this->input->post('title', TRUE),
            (string)$this->input->post('detail', TRUE),
            (int)$this->input->post('category_id')
        );

        // (1) 기존 파일 삭제(체크된 것)
        $remove_ids = (array)$this->input->post('remove_files'); // [] or ['3','5']
        foreach ($remove_ids as $fid) {
            $meta = $this->posts->get_file((int)$fid);
            if ($meta && (int)$meta['post_id'] === $post_id) {
                $abs = rtrim(FCPATH, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($meta['path'], DIRECTORY_SEPARATOR);
                if (is_file($abs)) @unlink($abs);
                $this->posts->delete_file_row((int)$fid);
            }
        }

        // (2) 새 파일 추가(여러 개)
        if (!empty($_FILES['files']['name'][0])) {
            $upload_path = FCPATH.'uploads/';
            if (!is_dir($upload_path)) @mkdir($upload_path, 0777, true);
            $files = $_FILES['files'];
            for ($i=0; $i<count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $_FILES['__one'] = [
                    'name'=>$files['name'][$i],'type'=>$files['type'][$i],
                    'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i],
                ];
                $config = [
                    'upload_path'=>$upload_path,'allowed_types'=>'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx','max_size'=>0,'encrypt_name'=>TRUE
                ];
                $this->load->library('upload', $config, 'upl');
                
                // 파일 업로드 도중 하나라도 실패하면 전부 롤백
                if (!$this->upl->do_upload('__one')) {
                    throw new RuntimeException('파일 업로드 실패: '.$this->upl->display_errors('', ''));
                }

                $up = $this->upl->data();
                $this->files->insert_file($post_id, [
                    'original_name'=>$up['client_name'],
                    'stored_name'  =>$up['file_name'],
                    'path'         =>'uploads/'.$up['file_name'],
                    'mime_type'    =>$up['file_type'],
                    'size_bytes'   =>(int)$up['file_size']*1024
                ]);
            }
        }

        $this->session->set_flashdata('success','게시글이 수정되었습니다.');
        return redirect('post/detail/'.$post_id);
    }

    // 삭제(소프트 delete)
    public function delete($post_id)
    {
        $post_id = (int)$post_id;
        $post = $this->posts->get_post($post_id);
        if (!$post) show_404();

        // 작성자만
        $me = $this->session->userdata('user');
        if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

        $this->posts->soft_delete_post($post_id);
        $this->session->set_flashdata('success','게시글이 삭제되었습니다.');
        return redirect('post'); // 목록으로
    }


}
