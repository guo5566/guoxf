<?php 
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */

load()->model('cloud');
load()->model('setting');

$dos = array(
	'auth',
	'build',
	'init',
	'schema',
	'download',
	'module.query',
	'module.info',
	'module.build',
	'module.setting.cloud', 	'theme.query',
	'theme.info',
	'theme.build',
	'application.build',
);
$do = in_array($do, $dos) ? $do : '';
if(empty($do)) {
	exit();
}
if($do != 'auth') {
	if(is_error(cloud_prepare())) {
		exit('cloud service is unavailable.');
	}
}

$post = file_get_contents('php://input');

if($do == 'auth') {
	$secret = random(32);
	$auth = @json_decode(base64_decode($post), true);
	if(empty($auth)) {
		exit;
	}
	$auth['secret'] = $secret;
	cache_write('cloud:auth:transfer', $auth);
	exit($secret);
}

if($do == 'build') {
	$dat = __secure_decode($post);
	if(!empty($dat)) {
		$secret = random(32);
		$ret = array();
		$ret['data'] = $dat;
		$ret['secret'] = $secret;
		file_put_contents(IA_ROOT . '/data/application.build', iserializer($ret));
		exit($secret);
	}
}

if($do == 'schema') {
	$dat = __secure_decode($post);
	if(!empty($dat)) {
		$secret = random(32);
		$ret = array();
		$ret['data'] = $dat;
		$ret['secret'] = $secret;
		file_put_contents(IA_ROOT . '/data/application.schema', iserializer($ret));
		exit($secret);
	}
}

if($do == 'download') {
	$data = base64_decode($post);
	if (base64_encode($data) != $post) {
		$data = $post;
	}
	$ret = iunserializer($data);
	$gz = function_exists('gzcompress') && function_exists('gzuncompress');
	$file = base64_decode($ret['file']);
	if($gz) {
		$file = gzuncompress($file);
	}

	$string = (md5($file) . $ret['path'] . $_W['setting']['site']['token']);
	if(md5($string) == $ret['sign']) {
		$path = IA_ROOT . $ret['path'];
		load()->func('file');
		@mkdirs(dirname($path));
		file_put_contents($path, $file);
		$sign = md5(md5_file($path) . $ret['path'] . $_W['setting']['site']['token']);
		if($ret['sign'] == $sign) {
			exit('success');
		}
	}
	exit('failed');
}

if(in_array($do, array('module.query', 'module.info', 'module.build', 'theme.query', 'theme.info', 'theme.build', 'application.build'))) {
	$dat = __secure_decode($post);
	if(!empty($dat)) {
		$secret = random(32);
		$ret = array();
		$ret['data'] = $dat;
		$ret['secret'] = $secret;
		file_put_contents(IA_ROOT . '/data/' . $do, iserializer($ret));
		exit($secret);
	}
}

if ($do == 'module.setting.cloud') {
	$data = __secure_decode($post);
	$data = unserialize($data);
	$setting = $data['setting'];
	$uniacid = $data['acid'];
	
	foreach ($data['struct'] as $name => $type) {
		if ($type == 'image') {
			$url = $setting[$name];
			$attach = cloud_resource_to_local($uniacid, 'image', $url);
			if (!is_error($attach)) {
				$setting[$name] = $attach['attachment'];
			}
		} elseif ($type == 'richtext'){
			$content = $setting[$name];
			preg_match_all('/src=&quot;(\S*)&quot;/', $content, $matches);
			if ($matches[1]) {
				$new_urls = array();
				foreach ($matches[1] as $url) {
					$attach = cloud_resource_to_local($uniacid, 'image', $url);
					if (!is_error($attach)) {
						$new_urls[] = $attach['url'];;
					}
				};
				$setting[$name] = str_replace($matches[1], $new_urls, $setting[$name]);
			}
		} elseif ($type == 'images'){
			foreach ($setting[$name] as $idx => $url) {
				$attach = cloud_resource_to_local($uniacid, 'image', $url);
				if (!is_error($attach)) {
					$setting[$name][$idx] = $attach['attachment'];
				}
			}
		}
	}
	$_W['uniacid'] = $data['acid'];
	$module = WeUtility::createModule($data['module']);
	$module->saveSettings($data['setting']);
	cache_write("modulesetting:{$data['acid']}:{$data['module']}", $data['setting']);
	
	load()->model('cache');
	cache_build_modules();
	echo 'success';
	exit;
}

function __secure_decode($post) {
	global $_W;
	$data = base64_decode($post);
	if (base64_encode($data) != $post) {
		$data = $post;
	}
	$ret = iunserializer($data);
	$string = ($ret['data'] . $_W['setting']['site']['token']);
	if(md5($string) == $ret['sign']) {
		return $ret['data'];
	}
	return false;
}