<?php
namespace NaverBlogAnalyzer;
class NbaClient{
	private string $clientId;
	private string $clientSecret;
	public function __construct(){
		$this->clientId= getenv('QUANTITY_NAVER_CLIENT_ID') ?: ($_ENV['QUANTITY_NAVER_CLIENT_ID'] ?? '');
		$this->clientSecret= getenv('QUANTITY_NAVER_CLIENT_SECRET') ?: ($_ENV['QUANTITY_NAVER_CLIENT_SECRET'] ?? '');
	}
	public function searchBlogs(string $keyword,int $display=10): array {
		$qs=http_build_query(['query'=>$keyword,'display'=>$display,'start'=>1,'sort'=>'sim']);
		$url='https://openapi.naver.com/v1/search/blog.json?'.$qs;
		$ch=curl_init($url);
		curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>[
			'X-Naver-Client-Id: '.$this->clientId,
			'X-Naver-Client-Secret: '.$this->clientSecret,
			'User-Agent: NaverBlogAnalyzer/1.0'
		]]);
		$resp=curl_exec($ch);
		$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
		if($resp===false){return ['error'=>'cURL error: '.curl_error($ch)];}
		curl_close($ch);
		if($code!==200){return ['error'=>'HTTP '.$code, 'body'=>$resp];}
		$data=json_decode($resp,true);
		return $data?:['error'=>'Invalid JSON'];
	}
}
class NbaAnalyzer{
	public function analyze(string $keyword,array $items): array {
		$normalized=strtolower(trim($keyword));
		$topPosts=[]; $totalChars=0; $totalParagraphs=0; $keywordCounts=[]; $positions=[]; $titlePatterns=[]; $styleExamples=[]; $coWordCounts=[];
		foreach(array_slice($items,0,10) as $it){
			$title=strip_tags($it['title']??'');
			$desc=strip_tags($it['description']??'');
			$link=$it['link']??'';
			$text=$title."\n\n".$desc;
			$chars=mb_strlen($text,'UTF-8');
			$paras=max(1,substr_count($text,"\n\n")+1);
			$cnt=$this->countOccurrences($text,$normalized);
			$dens=$chars>0? round(($cnt/max(1,$chars))*100*40,2):0;
			$pos=$this->findPositions($text,$normalized);
			$tp=$this->classifyTitle($title);
			$style=$this->inferStyle($desc);
			$this->accumulateCoWords($desc,$coWordCounts,$normalized);
			$topPosts[]=['title'=>$title,'url'=>$link,'keywordCount'=>$cnt,'keywordDensityPct'=>$dens];
			$totalChars+=$chars; $totalParagraphs+=$paras; $keywordCounts[]=$cnt; $positions=array_merge($positions,$pos); $titlePatterns[]=$tp; $styleExamples[]=$style;
		}
		$avgChars= count($topPosts)? intdiv($totalChars,count($topPosts)) : 0;
		$avgParagraphs= count($topPosts)? round($totalParagraphs/count($topPosts),1):0;
		$avgDensity= count($keywordCounts)? round(array_sum($keywordCounts)/max(1,$avgChars*count($topPosts))*100*40,2):0;
		return [
			'summary'=>[
				'totalDocs'=>count($topPosts),
				'avgChars'=>$avgChars,
				'avgParagraphs'=>$avgParagraphs,
				'avgKeywordDensityPct'=>$avgDensity,
				'topTitleTypes'=>$this->topN($titlePatterns,3),
			],
			'topPosts'=>$topPosts,
			'keywordAnalysis'=>[
				'totalCount'=>array_sum($keywordCounts),
				'avgDensityPct'=>$avgDensity,
				'positions'=>array_slice($positions,0,10)
			],
			'titleStyle'=>[
				'topTitlePatterns'=>$this->topN($titlePatterns,5),
				'styleExamples'=>array_slice(array_values(array_filter($styleExamples)),0,5)
			],
			'coKeywords'=>$this->topCoWords($coWordCounts,20)
		];
	}
	private function countOccurrences(string $text,string $kw): int {
		if($kw==='') return 0;
		return preg_match_all('/'.preg_quote($kw,'/').'/iu',$text);
	}
	private function findPositions(string $text,string $kw): array {
		$pos=[]; if($kw==='') return $pos;
		if(mb_stripos($text,$kw,0,'UTF-8')!==false){$pos[]='본문 초반';}
		if(mb_stripos(mb_substr($text, mb_strlen($text)-min(120, mb_strlen($text)), null,'UTF-8'),$kw,0,'UTF-8')!==false){$pos[]='본문 하단';}
		if(mb_stripos($text,$kw,0,'UTF-8')!==false && mb_stripos($text,$kw,0,'UTF-8')<30){$pos[]='제목/리드';}
		return array_values(array_unique($pos));
	}
	private function classifyTitle(string $title): string {
		$title=trim($title);
		if(preg_match('/\b(\d{1,2})\b/u',$title)) return '숫자형(리스트)';
		if(preg_match('/[!?]/u',$title)) return '감탄/의문형';
		if(mb_strlen($title,'UTF-8')<=16) return '간결형';
		return '설명형';
	}
	private function inferStyle(string $desc): string {
		if(preg_match('/\bTip\b|꿀팁|방법/u',$desc)) return '가이드형';
		if(preg_match('/후기|리뷰|경험/u',$desc)) return '후기형';
		if(preg_match('/비교|대비/u',$desc)) return '비교형';
		return '';
	}
	private function accumulateCoWords(string $text,array &$bag,string $kw): void {
		$clean=mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u',' ',$text),'UTF-8');
		$tokens=preg_split('/\s+/u',$clean,-1,PREG_SPLIT_NO_EMPTY);
		$stop=['그리고','그','이','저','는','은','이','가','을','를','에','의','와','과','too','the','a','an','of','to','in','on'];
		foreach($tokens as $t){
			if(mb_strlen($t,'UTF-8')<2) continue;
			if(in_array($t,$stop,true)) continue;
			if($t===$kw) continue;
			$bag[$t]=($bag[$t]??0)+1;
		}
	}
	private function topN(array $arr,int $n): array {
		$counts=[]; foreach($arr as $a){$counts[$a]=($counts[$a]??0)+1;}
		arsort($counts); return array_slice(array_keys($counts),0,$n);
	}
	private function topCoWords(array $bag,int $n): array {
		arsort($bag); $out=[]; foreach(array_slice($bag,0,$n,true) as $k=>$v){$out[]=['keyword'=>$k,'count'=>$v];} return $out;
	}
}
