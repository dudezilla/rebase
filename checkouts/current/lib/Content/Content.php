<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Content")){
	class Content{
	
	private $content;
	private $description;
			
	private function __construct(){
	}	
		
	public function get_content(){
		return $this->content;
	}
				
	public function get_description(){
		return $this->description;
	}
	
	public function __toString(){
		return $this->content ?? '';   // bug #60: never return null (string return type)
	}
	
	public function set_content($content){
		$this->content = $content;
	}
	
	public function set_description($description){
		$this->description = $description;
	}
	
	public static function sql_assoc_array($array_data){
		$content = new Content();		
		$content->set_content( $array_data[ ContentDAO::FIELD_NAME_CONTENT] );
		$content->set_description( $array_data[ ContentDAO::FIELD_NAME_DESCRIPTION] );
		return $content;
	}
	
	}
}
?>
