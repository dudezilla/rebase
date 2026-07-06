<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if (!interface_exists("FormElementInterface")){
	interface FormElementInterface{
		public function __toString();			//Each form element must be converted into html.
		public function setId($arg);		//Element ID. Must be unique, or will destroy existing data.
		public function getId();
//		public function returnValue();	
		public function setSelectionComment($arg);
		public function setRequired($bool);	
		public function failsValidation();
	}
}