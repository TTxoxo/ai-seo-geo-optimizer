<?php
if(!defined('ABSPATH')){exit;}
class AI_SEO_GEO_Schema_Review_Manager {
	public function build_schema_context( $post_id ) {
		$post=get_post(absint($post_id)); if(!$post){return array();}
		return array('post_title'=>get_the_title($post_id),'post_excerpt'=>$post->post_excerpt,'post_content'=>wp_strip_all_tags($post->post_content),'featured_image'=>get_the_post_thumbnail_url($post_id,'full'),'author'=>get_the_author_meta('display_name',$post->post_author),'date_published'=>get_post_time('c',true,$post),'date_modified'=>get_post_modified_time('c',true,$post));
	}
	public function validate_schema_suggestion( $schema ) {
		$schema=is_array($schema)?$schema:array(); $type=sanitize_text_field($schema['@type']??''); $fields=is_array($schema['fields']??null)?$schema['fields']:array(); $errors=array();
		if(!in_array($type,array('Product','Article','FAQPage','BreadcrumbList','Organization'),true)){$errors[]='unsupported_type';}
		if('Product'===$type){ foreach(array('aggregateRating','review','certification') as $f){ if(isset($fields[$f])){$errors[]='forbidden_'.$f;} } }
		if('Organization'===$type){ $org=get_option('ai_seo_geo_schema_org_settings',array()); $fields=array_intersect_key($fields,array_flip(array('name','url','logo','contactUrl','sameAs','country'))); if(!empty($org)){$fields=array_merge($fields,array_filter(array('name'=>$org['name']??'','url'=>$org['url']??'','logo'=>$org['logo']??'','contactUrl'=>$org['contact_url']??'','sameAs'=>$org['same_as']??'','country'=>$org['country']??'')));}}
		$schema['fields']=$fields; $schema['schema_risk']=empty($errors)?'low':'high'; $schema['needs_human_review']=array_values(array_unique(array_merge((array)($schema['needs_human_review']??array()),$errors)));
		return array('valid'=>empty($errors),'schema'=>$schema,'errors'=>$errors);
	}
	public function save_schema_suggestion( $post_id, $schema ) { $v=$this->validate_schema_suggestion($schema); update_post_meta($post_id,'_ai_seo_geo_schema_suggestion',wp_json_encode($v['schema'])); update_post_meta($post_id,'_ai_seo_geo_schema_status','suggested'); return $v; }
	public function get_schema_suggestion( $post_id ) { $raw=get_post_meta($post_id,'_ai_seo_geo_schema_suggestion',true); return json_decode((string)$raw,true)?:array(); }
	public function check_visible_content_match( $schema, $post_content ) { $txt=strtolower(wp_strip_all_tags((string)$post_content)); $checks=array(); foreach((array)($schema['fields']??array()) as $k=>$v){ if(is_scalar($v)&&''!==trim((string)$v)){ $checks[$k]=false!==strpos($txt,strtolower((string)$v)); } } return $checks; }
}
