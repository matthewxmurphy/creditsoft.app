<?php
/**
 * New Cookies/Privacy/Terms Elementor consumption only; no legal/compliance opinion.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com; recorded identity Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
if(!defined('WP_CLI')||!WP_CLI||!defined('CREDITSOFT_LEGAL_TEST_SITE')||CREDITSOFT_LEGAL_TEST_SITE!=='task118-legal-disposable'
 ||preg_match('/^cslegal118_[a-f0-9]{8}$/D',DB_NAME)!==1||wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1')throw new RuntimeException('Owned legal fixture required.');
$origin=getenv('CREDITSOFT_LEGAL_HTTP_ORIGIN');
if(!is_string($origin)||preg_match('~^http://cslegal118-[a-f0-9]{8}-wp$~D',$origin)!==1)throw new RuntimeException('Owned HTTP origin required.');
$checks=0;$requests=0;
$expect=static function(bool$ok,string$label)use(&$checks):void{++$checks;if(!$ok)throw new RuntimeException($label);};
$normal=static fn(string$t):string=>trim(preg_replace('/\s+/u',' ',strtr(html_entity_decode($t,ENT_QUOTES|ENT_HTML5,'UTF-8'),["\u{2018}"=>"'","\u{2019}"=>"'","\u{201c}"=>'"',"\u{201d}"=>'"'])));
$dom=static function(string$html):DOMXPath{$old=libxml_use_internal_errors(true);try{$d=new DOMDocument();$d->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET);return new DOMXPath($d);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}};
$values=static function(DOMXPath$xp,string$query,?DOMNode$root=null)use($normal):array{$out=[];foreach($xp->query($query,$root)as$node)$out[]=$normal($node->textContent);return$out;};
$hrefs=static function(DOMXPath$xp,?DOMNode$root=null):array{$out=[];foreach($xp->query('.//a[@href]',$root)as$node)$out[]=$node->getAttribute('href');return$out;};
$get=static function(string$path)use($origin,&$requests):array{++$requests;$r=wp_remote_get($origin.$path,['redirection'=>0,'timeout'=>25,'headers'=>['Host'=>'127.0.0.1:19140']]);if(is_wp_error($r))throw new RuntimeException('Owned legal HTTP failed.');return[(int)wp_remote_retrieve_response_code($r),wp_remote_retrieve_body($r)];};
$admins=get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);if(!$admins)throw new RuntimeException('Synthetic core administrator required.');wp_set_current_user((int)$admins[0]);
$sentinel=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>'legal-sentinel','post_title'=>'Owned unrelated page','post_content'=>'Preserve this existing fixture content.']);
update_option('show_on_front','page');update_option('page_on_front',$sentinel);$beforeSentinel=get_post($sentinel)->post_content;
$pages=static function():array{global$wpdb;return$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='page' ORDER BY ID");};
try{
 foreach(['cookies'=>'Cookie Policy','privacy'=>'Privacy Policy','terms'=>'Terms of Service']as$slug=>$heading){
  $source=file_get_contents('/tmp/legal-source/'.$slug.'.astro');if(!preg_match('~<main\b[^>]*>(.*?)</main>~s',$source,$m))throw new RuntimeException('Known static source main missing.');
  $sourceXp=$dom($m[0]);$sourceRoot=$sourceXp->query('//main')->item(0);
  $beforePages=$pages();$data=creditsoft_helium_marketing_template($slug);
  $expect($pages()===$beforePages&&get_option('page_on_front')==$sentinel,'Reading this new legal export creates/selects no page.');
  $oldHome=home_url();$base=static fn(string$url,string$path):string=>'http://[2001:db8::118]/~legal'.($path===''?'':'/'.ltrim($path,'/'));
  add_filter('home_url',$base,10,2);try{$prepared=creditsoft_helium_marketing_template($slug);}finally{remove_filter('home_url',$base,10);}
  $expect($prepared===$data&&home_url()===$oldHome,'Legal mailto/no-link bodies remain unchanged under a configured preview base.');
  $page=\Elementor\Plugin::instance()->documents->create('wp-page',['post_title'=>$data['title'],'post_status'=>'publish','post_name'=>$slug]);if(is_wp_error($page))throw new RuntimeException('Owned legal document creation failed.');
  $settings=array_merge($data['page_settings']??[],['template'=>'templates/creditsoft-home.php']);
  $expect($page->save(['elements'=>$data['content'],'settings'=>$settings]),'Explicit native Elementor save imports this chosen legal page.');
  update_post_meta($page->get_main_id(),'_wp_page_template','templates/creditsoft-home.php');
  $path=wp_parse_url(get_permalink($page->get_main_id()),PHP_URL_PATH);$expect($path==='/'.$slug.'/','The new page retains its source public path.');
  [$status,$html]=$get($path);$xp=$dom($html);$root=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," creditsoft-legal ")]')->item(0);
  $expect($status===200&&$root!==null&&str_contains($html,'id="g-page-surround"'),'New legal page renders in the actual Gantry/Elementor stack.');
  $expect($normal($root->textContent)===$normal($sourceRoot->textContent),'All rendered legal wording/date/order matches the actual published source, allowing normal typography only.');
  $expect($values($xp,'.//h1',$root)===[$heading]&&$values($xp,'.//h2',$root)===$values($sourceXp,'.//h2',$sourceRoot),'Source heading hierarchy and order are preserved.');
  $expect($hrefs($xp,$root)===$hrefs($sourceXp,$sourceRoot),'Source mailto links are preserved without navigation.');
  $expect($xp->query('.//strong',$root)->length===$sourceXp->query('.//strong',$sourceRoot)->length,'Source emphasis is preserved.');
  $edited=$data['content'];$changed=false;$edit=static function(array&$nodes)use(&$edit,&$changed,$heading):void{foreach($nodes as&$node){if(($node['widgetType']??'')==='heading'&&($node['settings']['title']??'')===$heading){$node['settings']['title']='Temporary local legal edit';$changed=true;}if(isset($node['elements'])&&is_array($node['elements']))$edit($node['elements']);}};$edit($edited);
  $expect($changed&&$page->save(['elements'=>$edited,'settings'=>$settings]),'This imported page remains explicitly editable.');
  creditsoft_helium_marketing_template($slug);[$status,$editedHtml]=$get($path);
  $expect($status===200&&str_contains($editedHtml,'Temporary local legal edit'),'Preparing source again does not overwrite a saved native edit.');
  $expect($page->save(['elements'=>$data['content'],'settings'=>$settings]),'Original legal source is explicitly restored.');
  [$status,$restored]=$get($path);$restoredXp=$dom($restored);$restoredRoot=$restoredXp->query('//*[contains(concat(" ",normalize-space(@class)," ")," creditsoft-legal ")]')->item(0);
  $expect($status===200&&$restoredRoot&&$normal($restoredRoot->textContent)===$normal($sourceRoot->textContent),'Restored actual legal page retains all source copy.');
 }
 $expect(get_option('page_on_front')==$sentinel&&get_post($sentinel)->post_content===$beforeSentinel,'Unrelated existing page/frontpage selection remains preserved.');
 echo 'PASS: '.$checks.' new legal-page checks; '.$requests.' HTTP requests; WP '.get_bloginfo('version').'; PHP '.PHP_VERSION.'; Elementor '.ELEMENTOR_VERSION.'. Copy/render acceptance only, not legal or consent verification.'.PHP_EOL;
}catch(Throwable$e){echo 'FAIL after '.$checks.' legal checks; '.$requests.' HTTP requests.'.PHP_EOL;throw$e;}
