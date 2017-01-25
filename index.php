<?php
require_once dirname(__FILE__).'/vendor/autoload.php';

session_start();

function get_duelist($name) {
	$status = array();
	$status['name'] = $name;

	$md5 = md5($name);
	$status['md5'] = $md5;
	$status['hp'] = hexdec(substr($md5, 0, 3));
	$status['attack'] = hexdec(substr($md5, 8, 2));
	$status['defense'] = hexdec(substr($md5, 16, 2));
	$status['agility'] = hexdec(substr($md5, 24, 2));

	return $status;
}

function get_damage_point($attack, $defense) {
	$damage_point = round($attack * mt_rand(80, 200) / 100) - round($defense / 3);
	return $damage_point > 0 ? $damage_point : 0;
}

$messages = array();
$you = array();
$enemy = array();
$winning = 0;

// 覚醒
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['your_name'])) {
	$you = get_duelist($_POST['your_name']);
	$messages[] = $you['name'].'が覚醒した';
} else {
	if (!empty($_SESSION['you'])) {
		$you = $_SESSION['you'];
	}
}

// 敵作成
if (empty($_SESSION['enemy'])) {
	$faker = Faker\Factory::create('ja_JP');
	$enemy = get_duelist($faker->name);
	$message[] = $enemy['name'].'が現れた';
} else {
	$enemy = $_SESSION['enemy'];
}


// 攻撃
if (count($you) > 0 && count($enemy) > 0) {
	$enemy_damage = get_damage_point($you['attack'], $enemy['defense']);
	$your_damage = get_damage_point($enemy['attack'], $you['defense']);
	
	if ($you['agility'] >= $enemy['agility']) {
		$messages[] = $you['name'].'の攻撃';
		$messages[] = $enemy['name'].'に'.$enemy_damage.'のダメージ';
		$enemy['hp'] = $enemy['hp'] - $enemy_damage;
		if ($enemy['hp'] > 0) {
			$messages[] = $enemy['name'].'の攻撃';
			$messages[] = $you['name'].'に'.$your_damage.'のダメージ';
			$you['hp'] = $you['hp'] - $your_damage;
		}
	} else {
		$messages[] = $enemy['name'].'の攻撃';
		$messages[] = $you['name'].'に'.$your_damage.'のダメージ';
		$you['hp'] = $you['hp'] - $your_damage;
		if ($you['hp'] > 0) {
			$messages[] = $you['name'].'の攻撃';
			$messages[] = $enemy['name'].'に'.$enemy_damage.'のダメージ';
			$enemy['hp'] = $enemy['hp'] - $enemy_damage;
		}
	}
	
	// 敵死亡
	if ($enemy['hp'] <= 0 && $you['hp'] > 0) {
		$enemy['hp'] = 0;
		$messages[] = $enemy['name'].'を倒した';
		if (empty($_SESSION['graveyard'])) {
			$_SESSION['graveyard'] = array();
		}
		$_SESSION['graveyard'][] = $enemy;
		$enemy = array();
		unset($_SESSION['enemy']);
	}
	
	// 自分死亡
	if ($you['hp'] <= 0) {
		$you['hp'] = 0;
		$messages[] = $you['name'].'は倒れた';
		$you = array();
		unset($_SESSION['you']);
		unset($_SESSION['graveyard']);
	}
}

$_SESSION['you'] = $you;
$_SESSION['enemy'] = $enemy;

if (!empty($_SESSION['graveyard'])) {
	$winning = count($_SESSION['graveyard']);
}

// 表示
$loader = new Twig_Loader_Filesystem(dirname(__FILE__));
$twig = new Twig_Environment($loader);

$template = $twig->loadTemplate('index.twig');
$context = array(
	'messages' => $messages,
	'you' => $you,
	'enemy' => $enemy,
	'winning' => $winning,
);

echo $template->render($context);
