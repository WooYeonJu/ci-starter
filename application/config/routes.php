<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller']  = 'auth/login'; // 시작화면 = 로그인화면
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// 로그인 라우터 설정
$route['login']['get']   = 'auth/login';

// 회원가입 라우터 설정
$route['register']['get']     = 'auth/register';
$route['do_register']['post']    = 'auth/do_register';

// 로그아웃 라우터 설정
$route['logout']['post'] = 'auth/logout';

// 포스트 목록 라우터 설정
$route['post']['get']  = 'post/list';

// 포스트 생성 라우터 설정
$route['post/create']['get']    = 'post/create';
$route['post/do_create']['post'] = 'post/do_create';

// 파일 다운로드용 라우터 설정
$route['post/download/(:num)']['get']  = 'post/download/$1';

// 게시물 수정 라우터 설정
$route['post/edit/(:num)']['get']     = 'post/edit/$1';
$route['post/do_edit/(:num)']['post'] = 'post/do_edit/$1';

// 게시물 삭제 라우터 설정
$route['post/delete/(:num)']['post']  = 'post/delete/$1';

// 댓글 알림 SSE 라우터 설정
$route['comment/stream/(:num)'] = 'comment/stream/$1';

// 중간 대댓 삽입 시 단일 댓글 조회용 라우터 설정
$route['comment/item/(:num)'] = 'comment/item/$1';

// 댓글 삭제 라우터 설정
$route['comment/delete/(:num)'] = 'comment/delete/$1';
