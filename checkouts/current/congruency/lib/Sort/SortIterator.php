<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
Title: Data Structures and Algorithms in Java
Author: Adam Drozdak
Copyright: 2001 Brooks/Cole

Similar to usort

*/
if(!class_exists("SortIterator")){
	define("MERGE_BASE_SIZE","1");
	class SortIterator{
		private $references;		
		private $unsorted;
		private $size;
		private $position;

		public function __construct(){
			$this->unsorted = NULL;
			$this->references = NULL;
			$this->size = 0;
			$this->position = 0;
		}
		
		public function setUnsorted($unsorted){
			$this->unsorted = $unsorted;
			$this->size = count($unsorted);
			if($this->size > 0){
				$this->position = 1;
				$this->mergeSort();
			}
		}

		public function getSorted(){
			return $this->references;
		}
		
		/**************************************************************\
		//These functions may not need to test if elements exist before 
		//pulling a value.
		/**************************************************************/
		public function current(){
			$currentValue = NULL;
			if( $this->size > 0){
				$currentValue = current($this->references);
			}
			return $currentValue;
		}

		public function reset(){
			$firstValue = NULL;
			if( $this->size > 0){
				$firstValue = reset($this->references);
				$this->position = 1;
			}
			return $firstValue;
		}

		public function next(){
			$nextValue = NULL;
			if( $this->size > 0){
				$nextValue = next($this->references);
				$this->position++;
			}
			return $nextValue;
		}
		
		public function finished(){
			return ($this->size < $this->position) || ($this->size < 1);	
		}

		private function compare($elOne,$elTwo){
			return $elOne->getCompareValue() < $elTwo->getCompareValue();
		}

		private function splitArray($arraySize){
			$mergeIndecies = (2 * $arraySize);
			$index = 0;
			while( $index < $this->size ){
				$this->merge($arraySize,$index);
				$index += $mergeIndecies;
			}
		}		
				
		private  function merge($numberOfEls,$lowerBound1){
			$lowerBound2 = $lowerBound1 + $numberOfEls;
			if($lowerBound2 < $this->size){
				$upperBound1 = $lowerBound1 + $numberOfEls -1;
				$upperBound2 = $upperBound1 + $numberOfEls;
				if($upperBound2 >= $this->size){
					$upperBound2 = $this->size -1;
				}
				$index1 = $lowerBound1;
				$index2 = $lowerBound2;
				$tempIndex = 0;
				while( ($index1 <= $upperBound1) && ($index2 <= $upperBound2) ){
					if($this->compare($this->references[$index1],$this->references[$index2])){
						$temp[$tempIndex++] = $this->references[$index1++];
					}else{
						$temp[$tempIndex++] = $this->references[$index2++];
					}
				}
				if(($index1 >= $upperBound1) && ($index2 <= $upperBound2 )){
					while($index2 <= $upperBound2){
						$temp[$tempIndex++] = $this->references[$index2++];
					}	
				}elseif(($index1 <= $upperBound1) && ($index2 >= $upperBound2 )){
					while($index1 <= $upperBound1){
						$temp[$tempIndex++] = $this->references[$index1++];
					}
				}
				$copyTempIndex = $lowerBound1;
				foreach($temp as $sorted){
					$this->references[$copyTempIndex++] = $sorted;
				}
			}	
		}

		private function mergeSort(){
			$this->prime();
			$mergeSize = MERGE_BASE_SIZE;
			while($mergeSize < $this->size){
				$this->splitArray($mergeSize);
				$mergeSize *= 2;
			}
		}
		
		private function prime(){
			$index = 0;
			foreach($this->unsorted as $unsorted){
				$this->references[$index++] = $unsorted;	
			}
		}
		
	}
}
?>