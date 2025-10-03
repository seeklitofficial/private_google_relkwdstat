<?php
namespace NaverBlogScraper;
class NbxScraper {
	private function curl(string $url, array $headers=[]): array {
		$ch=curl_init($url);
		$ua='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
		$base=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_ENCODING=>'',CURLOPT_HTTPHEADER=>array_merge([
			'User-Agent: '.$ua,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
			'Cache-Control: no-cache'
		],$headers)];
		curl_setopt_array($ch,$base);
		$body=curl_exec($ch);
		$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$err=curl_error($ch);
		curl_close($ch);
		return ['code'=>$code,'body'=>$body,'error'=>$err?:null];
	}
	public function fetchTopBlogs(string $keyword,int $count=10): array {
		$q=urlencode($keyword);
		// Prefer mobile SERP for simpler markup
		$url="https://m.search.naver.com/search.naver?where=m_blog&sm=mtb_jum&query={$q}";
		$res=$this->curl($url,[ 'Referer: https://m.search.naver.com/' ]);
		if(($res['code']!==200 || !$res['body'])){
			// Fallback to desktop blog/post tab
			$url2="https://search.naver.com/search.naver?where=post&sm=tab_jum&query={$q}";
			$res=$this->curl($url2,[ 'Referer: https://search.naver.com/' ]);
		}
		if($res['code']!==200 || !$res['body']) return ['error'=>'SERP fetch failed','status'=>$res['code']];
		$items=$this->parseSerp($res['body'],$count);
		if(!$items) return ['error'=>'No items'];
		// Enrich with basic snippet
		foreach($items as &$it){
			$detail=$this->curl($it['link'],['Referer: https://m.search.naver.com/']);
			if($detail['code']===200 && $detail['body']){
				$it['description']=$this->extractMeta($detail['body']);
			}
		}
		return ['items'=>$items];
	}
	private function parseSerp(string $html,int $limit): array {
		$items=[];
		$decoded=html_entity_decode($html, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
		// Patterns to capture links to blog posts on mobile/desktop results
		$patterns=[
			// Mobile SERP typical anchor
			'#<a[^>]+href=\"(https?:\\/\\/(?:m\\.)?blog\\.naver\\.com\\/[^\"]+)\"[^>]*?class=\"[^\"]*api_txt_lines[^\"]*\"[^>]*?>(.*?)<\\/a>#isu',
			// Any anchor to blog.naver.com
			'#<a[^>]+href=\"(https?:\\/\\/(?:m\\.)?blog\\.naver\\.com\\/[^\"]+)\"[^>]*?>(.*?)<\\/a>#isu',
			// data-url attribute
			'#data-url=\"(https?:\\/\\/(?:m\\.)?blog\\.naver\\.com\\/[^\"]+)\"#isu',
			// PostView direct links
			'#href=\"(https?:\\/\\/(?:m\\.)?blog\\.naver\\.com\\/PostView\\.naver\?[^\"]+)\"#isu'
		];
		foreach($patterns as $rx){
			if(preg_match_all($rx,$decoded,$m,PREG_SET_ORDER)){
				foreach($m as $mm){
					$linkRaw=$mm[1];
					$titleRaw=$mm[2]??'';
					$title=trim(strip_tags(html_entity_decode($titleRaw,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')));
					$link=html_entity_decode(str_replace('\\/','/',$linkRaw),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
					// Normalize to https and decode &amp;
					$link=preg_replace('#^http:#','https:',$link);
					$link=str_replace('&amp;','&',$link);
					$items[]=['title'=>trim($title)?:$link,'link'=>$link,'description'=>''];
					if(count($items)>=$limit) break 2;
				}
			}
		}
		// Deduplicate by link
		$seen=[]; $uniq=[];
		foreach($items as $it){ if(isset($seen[$it['link']])) continue; $seen[$it['link']]=1; $uniq[]=$it; }
		return array_slice($uniq,0,$limit);
	}
	private function extractMeta(string $html): string {
		if(preg_match('#<meta[^>]+property=\"og:description\"[^>]+content=\"([^\"]*)\"#i',$html,$m)) return html_entity_decode($m[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		if(preg_match('#<meta[^>]+name=\"description\"[^>]+content=\"([^\"]*)\"#i',$html,$m)) return html_entity_decode($m[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		return '';
	}
}
