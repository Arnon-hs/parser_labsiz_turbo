<?php
require('vendor/autoload.php');

use Symfony\Component\DomCrawler\Crawler;

mb_internal_encoding("UTF-8");
define('DEFAULT_URL', "https://labsiz.ru/");
define('SITE_NAME', "Лабсиз");

/**
 * Прогресс бар
 *
 * @param int $done выполнено итераций
 * @param int $total всего итераций
 * @param string $info информационное сообщение
 * @param int $width ширина прогресс бара
 *
 * @return string
 */
function progressBar($done, $total, $info="", $width=50) {
    $perc = round(($done * 100) / $total);
    $bar = round(($width * $perc) / 100);
    return sprintf("%s%% [%s>%s] %s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width-$bar), $info);
}

/**
 * @param string $url
 * @return mixed
 */
function curlConnect($url = DEFAULT_URL){
    $curl = curl_init($url);
    // ПОДГОТОВКА ЗАГОЛОВКОВ
    $uagent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36";
    // ВСЯКИЕ ПАРАМЕТРЫ
    curl_setopt($curl, CURLOPT_USERAGENT, $uagent);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_COOKIE, "PMBC=96152e8e9a0168a731539c5e52c6b39a; PHPSESSID=jl0i13pn3157qca807jgp0jqa7; serverId=2");
    $page = curl_exec($curl);
    curl_close($curl);
//    dump($page);
    return $page;
}

function findContent(Crawler $crawler){
    try {
        $crawler = $crawler->filter('.cont_wr')->each(function (Crawler $node){
            $result['title'] = $node->siblings()->filter('.way_wr_box')->filter('h1')->text();
//            echo $h1.PHP_EOL;
            $result['content'] = $node->html();
//            dump($result['content']);
            $pos = strpos($result['content'], 'hero-message', strpos($result['content'], 'hero-message') + 1);
            $pos2 = strpos($result['content'], 'message-right', strpos($result['content'], 'message-right') + 1);
           // echo PHP_EOL.$pos;
            $occurances = substr_count($result['content'], 'hero-message');
            $occurances2 = substr_count($result['content'], 'message-right');
            if ($occurances > 1 || $occurances2 > 1) {
                if($pos || $pos2){
                    $result['content'] = preg_replace("/\s?<div class=\"hero-message.*?\"[^>]*?>.*?<\/div>\s?/si", " ", $result['content'], 1);
                    $result['content'] = preg_replace("/\s?<div class=\"message-right.*?\"[^>]*?>.*?<\/div>\s?/si", " ", $result['content'], 1);
                }
            }
            return $result;
        });
//        dd($crawler);
        return $crawler;
    } catch (\Exception $e) {
        echo "Skip node! ". $e->getMessage();
        return false;
    }
}

/**
 * @param $content
 * @return bool
 */
function putContent($content){
    try{
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0','UTF-8');
        $xml->startElement("rss");
        $xml->writeAttribute("version", "2.0");
        $xml->writeAttribute("xmlns:media", "http://search.yahoo.com/mrss/");
        $xml->writeAttribute("xmlns:turbo", "http://turbo.yandex.ru");
        $xml->writeAttribute("xmlns:yandex", "http://news.yandex.ru");
            $xml->startElement("channel");
            $xml->writeElement("title", SITE_NAME);
            $xml->writeElement("link", DEFAULT_URL);
            $xml->writeElement("description", "turbo");
            $xml->writeElement("language", "ru");
            $xml->writeElement("generator", "Generated by parser Arnon_hs");
                foreach ($content as $i => $item):
                    $xml->startElement("item");
                    $xml->writeAttribute("turbo", "true");
                        $xml->writeElement("title", !empty($item[0]['title'])?$item[0]['title']:SITE_NAME);
                        $xml->writeElement("link", $item[0]['link']);
                        $xml->writeElement("turbo:topic", !empty($item[0]['title'])?$item[0]['title']:SITE_NAME);
                        $xml->writeElement("turbo:source", $item[0]['link']);
                        $xml->startElement("turbo:content");
                            $xml->writeCdata($item[0]['content']);
                        $xml->endElement();
                    $xml->endElement();
                    echo progressBar($i+1, count($content), 'Запись файла', 90);
                    sleep(1);
                endforeach;
            $xml->endElement();
        $xml->endElement();

        $outFile = fopen("output.xml", "w") or die("Unable to open file!");
        fwrite($outFile, $xml->outputMemory());
        fclose($outFile);

    } catch (\Exception $e){
        echo $e->getMessage();
    }
    return true;
}
//
//function strposX($haystack, $needle, $number){
//    if ($number == '1'){
//        return strpos($haystack, $needle);
//    } elseif ($number > '1'){
//        return strpos($haystack, $needle, strposX($haystack, $needle, $number - 1) + strlen($needle));
//    }
//    else {
//        return error_log('Error: Value for parameter $number is out of range');
//    }
//}

// START PROGRAM \\
$pattern = [
    "/\s?<div class=\"we__work.*?\"[^>]*?>.*?<\/div>\s?/si",
    "/\s?<div class=\"test-result.*?\"[^>]*?>.*?<\/div>\s?/si",
    "/\s?<div class=\"our-client.*?\"[^>]*?>.*?<\/div>\s?/si",
    "/\s?<img src=\"\/image\/cache\/catalog.*?\"[^>]*?>\s?/si",
    "/\s?<img src=\"\"[^>]*?>\s?/si",
    "/\s?<div class=\"item no-tabs.*?\"[^>]*?>.*?<\/div>\s?/si",
/*    "/\s?<div class=\"form-list\".*?\"[^>]*?>.*?<\/div>\s?/si",*/
/*    "/\s?<p style=\"margin-bottom: 3px !important;\".*?\"[^>]*?>.*?<\/p>\s?/si",*/
/*    "/\s?<div class=\"c-pop.*?\"[^>]*?>.*?<\/div>\s?/si",*/
/*    "/\s?<div class=\"pop-catalog.*?\"[^>]*?>.*?<\/div>\s?/si",*/
/*    "/\s?<div class=\"privacy.*?\"[^>]*?>.*?<\/div>\s?/si",*/
/*    "/\s?<div class=\"page-screen.*?\"[^>]*?>.*?<\/div>\s?/si",*/
/*    /*    "/\s?<div class=\"big-slider-block.*?\"[^>]*?>.*?<\/div>\s?/si",*/
/*    "'<div class=\"big-slider-block\"[^>]*?>.*?</div>'si",*/
/*    "'<a href=\"#.*?\"[^>]*?>.*?</a>'si",*/
/*    /*    "/\s?<h1[^>]*?>.*?<\/h1>\s?/si",*/
    '/\s?id=["][^"]*"\s?/i',
    '/\s?data-lazy-src=["][^"]*"\s?/i',
////    '/\s?href=["][^"][^#]*"\s?/i',
    '/\s?<script[^>]*?>.*?<\/script>\s?/si',
    '/\s?<form[^>]*?>.*?<\/form>\s?/si',
    '/\s?<noscript[^>]*?>.*?<\/noscript>\s?/si',
    '/\s?<footer[^>]*?>.*?<\/footer>\s?/si',
    '/\s?<style[^>]*?>.*?<\/style>\s?/si',
////    "![#](.*?)'!"
];
$replacement = [
////    "src",
//    "src",
//    DEFAULT_URL."wp-content"
];
$search = [
////    "data-lazy-src",
//    "srcset",
//    " /wp-content"
];

function readingFile($fileName)
{
    $fp = fopen($fileName, "r"); // Открываем файл в режиме чтения
    $mytext = array();
    if ($fp) {
        $index = 0;
        while (!feof($fp)) {
            $line = fgets($fp);
            $mytext[] = $line;
            $index++;
        }
    } else echo "Ошибка при открытии файла";
    fclose($fp);
    return $mytext;
}

$page = curlConnect();
$crawler = new Crawler($page, null, "https://labsiz.ru/");
function findContentHome($crawler){
    try {
        $crawler = $crawler->filter('.section_box_wr.mw')->each(function (Crawler $node){
            $result['link'] = "title";
            $result['content'] = $node->html();
            $result['content'] .= implode(" ", $node->siblings()->filter('.content-preview')->each(function (Crawler $n){
                return $n->html();
            }));
            $result['content'] = preg_replace("/\s?<img src=\"\/image\/cache\/catalog.*?\"[^>]*?>\s?/si", " ", $result['content']);
//            var_dump($result['content']);
            return $result;
        });
        return $crawler;
    } catch (\Exception $e) {
        echo "Skip node! ". $e->getMessage();
        return false;
    }
}
function findContentShop($crawler){
    try {
        $crawler = $crawler->filter('.cont_wr .abvv_container')->each(function (Crawler $node){
            $result['content'] = $node->html();
//            var_dump($node->html());
//            exit;
            $result['content'] = preg_replace("/\s?<img src=\"\/image\/cache\/catalog.*?\"[^>]*?>\s?/si", " ", $result['content']);
            $result['title'] = "Магазин средств защиты";
//            $result['content'] = implode(" ", $node->siblings()->filter('.content-preview')->each(function (Crawler $n){
//                return $n->html();
//            }));
//            var_dump($result); exit;
            return $result;
        });
//        dd($crawler);
        return $crawler;
    } catch (\Exception $e) {
        echo "Skip node! ". $e->getMessage();
        return false;
    }
}

$exc = ["https://labsiz.ru/","https://labsiz.ru/magazin-sredstv-zaschity"];
$crawler = readingFile('input.txt');
//dd($crawler);
foreach ($crawler as $key => $node){
    try {
        $node = str_replace("\n", "", $node);
        echo $node . PHP_EOL;
//        echo  $key. ") ".$node['link']." ". $node['text'] . PHP_EOL;
        $html = curlConnect($node);
        $crawler = new Crawler($html, null, DEFAULT_URL);
//dump($crawler);
        if ($node == $exc[0])
            $content[] = findContentHome($crawler);
        else if ($node == $exc[1])
            $content[] = findContentShop($crawler);
        else
            $content[] = findContent($crawler);
//        var_dump($content);
        $content[$key][0]['link'] = $node;
        // deleting
//        if($key == 1){
//            var_dump($content[$key]);exit;}
        $content[$key][0]['content'] = str_replace( $search, $replacement, preg_replace($pattern, " ", $content[$key][0]['content']));
    } catch (\Exception $e) {
        echo $e->getMessage();
    }
}
//var_dump($content);
if(putContent($content))
    echo PHP_EOL . "Success write!" . PHP_EOL ;
else
    echo "Error!" . PHP_EOL;