<?php
/*
 * Created on Feb 11, 2007
 *
 * 
Congruency The web management system.
Copyright (C) 2006 Steven Peterson

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

<<<Contact Info>>>
Steven Peterson,2234 4th Ave NW, Calgary, AB T2N 0N7 Canada. steven.peterson@shaw.ca 
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
		return $this->content;
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
