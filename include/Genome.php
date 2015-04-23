<?php
/**
 *
 */
class Genome {
	
	private $genes = [];

	// Constructor
	function __construct($genomes_directory){

		$this->recursive_directory_search($genomes_directory);

	}
		
	/**
	 * Find genes by key value searching
	 *
	 * @param key (iso_code|state|name) to search
	 * @param value the value to find
	 *
	 * @retval a comma delimited text string
	 */
	function get_genes_by_key($key,$value){
		
		$string = '';

		foreach ($this->genes as $gene){

			if ($gene[$key] == $value){

				$string .= $gene['name'].",";
			}
		}

		return $string;
	}

	function get_genes(){

		$string = '';

		foreach ($this->genes as $gene){

			$string .= $gene['name'];
			if (next($this->genes) == true){

				$string .=",";
			}

		}

		return $string;
	}

	/**
	 * Populate our array of genes by scanning the Genome filestructure
	 * Genes are organized by ISO_CODE/STATE/NAME
	 *
	 * @param $dir the location of the Genome
	 */
	private function recursive_directory_search($dir){

		$iterator = new DirectoryIterator($dir);

		foreach ($iterator as $item) {

			if ($item->isDir() && !$item->isDot()){ // Ignore dots and anything that is not a directory
	
				// We only care about the path after Genome
				$trimmed_path = explode("Genome/",$item->getPathname());

				// Check for valid gene paths
				$gene = explode("/",end($trimmed_path));

				if (count($gene) == 3){

					$this->genes[] = [
						
						'iso_code'=> $gene[0],
						'state' => $gene[1],
						'name' => end($trimmed_path)
					];
				}
				
				// Iterate over only valid paths
				if ($gene[0] != '.git'){

					$this->recursive_directory_search("$dir/$item");
				}
				
				

			}
		}
	}

}
?>
