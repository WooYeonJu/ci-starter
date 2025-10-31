<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Post extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();

        // $this->load->view('template/header', ['title' => '게시글 목록']);

        $this->load->model('Post_model', 'posts');      // 게시물 모델 로드
        $this->load->model('File_model', 'files');      // 파일 모델 로드
        $this->load->model('Comment_model', 'comment'); // 코멘트 모델 로드
        $this->load->helper(['form', 'url', 'download']); // download() 위해

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
    public function list()
    {
        // 몇 번째 페이지 보여줄지 -> 없으면 기본값 1
        $page = (int)$this->input->get('page') ?: 1;

        // n개씩 보기(10개, 20개, 50개, 100개)
        $allowed  = [10, 20, 50, 100];
        // 한 페이지 당 몇 개를 보여줄지
        $per_page = (int)$this->input->get('per_page');
        // 기본값 10
        if (!in_array($per_page, $allowed, true)) $per_page = 10;

        // 카테고리 (전체는 null)
        $category_id = $this->input->get('category_id');
        $category_id = ctype_digit((string)$category_id) ? (int)$category_id : null;

        // 검색어
        $q = trim((string)$this->input->get('q'));

        // 데이터 조회
        $result = $this->posts->list_all($page, $per_page, $category_id, $q);
        $categories = $this->posts->get_categories();

        // 페이징 url에 붙일 공통 쿼리스트링
        // 이게 없으면 다음 페이지 넘어갔을 때 카테고리, 검색어 등이 초기회됨
        $qs = http_build_query([
            'per_page'    => $per_page,
            'category_id' => $category_id,
            'q'           => $q,
        ]);


        // 페이지 계산은 기존 로직 유지
        $total_pages = max(1, (int)ceil($result['total'] / $per_page));
        $pages = [1, max(1, $page - 1), $page, min($total_pages, $page + 1), $total_pages];
        $pages = array_values(array_unique(array_filter($pages, function ($p) use ($total_pages) {
            return $p >= 1 && $p <= $total_pages;
        })));
        sort($pages);

        $pages_view = [];
        $prev = null;
        $push_sep = function () use (&$pages_view) {
            $pages_view[] = ['type' => 'sep'];
        };
        foreach ($pages as $p) {
            if ($prev !== null) {
                if ($p - $prev > 1) {
                    $push_sep();                       // |
                    $pages_view[] = ['type' => 'ellipsis']; // …
                    $push_sep();                       // |
                } else {
                    $push_sep();                       // |
                }
            }

            $pages_view[] = [
                'type'    => 'num',
                'n'       => $p,
                'current' => ($p == $page),
            ];
            $prev = $p;
        }

        // 카테고리도 selected 플래그 미리 계산 (경계값 대비)
        $category_id_val = ($category_id === null) ? '' : (string)$category_id;
        $categories_view = array_map(function ($c) use ($category_id_val) {
            $cid = (string)$c['category_id'];
            return [
                'category_id'   => $cid,
                'category_name' => $c['category_name'],
                'selected'      => ($category_id_val !== '' && $category_id_val === $cid),
            ];
        }, $categories);

        // 템플릿 전달
        $data = [
            'title'        => '게시글 목록',
            'rows'         => $result['rows'],
            'total'        => $result['total'],
            'page'         => $page,
            'per_page'     => $per_page,
            'total_pages'  => $total_pages,
            'categories'   => $categories_view,   // ← 가공본
            'category_id'  => $category_id,
            'q'            => $q,
            'qs'           => $qs,
            'pages'        => $pages_view,        // ← 가공본
        ];

        $this->template_->viewDefine('layout_common', 'post/list.tpl');
        $this->optimizer->setCss('post.css');
        $this->template_->viewAssign($data);


        // $total_pages = max(1, (int)ceil($result['total'] / $per_page));

        // $pages = [1, max(1, $page - 1), $page, min($total_pages, $page + 1), $total_pages];
        // $pages = array_values(array_unique(array_filter($pages, function ($p) use ($total_pages) {
        //     return $p >= 1 && $p <= $total_pages;
        // })));
        // sort($pages);

        // // 조회된 데이터 기반 뷰로 전송해줄 데이터 생성
        // $data = [
        //     'title'        => '게시글 목록',
        //     'rows'         => $result['rows'],
        //     'total'        => $result['total'],
        //     'page'         => $page,
        //     'per_page'     => $per_page,
        //     'total_pages'  => max(1, (int)ceil($result['total'] / $per_page)),
        //     'categories'   => $categories,
        //     'category_id'  => $category_id,
        //     'q'            => $q,
        //     'qs'           => $qs, // 페이징 링크에서 사용
        //     'pages'        => $pages,
        // ];

        // $this->template_->viewDefine('layout_common', 'post/list.tpl');

        // // Optimizer로 CSS 주입
        // $this->optimizer->setCss('post.css');
        // $this->template_->viewAssign($data);


        // 뷰 로드
        // $this->load->view('post/list', $data);
    }

    // 게시글 상세 조회 + 첨부파일까지 함께 조회
    public function detail($post_id)
    {
        $post_id = (int)$post_id;

        // 게시물 조회(is_deleted가 false인 것만)
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

        // 이 게시물의 파일 조회
        $files = $this->posts->get_files($post_id);
        $files = array_map(function ($f) {
            return [
                'file_id'       => (int)$f['file_id'],
                'original_name' => (string)$f['original_name'],
                'size_bytes'    => isset($f['size_bytes']) ? (int)$f['size_bytes'] : null,
                // ✅ 템플릿에서 결합하지 않도록 미리 완성
                'url_download'  => site_url('post/download/' . (int)$f['file_id']),
            ];
        }, $files ?? []);

        // 이 게시물 작성자가 본인인지 확인
        $me = $this->session->userdata('user');
        $is_owner = $me && ((int)$me['user_id'] === (int)$row['user_id']);

        // 이 게시물의 댓글 조회
        // 초기 렌더에서는 최대 200개만 -> 무한 스크롤로 구현
        $comments = $this->comment->get_by_post_page($post_id, '', 200);
        // 전체 댓글 개수
        $comment_cnt = (int)$this->comment->count_by_post($post_id);

        // 본문 템플릿
        $this->template_->viewDefine('layout_common', 'post/detail.tpl');

        // 댓글 파셜들 정의
        $this->template_->viewDefine('comments',      'comment/list.tpl');
        $this->template_->viewDefine('comment_items', 'comment/_items.tpl');

        // CSS (Optimizer 사용)
        $this->optimizer->setCss('post-detail.css');
        $this->optimizer->setCss('comments.css');

        // JS
        $this->optimizer->setJs('comments.js');

        $initial_items_count = is_array($comments) ? count($comments) : 0;


        $this->template_->viewAssign([
            'title'          => '게시글 상세',
            'post'           => $row,
            'is_owner'       => (bool)$is_owner,
            'files'          => $files,

            // 댓글 데이터
            'post_id'        => $post_id,
            'comments'       => $comments,
            'comment_cnt'    => (int)$comment_cnt,

            // JS 용 안전 값
            'post_id_js'         => $post_id,                 // 정수 보장
            'has_more_js'        => $comment_cnt > $initial_items_count, // 더 불러올 게 있는지
            'initial_count_js'   => $initial_items_count,     // 초기 렌더 개수
            'url_edit'   => site_url('post/edit/' . $post_id),
            'url_delete' => site_url('post/delete/' . $post_id),
        ]);

        // // 뷰 로드
        // $this->load->view('post/detail', [
        //     'title' => $row['title'],
        //     'post'  => $row,
        //     'files' => $files,
        //     'is_owner'  => $is_owner,
        //     'comments' => $comments,
        //     'comment_cnt' => $comment_cnt,
        // ]);
    }


    // 게시글 작성 페이지 이동
    public function create()
    {
        // 카테고리 목록 조회
        $categories = $this->posts->get_categories();


        // 본문 템플릿 지정
        $this->template_->viewDefine('layout_common', 'post/create.tpl');

        // Optimizer로 페이지 CSS
        $this->optimizer->setCss('post-form.css');

        // 플래시 & 검증 에러
        $flash_success = $this->session->flashdata('success');
        $flash_error   = $this->session->flashdata('error');
        $validation_errors_html = validation_errors(); // form_validation 로드 전제

        // 바인딩
        $this->template_->viewAssign([
            'title'                   => '게시글 작성',
            'categories'              => $categories,
            'flash_success'           => $flash_success,
            'flash_error'             => $flash_error,
            'validation_errors_html'  => $validation_errors_html,
        ]);


        // $data = [
        //     'title'      => '게시글 작성',
        //     'categories' => $categories,
        // ];


        // 뷰 로드
        // $this->load->view('post/create', $data);
    }

    // 게시글 작성 함수
    public function do_create()
    {
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

        $category_id = (int)$this->input->post('category_id');
        $title       = (string)$this->input->post('title',  TRUE);
        $detail      = (string)$this->input->post('detail', TRUE);

        $user    = $this->session->userdata('user');
        $user_id = (int)$user['user_id'];

        $staging_dir = FCPATH . 'uploads_tmp/';
        $final_dir   = FCPATH . 'uploads/';
        $allowed     = 'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx';

        $this->load->library('File_staging', null, 'fs');

        // 1) 스테이징
        try {
            $stage = $this->fs->stage_from_input('files', $staging_dir, $allowed, 0);
            $file_metas = $stage['metas'];
            $staged_fullpaths = $stage['staged_fullpaths'];
        } catch (Throwable $e) {
            log_message('error', '파일 스테이징 실패(생성): ' . $e->getMessage());
            $this->session->set_flashdata('error', '파일 업로드에 실패했습니다.');
            return redirect('post/create');
        }

        // 2) DB + 파일 승격
        $this->db->trans_begin();

        try {
            $post_id = $this->posts->create_post($user_id, $category_id, $title, $detail);
            if (!$post_id) throw new RuntimeException('게시글 등록 실패');

            foreach ($file_metas as $m) {
                $ok = $this->files->insert_file($post_id, [
                    'original_name' => $m['client_name'],
                    'stored_name'   => $m['file_name'],
                    'path'          => 'uploads/' . $m['file_name'],
                    'mime_type'     => $m['file_type'],
                    'size_bytes'    => (int) round($m['file_size'] * 1024),
                ]);
                if (!$ok) throw new RuntimeException('파일 메타데이터 저장 실패');
            }

            // 파일 승격
            $this->fs->promote($file_metas, $staging_dir, $final_dir);

            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            // 스테이징만 정리 (최종은 아직 없음)
            $this->fs->cleanup($staged_fullpaths);

            log_message('error', '게시글/파일 저장 실패(생성): ' . $e->getMessage());
            $this->session->set_flashdata('error', '게시글 등록에 실패했습니다.');
            return redirect('post/create');
        }

        $this->session->set_flashdata('success', '게시글이 등록되었습니다.');
        return redirect('post/detail/' . $post_id);
    }



    // // 게시글 작성(POST)
    // public function do_create()
    // {
    //     // 입력값 유효성 검사
    //     $this->form_validation->set_rules('category_id', '카테고리', 'required|integer');
    //     $this->form_validation->set_rules('title', '제목', 'trim|required|min_length[1]|max_length[200]');
    //     $this->form_validation->set_rules('detail', '내용', 'trim|required');

    //     // 유효성 검사 실패시 입력 페이지 재로드
    //     if (!$this->form_validation->run()) {
    //         $categories = $this->posts->get_categories();
    //         return $this->load->view('post/create', [
    //             'title'      => '게시글 작성',
    //             'categories' => $categories,
    //         ]);
    //     }

    //     // 입력값 받아오기
    //     $category_id = (int)$this->input->post('category_id');
    //     $title       = (string)$this->input->post('title',  TRUE);
    //     $detail      = (string)$this->input->post('detail', TRUE);

    //     // 작성자 정보 조회
    //     $user    = $this->session->userdata('user');
    //     $user_id = (int)$user['user_id'];

    //     /**
    //      * 파일 업로드 - 스테이징 기법
    //      * - 임시폴더(uploads_tmp)에 파일 저장 후 DB에 uploads/경로로 저장
    //      * - DB insert 완료 후 임시 폴더에 있던 파일을 uploads 폴더로 이동
    //      */

    //     // ----------------------------
    //     // 1단계) 임시 폴더(uploads_tmp)에 파일 업로드
    //     // ----------------------------
    //     // 임시 폴더 경로 설정
    //     $staging_dir = FCPATH . 'uploads_tmp/';
    //     // 최종 저장 폴더 경로 설정
    //     $final_dir   = FCPATH . 'uploads/';

    //     // 임시 폴더가 없거나 최종 저장 폴더가 없으면 폴더 생성
    //     if (!is_dir($staging_dir)) @mkdir($staging_dir, 0777, true);
    //     if (!is_dir($final_dir))   @mkdir($final_dir,   0777, true);

    //     $staged_fullpaths = []; // 임시폴더에 저장된 경로들 저장할 배열
    //     $file_metas       = []; // 업로드 후 CI가 돌려준 메타 (DB 저장에 사용)

    //     // 임시 폴더에 파일 업로드 로직 시도
    //     try {
    //         // _FILES: HTML <input type="file">로 업로드된 파일 정보를 담는 php 전역배열(폼 전송 시 PHP가 자동으로 생성해줌)
    //         // = 파일명, 파일 타입 등 입력한 폼의 name 속성값을 배열 형태로 저장
    //         // 예를 들어 photo.jpg, resume.pdf 두 파일을 업로드한 경우, 아래와 같은 배열 생성
    //         // $_FILES = [ 'files' => [ 'name' => ['photo.jpg', 'resume.pdf'], 'type' => ['image/pdf', 'application/pdf'], ... ] ]
    //         // !empty($_FILES['files']['name'][0]) = 첫 번째 파일이 존재하는지? = 파일이 하나라도 업로드 되었는지 확인
    //         if (!empty($_FILES['files']['name'][0])) {
    //             // $_FILES 안에 files 배열 받아오기
    //             $files = $_FILES['files'];
    //             // 파일 총 개수 확인
    //             $count = count($files['name']);

    //             // 파일 개수만큼 순회
    //             for ($i = 0; $i < $count; $i++) {
    //                 // 파일 업로드 중 오류 발생 여부 확인(파일이 제대로 서버에 도착했는지)
    //                 if ($files['error'][$i] !== UPLOAD_ERR_OK) {
    //                     throw new RuntimeException('파일 업로드 오류 코드: ' . $files['error'][$i]);
    //                 }

    //                 // 한 번에 한 파일씩 처리하기 위해 배열에서 하나의 파일 정보 뽑아오기
    //                 $_FILES['__one'] = [
    //                     'name'     => $files['name'][$i],
    //                     'type'     => $files['type'][$i],
    //                     'tmp_name' => $files['tmp_name'][$i],
    //                     'error'    => $files['error'][$i],
    //                     'size'     => $files['size'][$i],
    //                 ];

    //                 // 파일 업로드 관련 설정
    //                 $config = [
    //                     'upload_path'   => $staging_dir,       // 임시 폴더에 저장
    //                     'allowed_types' => 'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx',   // 저장 가능한 파일 타입
    //                     'max_size'      => 0,                  // 서버 제한까지
    //                     'encrypt_name'  => TRUE,               // 저장 파일명 랜덤
    //                     'detect_mime'   => TRUE,               // CI 3.1.5+ MIME 검증
    //                 ];

    //                 // 파일 업로드 라이브러리 호출 - 매 파일마다 새로 초기화
    //                 $this->load->library('upload');
    //                 // 이전 파일 설정이 다음 파일에 영향을 주지 않게 하기 위해 초기화(이전 파일의 확장자명, 경로, 파일명 등)
    //                 $this->upload->initialize($config, TRUE);

    //                 // CI Upload 라이브러리에서 발생한 오류 확인(확장자, MIME, 권한 등이 설정에 따라 거부된 경우)
    //                 if (!$this->upload->do_upload('__one')) {
    //                     throw new RuntimeException('파일 업로드 실패: ' . $this->upload->display_errors('', ''));
    //                 }

    //                 // 파일 업로드 끝난 후 CI가 반환한 업로드 결과 데이터 검증
    //                 // 파일이 임시 폴더에 정상적으로 업로드 되었는지, 결과 정보가 제대로 들어왔는지 확인
    //                 $up = $this->upload->data();
    //                 // 파일 경로 혹은 파일 이름 중 하나라도 비어있을 경우 정상적으로 업로드 된 파일이 아니므로 예외 처리
    //                 if (empty($up['full_path']) || empty($up['file_name'])) {
    //                     throw new RuntimeException('업로드 메타 파싱 실패');
    //                 }

    //                 // 파일이 저장된 실제 경로(임시 폴더)
    //                 $staged_fullpaths[] = $up['full_path']; // /.../uploads_tmp/abcd1234.png
    //                 $file_metas[]       = $up;              // 나중에 DB에 저장할 때 쓸 메타 정보
    //             }
    //         }
    //         // 여기까지 왔으면 "모든 파일이 임시폴더로 정상 업로드 완료" 상태
    //     } catch (Throwable $e) {
    //         // 임시 폴더에 파일 저장 로직 중 실패 발생 시 임시 폴더에 있던 파일 삭제 후 에러 반환
    //         foreach ($staged_fullpaths as $p) {
    //             if (is_file($p)) @unlink($p);
    //         }
    //         log_message('error', '파일 스테이징 실패: ' . $e->getMessage());
    //         $this->session->set_flashdata('error', '파일 업로드에 실패했습니다.');
    //         return redirect('post/create');
    //     }

    //     // ----------------------------
    //     // 2단계) DB에 파일 정보 저장(경로는 실제 저장 폴더인 uploads/.. 으로)
    //     // ----------------------------
    //     $post_id = 0;
    //     // DB에 저장 시도
    //     try {
    //         // 게시글(post 테이블) 저장
    //         $post_id = $this->posts->create_post($user_id, $category_id, $title, $detail);
    //         if (!$post_id) {
    //             throw new RuntimeException('게시글 등록 실패');
    //         }

    //         // 파일 메타 저장(최종 경로(uploads/) 기준으로 DB에 기록)
    //         foreach ($file_metas as $m) {
    //             $ok = $this->files->insert_file($post_id, [
    //                 'original_name' => $m['client_name'],           // 기존 파일명(사용자에게 보여줄 파일명)
    //                 'stored_name'   => $m['file_name'],             // 랜덤 저장명(unique)
    //                 'path'          => 'uploads/' . $m['file_name'],  // 최종 경로 기준으로 저장
    //                 'mime_type'     => $m['file_type'],             // image/jpg 등
    //                 'size_bytes'    => (int) round($m['file_size'] * 1024), // 파일 사이즈
    //             ]);
    //             // 실패 시 에러 처리
    //             if (!$ok) {
    //                 throw new RuntimeException('파일 메타데이터 저장 실패');
    //             }
    //         }
    //     } catch (Throwable $e) {
    //         // DB가 실패했으나 /uploads 는 아직 건드린 적 없음 -> 임시 폴더만 정리하면 끝
    //         foreach ($staged_fullpaths as $p) {
    //             if (is_file($p)) @unlink($p);
    //         }

    //         // 실패 로그 기록
    //         log_message('error', '게시글/파일메타 저장 실패: ' . $e->getMessage());
    //         // alert
    //         $this->session->set_flashdata('error', '게시글 등록에 실패했습니다.');
    //         // 게시물 작성 페이지로 리다이렉트
    //         return redirect('post/create');
    //     }

    //     // ----------------------------
    //     // 3단계) 임시폴더(uploads_tmp) → 최종폴더(uploads)로 이동
    //     // ----------------------------
    //     try {
    //         foreach ($file_metas as $m) {
    //             $from = $staging_dir . $m['file_name']; // 기존 경로(임시 폴더)
    //             $to   = $final_dir . $m['file_name'];   // 최종 경로

    //             // 파일 이동(rename) 시도
    //             // @rename: 파일 이동 혹은 이름 변경해주는 php 내장 함수
    //             if (!@rename($from, $to)) {
    //                 // 다른 파티션(/post -> /home) 등의 이유로 경로 변환 실패시
    //                 // 저장할 폴더에 해당 파일 복사 후 임시 폴더에 있는 파일 삭제하는 식으로 수행
    //                 if (!@copy($from, $to)) {   // 최종 폴더로 복사
    //                     throw new RuntimeException('파일 이동 실패: ' . $m['file_name']);
    //                 }
    //                 @unlink($from);             // 임시 폴더에 있던 파일 삭제
    //             }
    //         }
    //     } catch (Throwable $e) {
    //         // 이동 실패 시: 이미 DB에 insert 된 상황
    //         // => DB에서 방금 등록한 파일메타/게시글 삭제 + 임시파일 정리
    //         foreach ($staged_fullpaths as $p) {
    //             if (is_file($p)) @unlink($p);
    //         }

    //         // 파일 이동 실패 로그 기록
    //         log_message('error', '파일 승격(임시→최종) 실패: ' . $e->getMessage() . ' (post_id=' . $post_id . ')');

    //         // 선택 1) 사용자에게 실패 알리고 글 작성 취소 처리
    //         $this->posts->delete_post_cascade($post_id);
    //         $this->session->set_flashdata('error', '파일 이동 중 오류가 발생하여 등록이 취소되었습니다.');
    //         return redirect('post/create');

    //         // 선택 2) 글은 남기고, 실패한 파일만 제외/재시도 유도
    //         // $this->session->set_flashdata('error', '일부 파일 이동에 실패했습니다. 다시 시도해 주세요.');
    //         // return redirect('post/detail/'.$post_id);
    //     }

    //     // 성공
    //     $this->session->set_flashdata('success', '게시글이 등록되었습니다.');
    //     return redirect('post/detail/' . $post_id);
    // }



    // 파일 다운로드 함수
    public function download($file_id)
    {
        $file_id = (int)$file_id;

        // 파일 정보 조회
        $row = $this->posts->excute("
            SELECT file_id, original_name, stored_name, path, mime_type, size_bytes
            FROM file
            WHERE file_id = {$file_id}
            LIMIT 1
        ", 'row');

        // 파일이 없을 경우 404
        if (empty($row)) {
            log_message('error', 'download: file row missing. id=' . $file_id);
            show_404();
        }

        // 절대경로 생성
        // FCPATH: CI가 자동으로 정의해주는 프로젝트 루트 디렉토리 경로
        // DIRECTORY_SEPARATOR: 운영체제마다 다른 경로 구분 문자 자동 처리 상수(OS에 맞게 / \ 알아서 들어감)
        // 기본 경로 + 파일 상대 경로 안전하게 결합하는 로직
        // 즉, 이 코드로 OS와 상관없이 파일 실제 경로 생성 가능
        $abs = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($row['path'], DIRECTORY_SEPARATOR);

        // 그 경로에 파일이 없을 경우 404 오류
        if (!is_file($abs)) {
            log_message('error', 'download: file not found at ' . $abs);
            show_404();
        }

        // 원본 파일명으로 다운로드
        $data = file_get_contents($abs);
        force_download($row['original_name'], $data); // download 헬퍼를 활용하여 다운로드
    }


    // 수정 화면 출력
    public function edit($post_id)
    {
        $post_id = (int)$post_id;

        // 해당 게시글이 없으면 404
        $post = $this->posts->get_post($post_id);
        if (!$post) show_404();

        // 이 글의 작성자만 접근 가능하게 수정
        $me = $this->session->userdata('user');
        if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

        // 카테고리랑 해당 게시글에 저장된 파일 불러오기
        $categories = $this->posts->get_categories();
        $files = $this->posts->get_files($post_id);

        // 레이아웃 & CSS
        $this->template_->viewDefine('layout_common', 'post/edit.tpl');
        $this->optimizer->setCss('post-form.css');

        // 유효성 에러 HTML (form_validation 사용 전제)
        $validation_errors_html = validation_errors();

        // 셀렉트 기본 선택값: POST 우선, 없으면 기존 카테고리
        $selected_category_id = set_value('category_id', $post['category_id']);

        // 기존 파일 목록을 프런트에 전달할 JSON
        $existing_files = array_map(function ($f) {
            return [
                'id'   => (int)$f['file_id'],
                'name' => (string)$f['original_name'],
                'size' => isset($f['size_bytes']) ? (int)$f['size_bytes'] : 0,
            ];
        }, $files ?? []);
        $existing_files_json = json_encode($existing_files, JSON_UNESCAPED_UNICODE);

        $action_do_edit = site_url('post/do_edit/' . (int)$post['post_id']);

        $this->template_->viewAssign([
            'title'                 => '게시글 수정',
            'post'                  => $post,
            'categories'            => $categories,
            'selected_category_id'  => (int)$selected_category_id,
            'validation_errors_html' => $validation_errors_html,
            'existing_files_json'   => $existing_files_json,
            'action_do_edit' => $action_do_edit,
        ]);

        // $this->load->view('post/edit', [
        //     'title'      => '게시글 수정',
        //     'post'       => $post,
        //     'categories' => $categories,
        //     'files'      => $files,
        // ]);
    }

    // 수정 처리
    public function do_edit($post_id)
    {
        $post_id = (int)$post_id;
        $post = $this->posts->get_post($post_id);
        if (!$post) show_404();

        // 작성자만 수정
        $me = $this->session->userdata('user');
        if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

        // 입력값 검증
        $this->form_validation->set_rules('category_id', '카테고리', 'required|integer');
        $this->form_validation->set_rules('title', '제목', 'trim|required|max_length[200]');
        $this->form_validation->set_rules('detail', '내용', 'trim|required');

        if (!$this->form_validation->run()) {
            $categories = $this->posts->get_categories();
            $files = $this->posts->get_files($post_id);
            return $this->load->view('post/edit', [
                'title'      => '게시글 수정',
                'post'       => $post,
                'categories' => $categories,
                'files'      => $files
            ]);
        }

        // 스테이징 경로/최종 경로
        $staging_dir = FCPATH . 'uploads_tmp/';
        $final_dir   = FCPATH . 'uploads/';
        $allowed     = 'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx';

        // 1) 새 파일을 먼저 스테이징(실패 시 즉시 중단)
        $this->load->library('File_staging', null, 'fs');
        try {
            $stage = $this->fs->stage_from_input('files', $staging_dir, $allowed, 0);
            $file_metas = $stage['metas'];               // CI 업로드 메타 (file_name 등)
            $staged_fullpaths = $stage['staged_fullpaths'];
        } catch (Throwable $e) {
            log_message('error', '파일 스테이징 실패(수정): ' . $e->getMessage() . ' (post_id=' . $post_id . ')');
            $this->session->set_flashdata('error', '파일 업로드에 실패했습니다.');
            return redirect('post/edit/' . $post_id);
        }

        // 2) DB 트랜잭션 시작
        $this->db->trans_begin();

        // 2-1) 게시글 업데이트
        $ok_update = $this->posts->update_post(
            $post_id,
            (string)$this->input->post('title', TRUE),
            (string)$this->input->post('detail', TRUE),
            (int)$this->input->post('category_id')
        );

        if (!$ok_update) {
            $this->db->trans_rollback();
            // 스테이징 정리
            $this->fs->cleanup($staged_fullpaths);
            $this->session->set_flashdata('error', '게시글 수정에 실패했습니다.');
            return redirect('post/edit/' . $post_id);
        }

        // 2-2) 새 파일 메타를 "최종 경로(uploads/...)" 기준으로 DB 기록
        foreach ($file_metas as $m) {
            $ok = $this->files->insert_file($post_id, [
                'original_name' => $m['client_name'],
                'stored_name'   => $m['file_name'],
                'path'          => 'uploads/' . $m['file_name'],
                'mime_type'     => $m['file_type'],
                'size_bytes'    => (int) round($m['file_size'] * 1024),
            ]);
            if (!$ok) {
                $this->db->trans_rollback();
                $this->fs->cleanup($staged_fullpaths);
                $this->session->set_flashdata('error', '파일 메타데이터 저장에 실패했습니다.');
                return redirect('post/edit/' . $post_id);
            }
        }

        // 2-3) 여기까지 DB 임시 반영 상태 → 파일 승격 시도
        try {
            $this->fs->promote($file_metas, $staging_dir, $final_dir);
        } catch (Throwable $e) {
            // 파일 승격 실패 → DB 롤백 + 스테이징 정리
            $this->db->trans_rollback();
            $this->fs->cleanup($staged_fullpaths);
            log_message('error', '파일 승격(임시→최종) 실패(수정): ' . $e->getMessage() . ' (post_id=' . $post_id . ')');
            $this->session->set_flashdata('error', '파일 이동 중 오류가 발생해 수정이 취소되었습니다.');
            return redirect('post/edit/' . $post_id);
        }

        // 2-4) 기존 파일 삭제는 "승격 성공 후" 처리 (안전)
        $remove_ids = (array)$this->input->post('remove_files'); // [] or ['3','5']
        foreach ($remove_ids as $fid) {
            $meta = $this->posts->get_file((int)$fid);
            if ($meta && (int)$meta['post_id'] === $post_id) {
                // 1) DB에서 먼저 삭제(동일 트랜잭션 내)
                $this->posts->delete_file_row((int)$fid);
                // 2) 이후 실제 파일 삭제는 커밋 이후에도 되지만, 여기서 바로 지워도 무방
                $abs = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($meta['path'], DIRECTORY_SEPARATOR);
                if (is_file($abs)) @unlink($abs);
            }
        }

        // 2-5) 커밋
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            // 새로 추가된 파일(최종폴더)의 제거까지 되려면 추가 로직이 필요하지만,
            // promote 성공 후 trans_status FALSE는 드뭅니다. 필요시 여기서도 파일 제거 처리 가능.
            $this->session->set_flashdata('error', '수정 처리 중 오류가 발생했습니다.');
            return redirect('post/edit/' . $post_id);
        }
        $this->db->trans_commit();

        // 완료
        $this->session->set_flashdata('success', '게시글이 수정되었습니다.');
        return redirect('post/detail/' . $post_id);
    }


    // // 수정 처리
    // public function do_edit($post_id)
    // {
    //     $post_id = (int)$post_id;
    //     $post = $this->posts->get_post($post_id);
    //     if (!$post) show_404();

    //     // 작성자만 수정 가능하게
    //     $me = $this->session->userdata('user');
    //     if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

    //     // 입력값 검증
    //     $this->form_validation->set_rules('category_id', '카테고리', 'required|integer');
    //     $this->form_validation->set_rules('title', '제목', 'trim|required|max_length[200]');
    //     $this->form_validation->set_rules('detail', '내용', 'trim|required');
    //     // 검증 실패 시 게시글 수정 페이지 새로 로드
    //     if (!$this->form_validation->run()) {
    //         $categories = $this->posts->get_categories();
    //         $files = $this->posts->get_files($post_id);
    //         return $this->load->view('post/edit', [
    //             'title' => '게시글 수정',
    //             'post' => $post,
    //             'categories' => $categories,
    //             'files' => $files
    //         ]);
    //     }

    //     // 게시글 업데이트
    //     $this->posts->update_post(
    //         $post_id,
    //         (string)$this->input->post('title', TRUE),
    //         (string)$this->input->post('detail', TRUE),
    //         (int)$this->input->post('category_id')
    //     );

    //     // 파일 수정
    //     // (1) 기존 파일 삭제(체크된 것)
    //     $remove_ids = (array)$this->input->post('remove_files'); // [] or ['3','5']
    //     foreach ($remove_ids as $fid) {
    //         $meta = $this->posts->get_file((int)$fid);
    //         if ($meta && (int)$meta['post_id'] === $post_id) {
    //             $abs = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($meta['path'], DIRECTORY_SEPARATOR);
    //             if (is_file($abs)) @unlink($abs);
    //             $this->posts->delete_file_row((int)$fid);
    //         }
    //     }

    //     // (2) 새 파일 추가(여러 개)
    //     if (!empty($_FILES['files']['name'][0])) {
    //         $upload_path = FCPATH . 'uploads/';
    //         if (!is_dir($upload_path)) @mkdir($upload_path, 0777, true);
    //         $files = $_FILES['files'];
    //         for ($i = 0; $i < count($files['name']); $i++) {
    //             if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
    //             $_FILES['__one'] = [
    //                 'name' => $files['name'][$i],
    //                 'type' => $files['type'][$i],
    //                 'tmp_name' => $files['tmp_name'][$i],
    //                 'error' => $files['error'][$i],
    //                 'size' => $files['size'][$i],
    //             ];
    //             $config = [
    //                 'upload_path' => $upload_path,
    //                 'allowed_types' => 'jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx',
    //                 'max_size' => 0,
    //                 'encrypt_name' => TRUE
    //             ];
    //             $this->load->library('upload', $config, 'upl');

    //             // 파일 업로드 도중 하나라도 실패하면 전부 롤백
    //             if (!$this->upl->do_upload('__one')) {
    //                 throw new RuntimeException('파일 업로드 실패: ' . $this->upl->display_errors('', ''));
    //             }

    //             $up = $this->upl->data();
    //             $this->files->insert_file($post_id, [
    //                 'original_name' => $up['client_name'],
    //                 'stored_name'  => $up['file_name'],
    //                 'path'         => 'uploads/' . $up['file_name'],
    //                 'mime_type'    => $up['file_type'],
    //                 'size_bytes'   => (int)$up['file_size'] * 1024
    //             ]);
    //         }
    //     }

    //     $this->session->set_flashdata('success', '게시글이 수정되었습니다.');
    //     return redirect('post/detail/' . $post_id);
    // }

    // 삭제(소프트 delete - is_deleted 변수를 true로)
    public function delete($post_id)
    {
        // 해당하는 포스트가 없는 경우
        $post_id = (int)$post_id;
        $post = $this->posts->get_post($post_id);
        if (!$post) show_404();

        // 작성자만 삭제 가능
        $me = $this->session->userdata('user');
        if (!$me || (int)$me['user_id'] !== (int)$post['user_id']) show_404();

        $this->posts->soft_delete_post($post_id);
        $this->session->set_flashdata('success', '게시글이 삭제되었습니다.');
        return redirect('post'); // 목록으로
    }
}
