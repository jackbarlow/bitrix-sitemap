<?
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");


$config = include('sitemap.config.php');

date_default_timezone_set('Europe/Moscow');

$domain =  SITE_SERVER_NAME ? SITE_SERVER_NAME : $_SERVER['HTTP_HOST'];

$arResult = [];
// добавляем главную страницу
$arResult[] = [
	'url' => $domain,
	'lastmod' => date("c", filemtime(realpath($_SERVER['DOCUMENT_ROOT']).'/index.php')),
];

// добавление статичных страниц
foreach ($config['FOLDER'] as $value) {

	$startDir = realpath($_SERVER['DOCUMENT_ROOT'].$value);

	$curDir = dirname( $startDir );

	if(!function_exists('findDirs')) {
		function findDirs($dir, $curDir, $startDir){
			$result = [];

			$files = scandir( $dir );

			foreach($files as $f){
				if($f == '.' | $f == '..') continue;

				if(!is_dir($dir.'/'.$f) && $f != 'index.php') continue;

				// если index.php есть, то страница по данному адресу существует
				if($f == 'index.php'){
					$result[] = $dir;
				}

				if(is_dir($dir.'/'.$f)){
					$result = array_merge($result, findDirs($dir.'/'.$f, $curDir, $startDir));
				}
			}

			return $result;
		}
	}

	$result = findDirs($startDir, $curDir, $startDir);

	// убираем из результатов абсолютный путь и добавляем /
	foreach($result as $dir){
		$lastMod = date("c", filemtime($dir.'/index.php'));
		$dir = str_replace($curDir, '', $dir);
		$url = $domain.$dir.'/';
		$url = preg_replace('/\/+/', '/', $url);

		$arResult[] = [
			'url' => $url,
			'lastmod' => $lastMod,
		];
	}

}

// добавление страниц из инфоблоков
foreach($config['IBLOCK_ID'] as $iblock_id) {

	$arFilter = array(		
		'ACTIVE' => 'Y',
		'GLOBAL_ACTIVE'=>'Y',
		"IBLOCK_ID" => $iblock_id,
		"IBLOCK_ACTIVE"=>"Y",
	);

	$arItems = CIBlockSection::GetList(
		Array('LEFT_MARGIN' => "ASC", "SORT" => "ASC"),
		$arFilter,
		false,
		["ID", "DEPTH_LEVEL", "NAME", "SECTION_PAGE_URL", "TIMESTAMP_X"]
	);
	
	$i = 0;

	while($arItem = $arItems->GetNext()){

		$arResult[] = [
			'url' => $domain.$arItem['SECTION_PAGE_URL'],
			'lastmod' => date('c', strtotime($arItem['TIMESTAMP_X'])),
		];
		
		$arElements = CIBlockElement::GetList(
			Array("SORT" => "ASC"),
			array(
				'ACTIVE' => 'Y',
				'IBLOCK_ID' => $iblock_id,
				'SECTION_GLOBAL_ACTIVE'=>'Y',
				'SECTION_ID' => $arItem['ID'],
			),
			false
		);
		$i++;
		
		while($element = $arElements->GetNext()){
			$arResult[] = [
				'url' => $domain.$element['DETAIL_PAGE_URL'],
				'lastmod' => date('c', strtotime($element['TIMESTAMP_X'])),
			];
			$i++;
		}
	}
	
	// элементы без групп
	$arElements = CIBlockElement::GetList(
		Array("SORT" => "ASC"),
		array(
			'ACTIVE' => 'Y',
			'IBLOCK_ID' => $iblock_id,
			'SECTION_GLOBAL_ACTIVE'=>'Y',
			'SECTION_ID' => false
		),
		false
	);
	
	while($element = $arElements->GetNext()){
		$arResult[] = [
			'url' => $domain.$element['DETAIL_PAGE_URL'],
			'lastmod' => date('c', strtotime($element['TIMESTAMP_X'])),
		];
	}

}

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($arResult as $item){

	$countSlash = count(preg_split('/\//', $item['url'], 0, PREG_SPLIT_NO_EMPTY))-1;
	if($countSlash <= 0){
		$priority = 1.0;
	} else {
		$priority = 1.0 - 0.2 * $countSlash;
		if($priority < 0.2) $priority = 0.2;
	}



	$xml .= '<url>
	<loc>https://'.$item['url'].'</loc>
	<lastmod>'.$item['lastmod'].'</lastmod>
	<priority>'.$priority.'</priority>
	</url>';
}
$xml.= '</urlset>';

file_put_contents(realpath($_SERVER['DOCUMENT_ROOT']).'/sitemap.xml', $xml);
?>