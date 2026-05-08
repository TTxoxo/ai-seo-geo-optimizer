<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class AI_SEO_GEO_Image_Alt_Manager {
	public function scan_post_images( $post_id ) {
		$post=get_post(absint($post_id)); if(!$post){return array();}
		$images=array();
		if ( preg_match_all( '/<img[^>]+>/i', (string) $post->post_content, $matches ) ) {
			foreach ( $matches[0] as $img_tag ) {
				preg_match('/src=["\']([^"\']+)/i',$img_tag,$m); $url=esc_url_raw($m[1]??'');
				$id=attachment_url_to_postid($url);
				$images[]=$this->build_image_row($id,$url,false,false,(string) wp_strip_all_tags($post->post_excerpt));
			}
		}
		$thumb=get_post_thumbnail_id($post_id); if($thumb){ $images[]=$this->build_image_row($thumb,wp_get_attachment_url($thumb),true,false,''); }
		if('product'===get_post_type($post_id)){ $gallery=get_post_meta($post_id,'_product_image_gallery',true); foreach(array_filter(array_map('absint',explode(',',$gallery))) as $gid){ $images[]=$this->build_image_row($gid,wp_get_attachment_url($gid),false,true,''); } }
		$uniq=array(); $out=array(); foreach($images as $it){$k=(string)$it['image_id'].'|'.$it['image_url']; if(!isset($uniq[$k])){$uniq[$k]=1;$out[]=$it;}}
		return $out;
	}
	private function build_image_row($id,$url,$featured,$gallery,$nearby){
		$file=basename((string)$url);
		return array('image_id'=>(string)absint($id),'image_url'=>esc_url_raw((string)$url),'filename'=>sanitize_file_name($file),'current_alt'=>$this->get_image_current_alt($id),'nearby_text'=>sanitize_text_field($nearby),'is_featured_image'=>(bool)$featured,'is_product_gallery_image'=>(bool)$gallery);
	}
	public function get_image_current_alt( $attachment_id ) { return sanitize_text_field((string)get_post_meta(absint($attachment_id),'_wp_attachment_image_alt',true)); }
	public function build_image_context_for_ai( $post_id ) { return $this->scan_post_images($post_id); }
	public function validate_alt_suggestion( $alt_text ) {
		$alt=trim((string)$alt_text); $errors=array(); if(''===$alt){$errors[]='empty';}
		if(mb_strlen($alt)>125){$errors[]='too_long';}
		if(preg_match('/\b(best|top|cheap|No\.1|100% guaranteed)\b/i',$alt)){$errors[]='forbidden_marketing';}
		if(preg_match('/\b(\w+)\s+\1\b/i',$alt)){$errors[]='repeated_keyword';}
		if(preg_match('/\b(ISO|CE|UL|price|delivery|country|USA|Germany|China)\b/i',$alt)){$errors[]='unrelated_claim';}
		if(strtoupper($alt)===$alt && ''!==$alt){$errors[]='all_caps';}
		if(substr_count($alt,',')>=3){$errors[]='keyword_list_style';}
		$risk=empty($errors)?'low':(in_array('forbidden_marketing',$errors,true)||in_array('unrelated_claim',$errors,true)?'high':'medium');
		return array('valid'=>empty($errors),'risk'=>$risk,'errors'=>$errors);
	}
	public function apply_image_alt( $attachment_id, $alt_text ) {
		$aid=absint($attachment_id); $alt=sanitize_text_field($alt_text); if($aid<=0||''===trim($alt)){return array('success'=>false,'message'=>__('Invalid alt.','ai-seo-geo-optimizer'));}
		$check=$this->validate_alt_suggestion($alt); if('high'===$check['risk']){return array('success'=>false,'message'=>__('High-risk alt cannot be directly applied.','ai-seo-geo-optimizer'));}
		$old=$this->get_image_current_alt($aid); update_post_meta($aid,'_wp_attachment_image_alt',$alt); return array('success'=>true,'old_alt'=>$old,'new_alt'=>$alt);
	}
}
