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
	private function curl(string $url): array{
		$ch=curl_init($url);
		$ua='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
		curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>[
			'User-Agent: '.$ua,'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8']]);
		$body=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
		return ['code'=>$code,'body'=>$body,'err'=>$err];
	}
	public function fetchPostContent(string $url): string{
		$r=$this->curl($url); if($r['code']!==200 || !$r['body']) return '';
		$html=$r['body'];
		// Try to resolve canonical/og:url if present
		if(preg_match('#<link[^>]+rel=\"canonical\"[^>]+href=\"([^\"]+)\"#i',$html,$m) || preg_match('#<meta[^>]+property=\"og:url\"[^>]+content=\"([^\"]+)\"#i',$html,$m)){
			$canon=$m[1]; if(is_string($canon) && stripos($canon,'blog.naver.com')!==false){ $r2=$this->curl($canon); if($r2['code']===200 && $r2['body']) $html=$r2['body']; }
		}
		// Follow iframe to PostView if exists
		if(preg_match('#<iframe[^>]+src=\"([^\"]+PostView[^\"]*)\"#i',$html,$m) || preg_match('#<iframe[^>]+src=\"([^\"]*blog\.naver\.com[^\"]*)\"#i',$html,$m)){
			$src=html_entity_decode($m[1], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
			if(str_starts_with($src,'/')){ $p=parse_url($url); $src=($p['scheme']??'https').'://'.($p['host']??'blog.naver.com').$src; }
			$r3=$this->curl($src); if($r3['code']===200 && $r3['body']) {
				$html=$r3['body'];
				// iframe을 따라간 경우 전체 HTML을 반환 (이미지/링크 카운팅을 위해)
				return $html;
			}
		}
		$selectors=[
			'#<div[^>]+id=\"postViewArea\"[^>]*>([\s\S]*?)<\\/div>#iu',
			'#<div[^>]+class=\"[^\"]*se-main-container[^\"]*\"[^>]*>([\s\S]*?)<\\/div>#iu',
			'#<div[^>]+class=\"[^\"]*se_component_wrap[^\"]*\"[^>]*>([\s\S]*?)<\\/div>#iu'
		];
		foreach($selectors as $rx){
			if(preg_match($rx,$html,$m)){
				$chunk=$m[1];
				$chunk=preg_replace('#<script[\s\S]*?<\\/script>#iu',' ',$chunk);
				$chunk=preg_replace('#<style[\s\S]*?<\\/style>#iu',' ',$chunk);
				$text=strip_tags($chunk);
				$text=html_entity_decode($text, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
				$text=preg_replace('/[\x00-\x1F\x7F]+/u',' ',$text);
				$text=preg_replace('/\s+/u',' ',$text);
				return trim($text);
			}
		}
		// If no specific selectors found, return the full HTML for image/link counting
		return $html;
	}
}
class Analyzer{
	public function analyze(string $kw,array $items, callable $contentFetcher=null): array{
		$norm=mb_strtolower(trim($kw),'UTF-8'); $posts=[]; $totalChars=0; $paras=0; $counts=[];
		$sentences=[]; $avgSentenceLens=[]; $images=[]; $numbers=[]; $excls=[]; $headings=[]; $links=[];
		$coWordBag=[]; $titlePatterns=[];
		foreach(array_slice($items,0,5) as $it){
			$title=strip_tags($it['title']??''); $desc=strip_tags($it['description']??''); $url=$it['link']??'';
			$body=''; if($contentFetcher){ $body = trim((string)$contentFetcher($url)); }
			$combined = trim($title."\n\n".$desc.( $body ? "\n\n".$body : '' ));
			$chars=mb_strlen($combined,'UTF-8'); $p=max(1,substr_count($combined,"\n\n")+1);
			$c=$this->occurs($combined,$norm); $dens=$chars? round(($c/$chars)*100*40,2):0;
			list($sCnt,$avgSL)=$this->sentenceStats($combined);
			$img=$this->countImages($combined); $num=preg_match_all('/\d+/u',$combined); $exc=substr_count($combined,'!'); $hd=0; $ln=$this->countLinks($combined);
			list($firstPos,$section)=$this->firstOccurrenceInfo($combined,$norm, mb_strlen($title)+2+mb_strlen($desc));
			list($densestSentence,$densestPct)=$this->densestSentence($combined,$norm);
			$this->accumulateCoWords($combined,$coWordBag,$norm);
			$titlePatterns[]=$this->classifyTitleExtended($title);
			$posts[]=[
				'title'=>$title,'url'=>$url,'keywordCount'=>$c,'keywordDensityPct'=>$dens,
				'charCount'=>$chars,
				'firstOccurrence'=>$firstPos,'firstOccurrenceSection'=>$section,
				'densestSentence'=>$densestSentence,'densestSentenceDensityPct'=>$densestPct,
				'contentPreview'=>($body? mb_substr($body,0,120,'UTF-8').'...' : ''),
				'imageCount'=>$img,'linkCount'=>$ln
			];
			$totalChars+=$chars; $paras+=$p; $counts[]=$c; $sentences[]=$sCnt; $avgSentenceLens[]=$avgSL; $images[]=$img; $numbers[]=$num; $excls[]=$exc; $links[]=$ln; $headings[]=$hd;
		}
		$doc=count($posts); $avgChars=$doc? intdiv($totalChars,$doc):0; $avgParas=$doc? round($paras/$doc,1):0; $avgD=$doc? round(array_sum($counts)/max(1,$avgChars*$doc)*100*40,2):0;
		$summary=[
			'totalDocs'=>$doc,'avgChars'=>$avgChars,'avgParagraphs'=>$avgParas,'avgKeywordDensityPct'=>$avgD,
			'avgSentencesPerPost'=>$this->avg($sentences),'avgSentenceLenChars'=>$this->avg($avgSentenceLens),
			'avgImagesPerPost'=>$this->avg($images),'avgNumbersPerPost'=>$this->avg($numbers),'avgExclamationsPerPost'=>$this->avg($excls),
			'avgLinksPerPost'=>$this->avg($links)
		];
		return [
			'summary'=>$summary,
			'titleSummary'=>['topTitlePatterns'=>$this->topN($titlePatterns,5)],
			'coKeywords'=>$this->topCoWords($coWordBag,20),
			'topPosts'=>$posts
		];
	}
	private function occurs(string $text,string $kw): int{ if($kw==='') return 0; return preg_match_all('/'.preg_quote($kw,'/').'/iu',$text); }
	private function sentenceStats(string $text): array{ $clean=preg_replace('/[\r\n]+/',' ',$text); $parts=preg_split('/(?<=[.!?\x{3002}\x{FF01}\x{FF1F}])/u',$clean,-1,PREG_SPLIT_NO_EMPTY); $parts=array_map('trim',$parts); $parts=array_values(array_filter($parts,fn($s)=>$s!=='')); if(!$parts){return [0,0];} $sum=0; foreach($parts as $p){$sum+=mb_strlen($p,'UTF-8');} return [count($parts), round($sum/max(1,count($parts)),1)]; }
	private function avg(array $arr): float{ if(!$arr) return 0.0; return round(array_sum($arr)/max(1,count($arr)),2); }
	private function firstOccurrenceInfo(string $text,string $kw,int $afterTitleDescLen): array{
		$pos = ($kw==='')? false : mb_stripos($text,$kw,0,'UTF-8');
		if($pos===false) return ['-', '-'];
		$section = $pos < max(0,$afterTitleDescLen)? '제목/요약' : ($pos < $afterTitleDescLen + 200 ? '본문 초반' : '본문 중·후반');
		return [$pos, $section];
	}
	private function densestSentence(string $text,string $kw): array{
		$clean=preg_replace('/[\r\n]+/',' ',$text); $parts=preg_split('/(?<=[.!?\x{3002}\x{FF01}\x{FF1F}])/u',$clean,-1,PREG_SPLIT_NO_EMPTY);
		$best=['',0.0]; if(!$parts) return $best; foreach($parts as $s){ $len=mb_strlen($s,'UTF-8'); if($len===0) continue; $c=$this->occurs($s,$kw); $d= $c? ($c/$len*100*40):0; if($d>$best[1]) $best=[$s, round($d,2)]; } return $best;
	}
	private function accumulateCoWords(string $text,array &$bag,string $kw): void{
		$clean=mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u',' ',$text),'UTF-8');
		$tokens=preg_split('/\s+/u',$clean,-1,PREG_SPLIT_NO_EMPTY);
		$stop=['그리고','그','이','저','는','은','이','가','을','를','에','의','와','과','too','the','a','an','of','to','in','on'];
		foreach($tokens as $t){ if(mb_strlen($t,'UTF-8')<2) continue; if(in_array($t,$stop,true)) continue; if($t===$kw) continue; $bag[$t]=($bag[$t]??0)+1; }
	}
	private function topN(array $arr,int $n): array{ $counts=[]; foreach($arr as $a){ $counts[$a]=($counts[$a]??0)+1; } arsort($counts); return array_slice(array_keys($counts),0,$n); }
	private function topCoWords(array $bag,int $n): array{ arsort($bag); $out=[]; foreach(array_slice($bag,0,$n,true) as $k=>$v){ $out[]=['keyword'=>$k,'count'=>$v]; } return $out; }
	private function countImages(string $text): int{
		// HTML img 태그 찾기
		$imgTags = preg_match_all('/<img[^>]*>/i', $text);
		// 이미지 URL 패턴 찾기 (jpg, jpeg, png, gif, webp 등)
		$imgUrls = preg_match_all('/https?:\/\/[^\s<>"\']+\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?[^\s<>"\']*)?/i', $text);
		// 이미지 관련 단어 찾기 (추가 보너스)
		$imgWords = preg_match_all('/\b(이미지|사진|그림|img|image|photo|picture)\b/u', $text);
		return $imgTags + $imgUrls + $imgWords;
	}
	
	private function countLinks(string $text): int{
		// HTML a 태그 찾기
		$linkTags = preg_match_all('/<a[^>]*href[^>]*>/i', $text);
		// URL 패턴 찾기
		$urls = preg_match_all('/https?:\/\/[^\s<>"\']+/i', $text);
		return $linkTags + $urls;
	}
	
	private function classifyTitleExtended(string $title): string{
		$t=trim($title);
		if(preg_match('/\b(\d{1,2})\b/u',$t)) return '숫자형(리스트)';
		if(preg_match('/[!?]/u',$t)) return '감탄/의문형';
		if(preg_match('/(방법|가이드|Tip|TIP)/u',$t)) return '가이드형';
		if(preg_match('/(후기|리뷰|경험)/u',$t)) return '후기형';
		if(preg_match('/(비교|vs|대비)/iu',$t)) return '비교형';
		if(preg_match('/[\(\[][^\)\]]+[\)\]]/u',$t)) return '괄호형';
		if(mb_strlen($t,'UTF-8')<=16) return '간결형';
		return '설명형';
	}
}
