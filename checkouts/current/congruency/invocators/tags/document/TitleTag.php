<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("TitleTag")){
	class TitleTag implements Tag_Interface{
		private $arguments;
		private $title;
		
		public function __construct($arguments){
			$this->arguments = $arguments;
			$document = PersistentObjectManager::getData('WORKING_PAGE');
			$this->title = $document->getTitle();
		}
		
		public function get_document(){
			return "<title>" . $this->title . "</title>";
		}
	}		
}
?>
