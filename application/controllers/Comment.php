<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** 
 * CHECKLIST: 무한 스크롤로 구현했을 때 댓글 토스트 뜨면 그 주위 앞뒤 100개씩 가져오면 
 * 작성중이던 대댓 캐시에 저장? -> 작성중이던 대댓으로 돌아가기 눌러서 캐시에 있던 정보 가져오기?
 * 캐시는 세션 스토리지에 저장 -> 새로고침 정도는 버티나?<div class=""></div>
 * 만약 쭉 내려서 대댓 쓰다가 다른 사람이 댓글 달았다 토스트 떠서
 * 그거 눌렀더니 한참 이동했어 그래서 그 사람이 대댓 쓰던 댓글이 사라졌어
 * 근데? 캐시에 저장해두면 그거 찾을 수 있나? 
 */

class Comment extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Comment_model', 'comment');
        $this->load->helper(['form', 'url']);
    }

    // /** 게시물의 댓글 전체 조회 */
    // public function list($post_id)
    // {
    //     $post_id   = (int)$post_id;
    //     // 이전 조회 마지막 댓글 path 이후부터 가져오겠다는 뜻
    //     $afterPath = $this->params['afterPath'] ?? '';
    //     // 한 번에 가져올 갯수 제한 200개
    //     $limit     = (int)($this->params['limit'] ?? 200);

    //     $data['comments'] = $this->comment->get_by_post_page($post_id, $afterPath, $limit);
    //     $this->load->vars($data);
    //     $this->load->view('comment/list');
    // }


    /** 토스트 클릭 시 윈도우 기반 댓글 목록 불러오기 */
    public function around_json($post_id)
    {
        $post_id = (int)$post_id;
        if ($post_id <= 0) {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => 'bad post_id'], JSON_UNESCAPED_UNICODE));
        }

        // centerId 또는 centerPath 둘 중 하나 받기
        $centerId   = (int)$this->input->get('centerId', true);
        $centerPath = (string)$this->input->get('centerPath', true);

        $before = (int)$this->input->get('before', true);
        $after  = (int)$this->input->get('after', true);
        if ($before <= 0) $before = 100;
        if ($after  <= 0) $after  = 100;
        if ($before > 500) $before = 500;
        if ($after  > 500) $after  = 500;

        // centerPath 없으면 centerId로 path 조회
        if ($centerPath === '' && $centerId > 0) {
            $row = $this->comment->get_by_id($centerId);
            if (!$row || !empty($row['is_deleted'])) {
                return $this->output->set_status_header(404)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode(['status' => 'error', 'message' => 'center not found'], JSON_UNESCAPED_UNICODE));
            }
            $centerPath = (string)$row['path'];
        }
        if ($centerPath === '') {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => 'centerPath/centerId required'], JSON_UNESCAPED_UNICODE));
        }

        $win = $this->comment->get_window_around_path($post_id, $centerPath, $before, $after);
        $items = $win['items'] ?? [];

        // li 템플릿 렌더
        $this->template_->viewDefine('comment_items', 'comment/_items.tpl');
        // $this->template_->viewAssign(['comments' => $items]);

        $me = $this->session->userdata('user');
        $this->template_->viewAssign([
            'comments' => $items,
            'user_id'  => (int)($me['user_id'] ?? 0),
        ]);

        $html = (string)$this->template_->viewFetch('comment_items');

        return $this->output->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'status'    => 'success',
                'html'      => $html,
                'hasPrev'   => !empty($win['hasPrev']),
                'prevCursor' => (string)($win['prevCursor'] ?? ''), // 위쪽(작은 path 끝점)
                'hasNext'   => !empty($win['hasNext']),
                'nextCursor' => (string)($win['nextCursor'] ?? ''), // 아래쪽(큰 path 끝점)
                'centerPath' => $centerPath,
            ], JSON_UNESCAPED_UNICODE));
    }



    public function list_json($post_id)
    {
        $post_id = (int)$post_id;
        if ($post_id <= 0) {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => 'error',
                    'error'  => ['code' => 'BAD_REQUEST', 'message' => 'post_id가 유효하지 않습니다.']
                ], JSON_UNESCAPED_UNICODE));
        }

        // afterPath, limit 파라미터 (MY_Controller의 $this->params 사용 or GET 직접)
        $afterPath = isset($this->params['afterPath'])
            ? (string)$this->params['afterPath']
            : (string)$this->input->get('afterPath', true);

        $limit = isset($this->params['limit'])
            ? (int)$this->params['limit']
            : (int)$this->input->get('limit', true);

        // TODO: 댓글 한 번에 몇 개씩 불러오는게 가장 적절한지 테스트
        if ($limit < 1)   $limit = 200;
        if ($limit > 500) $limit = 500;

        // 커서 기반 페이지 조회 (limit+1 내부 처리로 hasMore/nextCursor 계산)
        $page = $this->comment->get_by_post_page_fetch_plus($post_id, $afterPath, $limit);
        $items = isset($page['items']) ? $page['items'] : [];

        // Template_로 li 조각 렌더 (절대 layout_common/layout_empty 정의 금지!)
        $this->template_->viewDefine('comment_items', 'comment/_items.tpl');
        // $this->template_->viewAssign(['comments' => $items]);
        $me = $this->session->userdata('user');
        $this->template_->viewAssign([
            'comments' => $items,
            'user_id'  => (int)($me['user_id'] ?? 0),
        ]);

        // 문자열로 가져오기 (출력 X)
        $html = (string)$this->template_->viewFetch('comment_items');

        $this->optimizer->setCss('comments.css');

        // JSON 반환
        return $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'status'     => 'success',
                'html'       => $html,
                'hasMore'    => !empty($page['hasMore']),
                'nextCursor' => isset($page['nextCursor']) ? (string)$page['nextCursor'] : '',
            ], JSON_UNESCAPED_UNICODE));
    }


    // // 해당 게시글의 댓글 조회(상위 n개)
    // public function list_json($post_id)
    // {
    //     $post_id   = (int)$post_id;
    //     if ($post_id <= 0) {
    //         return $this->output->set_status_header(400)
    //             ->set_content_type('application/json', 'utf-8')
    //             ->set_output(json_encode([
    //                 'status' => 'error',
    //                 'error'  => ['code' => 'BAD_REQUEST', 'message' => 'post_id가 유효하지 않습니다.']
    //             ], JSON_UNESCAPED_UNICODE));
    //     }
    //     // 이전 조회 마지막 댓글 path 이후부터 가져오겠다는 뜻
    //     $afterPath = $this->params['afterPath'] ?? '';
    //     // 한 번에 가져올 갯수 제한 200개
    //     $limit     = (int)($this->params['limit'] ?? 10);
    //     if ($limit < 1) $limit = 1;
    //     if ($limit > 500) $limit = 500; // 안전 상한

    //     // 모델에서 limit+1로 가져와 hasMore(데이터가 더 있는지)/nextCursor(다음에 불러올 첫번째 애 경로) 계산
    //     $page = $this->comment->get_by_post_page_fetch_plus($post_id, $afterPath, $limit);

    //     // 아이템 조각만 렌더 (부분뷰: comment/_items.php)
    //     $html = $this->load->view('comment/_items', ['comments' => $page['items']], TRUE);

    //     // json으로 반환값 변환
    //     return $this->output->set_content_type('application/json', 'utf-8')
    //         ->set_output(json_encode([
    //             'status'     => 'success',
    //             'html'       => $html,
    //             'hasMore'    => $page['hasMore'],
    //             'nextCursor' => $page['nextCursor'],
    //         ], JSON_UNESCAPED_UNICODE));
    // }

    // /** 특정 댓글의 직계 자식 댓글만 조회해서 가져오는 함수 */
    // public function children()
    // {
    //     $parent_id   = (int)($this->params['parent_id'] ?? 0);
    //     $lastCreated = $this->params['lastCreated'] ?? null;    // 작성일
    //     $limit       = (int)($this->params['limit'] ?? 50);     // 한 번에 가져올 개수(default: 50)

    //     try {
    //         // db 조회
    //         $rows = $this->comment->get_children_page($parent_id, $lastCreated, $lastId, $limit);
    //         return $this->output
    //             ->set_content_type('application/json','utf-8')
    //             ->set_output(json_encode(['status'=>'success','rows'=>$rows], JSON_UNESCAPED_UNICODE));
    //     } catch (Throwable $e) {
    //         // 오류 발생 시
    //         // 로그 기록
    //         log_message('error', 'children error: '.$e->getMessage());
    //         return $this->output->set_status_header(500)
    //             ->set_content_type('application/json','utf-8')
    //             ->set_output(json_encode(['status'=>'error','message'=>'서버 오류'], JSON_UNESCAPED_UNICODE));
    //     }
    // }

    // /** 특정 루트 스레드 전위순서 스트리밍 (path 커서 기반) */
    // public function thread($root_id)
    // {
    //     $root_id   = (int)$root_id;
    //     $afterPath = $this->params['afterPath'] ?? '';
    //     $limit     = (int)($this->params['limit'] ?? 200);

    //     try {
    //         $rows = $this->comment->get_thread_page($root_id, $afterPath, $limit);
    //         return $this->output
    //             ->set_content_type('application/json','utf-8')
    //             ->set_output(json_encode(['status'=>'success','rows'=>$rows], JSON_UNESCAPED_UNICODE));
    //     } catch (Throwable $e) {
    //         log_message('error', 'thread error: '.$e->getMessage());
    //         return $this->output->set_status_header(500)
    //             ->set_content_type('application/json','utf-8')
    //             ->set_output(json_encode(['status'=>'error','message'=>'서버 오류'], JSON_UNESCAPED_UNICODE));
    //     }
    // }

    /** 댓글 작성 */
    public function create()
    {
        // AJAX(Asynchronous JavaScript And XML) 응답 
        // = 페이지를 새로고침하지 않고 서버에 요청을 보내고 응답을 받는 기술
        // = HTML 페이지 전체가 아니라 데이터(JSON, 텍스트 등)만 반환하는 응답

        // php 오류 출력 비활성화 -> AJAX로 요청할 때 php 경고 등이 응답 본문에 출력되어 json을 깨트리는 일 방지
        @ini_set('display_errors', 0);
        // php 에러 보고 중 CI3의 오래된 문법에서 발생하는 경고는 무시
        @error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        // 사용자 정보 조회
        $me = $this->session->userdata('user');
        $user_id = is_array($me) ? (int)($me['user_id'] ?? 0) : (int)$me;

        $post_id     = (int)($this->params['post_id'] ?? 0);
        $detail      = trim((string)($this->params['comment_detail'] ?? ''));
        $parent_raw  = $this->params['parent_id'] ?? null;
        $parent_id   = (isset($parent_raw) && (int)$parent_raw > 0) ? (int)$parent_raw : null;

        // 사용자 아이디가 비어있는 경우 401 에러 처리
        if (!$user_id) {
            return $this->output->set_status_header(401)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => '로그인이 필요합니다'], JSON_UNESCAPED_UNICODE));
        }
        // 포스트 아이디 혹은 댓글 내용이 비어있는 경우 500 에러
        if (!$post_id || $detail === '') {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => '댓글을 입력해주세요'], JSON_UNESCAPED_UNICODE));
        }

        // 대댓인 경우 부모 댓글이 삭제된 경우
        $parent_id = (int)$this->input->post('parent_id');
        if ($parent_id > 0) {
            $parent = $this->comment->get_by_id($parent_id); // or find()
            if (!$parent) {
                return $this->output->set_status_header(410)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => 'error',
                        'message' => '부모 댓글이 존재하지 않습니다.'
                    ], JSON_UNESCAPED_UNICODE));
            }
            if (
                (int)$parent['is_deleted'] === 1 ||
                $parent['comment_detail'] === '삭제된 댓글입니다'
            ) {
                return $this->output->set_status_header(410)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => 'error',
                        'message' => '이미 삭제된 댓글에 답글을 달 수 없습니다.'
                    ], JSON_UNESCAPED_UNICODE));
            }
        }


        // 댓글 저장 시도
        try {
            $new_id = $this->comment->create($post_id, $user_id, $detail, $parent_id);

            // =========================================================
            // 여기서부터 댓글 등록 성공 시 자동 스크롤 + 하이라이트 관련 코드
            // =========================================================

            $row = $this->comment->get_by_id($new_id);
            if (!$row) {
                return $this->output->set_status_header(500)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode(['status' => 'error', 'message' => '방금 작성한 댓글을 찾을 수 없습니다'], JSON_UNESCAPED_UNICODE));
            }

            // snippet 생성 (앞 40자 정도)
            $rawDetail = $row['comment_detail'] ?? '';
            $snippet = mb_substr(strip_tags($rawDetail), 0, 40, 'UTF-8');

            $this->template_->viewDefine('comment_items', 'comment/_items.tpl');
            // $this->template_->viewAssign(['comments' => [$row]]);
            $this->template_->viewAssign([
                'comments' => [$row],
                'user_id'  => (int)$user_id, // 현재 로그인 사용자
            ]);
            $html = (string)$this->template_->viewFetch('comment_items');

            return $this->output
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status'      => 'success',
                    'comment_id'  => $new_id,
                    'user_id'      => (int)$user_id,
                    'snippet' => $snippet,
                    'html' => $html,
                    'message' => '댓글이 등록되었습니다.'
                ], JSON_UNESCAPED_UNICODE));

            // =========================================================
            // 여기서까지
            // =========================================================
        } catch (Throwable $e) {
            // 오류 발생 시 로그 기록
            log_message('error', 'comment/create failed: ' . $e->getMessage());
            // http 응답 코드 500으로 설정하여 전송
            return $this->output->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => '서버 오류'], JSON_UNESCAPED_UNICODE));
        }
    }


    /** 중간 대댓 삽입용 단일 댓글 조회 함수 */
    public function item($comment_id)
    {
        $comment_id = (int)$comment_id;
        if ($comment_id <= 0) {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'bad comment_id'
                ], JSON_UNESCAPED_UNICODE));
        }

        $me = $this->session->userdata('user');
        $row = $this->comment->get_by_id($comment_id);
        if (!$row || !empty($row['is_deleted'])) {
            return $this->output->set_status_header(404)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'not found'
                ], JSON_UNESCAPED_UNICODE));
        }

        // 템플릿으로 li 1개 렌더
        $this->template_->viewDefine('comment_items', 'comment/_items.tpl');
        // $this->template_->viewAssign(['comments' => [$row]]);

        $this->template_->viewAssign([
            'comments' => [$row],
            'user_id'  => (int)($me['user_id'] ?? 0),
        ]);
        $html = (string)$this->template_->viewFetch('comment_items');

        return $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'status' => 'success',
                'html'   => $html,
                'user_id' => (int)($me['user_id'] ?? 0),
                'path'   => (string)$row['path'],   // 클라이언트 정렬용
            ], JSON_UNESCAPED_UNICODE));
    }



    // =========================================================
    // SSE 관련 엔드포인트
    // =========================================================
    public function stream($post_id)
    {
        header_remove();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        @set_time_limit(0);
        ignore_user_abort(true);
        while (ob_get_level() > 0) @ob_end_clean();

        $me = $this->session->userdata('user');
        if (!$me) {
            echo "event: error\ndata:{\"message\":\"unauthorized\"}\n\n";
            flush();
            return;
        }
        session_write_close(); // ★ 세션 락 해제

        $post_id = (int)$post_id;
        if ($post_id <= 0) {
            echo "event: error\ndata:{\"message\":\"bad post_id\"}\n\n";
            flush();
            return;
        }

        $last = 0;
        if (!empty($_SERVER['HTTP_LAST_EVENT_ID'])) $last = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
        else if (isset($_GET['lastId'])) $last = (int)$_GET['lastId'];

        $row = $this->db->select('MAX(comment_id) max_id')->where('post_id', $post_id)->where('is_deleted', 0)->get('comment')->row_array();
        $cursor = max((int)($row['max_id'] ?? 0), $last);

        $deadline = time() + 120;
        while (!connection_aborted() && time() < $deadline) {
            $rows = $this->db->select('c.comment_id,c.post_id,c.user_id,c.comment_detail,c.created_at,u.name AS author_name')
                ->from('comment c')->join('users u', 'u.user_id=c.user_id')
                ->where('c.post_id', $post_id)->where('c.is_deleted', 0)
                ->where('c.comment_id >', $cursor)->order_by('c.comment_id', 'ASC')->get()->result_array();

            if ($rows) {
                foreach ($rows as $n) {
                    $cursor = (int)$n['comment_id'];
                    $data = [
                        'comment_id' => (int)$n['comment_id'],
                        'post_id' => (int)$n['post_id'],
                        'user_id' => (int)$n['user_id'],
                        'author_name' => (string)$n['author_name'],
                        'snippet' => mb_substr(trim((string)$n['comment_detail']), 0, 60),
                        'created_at' => (string)$n['created_at'],
                    ];
                    echo "id: {$cursor}\n";
                    echo "event: comment\n";
                    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    @ob_flush();
                }
            } else {
                echo ": ping\n\n";
                @flush();
                @ob_flush();
                sleep(2);
            }
        }
        echo "event: done\ndata: {}\n\n";
        @flush();
        @ob_flush();
    }

    // =========================================================
    // 댓글 삭제 로직
    // =========================================================
    public function delete($comment_id)
    {
        @ini_set('display_errors', 0);
        @error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        $comment_id = (int)$comment_id;

        $row = $this->comment->get_by_id($comment_id);
        if ((int)$row['is_deleted'] === 1) {
            return $this->output->set_status_header(410)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => '이미 삭제된 댓글입니다.']));
        }


        if ($comment_id <= 0) {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => 'bad comment_id'], JSON_UNESCAPED_UNICODE));
        }

        $me = $this->session->userdata('user');
        $my_id = is_array($me) ? (int)($me['user_id'] ?? 0) : (int)$me;
        if (!$my_id) {
            return $this->output->set_status_header(401)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => '로그인이 필요합니다'], JSON_UNESCAPED_UNICODE));
        }

        // 내가 쓴 댓글인지 확인
        $row = $this->db->select('comment_id, user_id, comment_detail')
            ->from('comment')->where('comment_id', $comment_id)->get()->row_array();
        if (!$row) {
            return $this->output->set_status_header(404)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => 'not found'], JSON_UNESCAPED_UNICODE));
        }
        if ((int)$row['user_id'] !== $my_id) {
            return $this->output->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => '권한이 없습니다'], JSON_UNESCAPED_UNICODE));
        }
        // 이미 삭제문구면 OK 처리
        if ($row['comment_detail'] === '삭제된 댓글입니다') {
            return $this->output->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'success', 'comment_id' => $comment_id], JSON_UNESCAPED_UNICODE));
        }

        // 내용만 치환 (is_deleted는 그대로 둠: 목록에 남겨두기 위함)
        $ok = $this->comment->soft_delete($comment_id);
        if (!$ok) {
            return $this->output->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['status' => 'error', 'message' => '삭제 실패'], JSON_UNESCAPED_UNICODE));
        }

        return $this->output->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['status' => 'success', 'comment_id' => $comment_id], JSON_UNESCAPED_UNICODE));
    }

    // 댓글 상태 확인: 존재/삭제 여부만 반환 (비로그인 허용 가능)
    public function status($comment_id)
    {
        $comment_id = (int)$comment_id;
        if ($comment_id <= 0) {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['exists' => false, 'is_deleted' => true, 'message' => 'bad id'], JSON_UNESCAPED_UNICODE));
        }

        $row = $this->comment->get_by_id($comment_id);
        if (!$row) {
            return $this->output->set_status_header(404)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['exists' => false, 'is_deleted' => true], JSON_UNESCAPED_UNICODE));
        }

        return $this->output->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'exists'     => true,
                'is_deleted' => ((int)$row['is_deleted'] === 1)
                    || ($row['comment_detail'] === '삭제된 댓글입니다')
            ], JSON_UNESCAPED_UNICODE));
    }
}
