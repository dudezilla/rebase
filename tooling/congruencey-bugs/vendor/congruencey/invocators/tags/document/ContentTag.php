<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("ContentTag")){
	class ContentTag implements Tag_Interface{
		private $arguments;
		private $contentID;
		private $content;
		
		public function __construct($arguments){
			$this->arguments = $arguments;
			$this->contentID = $arguments->top();
			$this->lookup_content();
		}
		
		public function get_document(){
			return $this->content->__toString();
		}
		
		private function lookup_content(){
			$contentDAO = new ContentDAO();
			$contentData = $contentDAO->lookupContent($this->contentID);
			$this->content = Content::sql_assoc_array($contentData);
		}
		
	}		
}
?>
