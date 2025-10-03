<?php
namespace NA; 
class Client{
	private string $id; private string $secret;
	public function __construct(){
		$this->id = getenv('QUANTITY_NAVER_CLIENT_ID') ?: ($_ENV['QUANTITY_NAVER_CLIENT_ID'] ?? '');
		$this->secret = getenv('QUANTITY_NAVER_CLIENT_SECRET') ?: ($_ENV['QUANTITY_NAVER_CLIENT_SECRET'] ?? '');
		if(($this->id==='' || $this->secret==='') && is_file(__DIR__.'/../.env')){
			foreach(file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
				if($line==='' || $line[0]==='#') continue; $p=strpos($line,'='); if($p===false) continue; $k=substr($line,0,$p); $v=trim(substr($line,$p+1),"\"' "); $_ENV[$k]=$v; if(function_exists('putenv')) @putenv($k.'='.$v);
			}
			$this->id = getenv('QUANTITY_NAVER_CLIENT_ID') ?: ($_ENV['QUANTITY_NAVER_CLIENT_ID'] ?? '');
			$this->secret = getenv('QUANTITY_NAVER_CLIENT_SECRET') ?: ($_ENV['QUANTITY_NAVER_CLIENT_SECRET'] ?? '');
		}
	}
	public function searchBlogs(string $kw,int $display=5): array{
		$q=http_build_query(['query'=>$kw,'display'=>$display,'start'=>1,'sort'=>'sim']);
		$ch=curl_init('https://openapi.naver.com/v1/search/blog.json?'.$q);
		curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>[
			'X-Naver-Client-Id: '.$this->id,
			'X-Naver-Client-Secret: '.$this->secret,
			'User-Agent: NA/1.0'
		]]);
		$resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); if($resp===false){$err=curl_error($ch);} curl_close($ch);
		if(isset($err)) return ['error'=>'curl','detail'=>$err];
		if($code!==200) return ['error'=>'http','status'=>$code,'body'=>$resp];
		return json_decode($resp,true) ?: ['error'=>'json'];
	}
}
class Analyzer{
	public function analyze(string $kw,array $items): array{
		$norm=mb_strtolower(trim($kw),'UTF-8'); $posts=[]; $totalChars=0; $paras=0; $counts=[];
		foreach(array_slice($items,0,5) as $it){
			$title=strip_tags($it['title']??''); $desc=strip_tags($it['description']??''); $url=$it['link']??'';
			$text=$title."\n\n".$desc; $chars=mb_strlen($text,'UTF-8'); $p=max(1,substr_count($text,"\n\n")+1);
			$c=$this->occurs($text,$norm); $dens=$chars? round(($c/$chars)*100*40,2):0;
			$posts[]=['title'=>$title,'url'=>$url,'keywordCount'=>$c,'keywordDensityPct'=>$dens];
			$totalChars+=$chars; $paras+=$p; $counts[]=$c;
		}
		$doc=count($posts); $avgChars=$doc? intdiv($totalChars,$doc):0; $avgParas=$doc? round($paras/$doc,1):0; $avgD=$doc? round(array_sum($counts)/max(1,$avgChars*$doc)*100*40,2):0;
		return ['summary'=>['totalDocs'=>$doc,'avgChars'=>$avgChars,'avgParagraphs'=>$avgParas,'avgKeywordDensityPct'=>$avgD],'topPosts'=>$posts];
	}
	private function occurs(string $text,string $kw): int{ if($kw==='') return 0; return preg_match_all('/'.preg_quote($kw,'/').'/iu',$text); }
}
