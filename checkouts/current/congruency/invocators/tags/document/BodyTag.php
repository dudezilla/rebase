<?php

/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if (!class_exists("BodyTag")) {
	class BodyTag implements Tag_Interface {
		private $arguments;
		private $body;

		public function __construct($arguments) {
			$this->arguments = $arguments;
			$document = PersistentObjectManager :: getData('WORKING_PAGE');
			$this->body = $document->get_content_outline();
		}

		public function get_document() {
			return "<<<ContentTag(" . $this->body . ")>>>";
		}
	}
}
?>
