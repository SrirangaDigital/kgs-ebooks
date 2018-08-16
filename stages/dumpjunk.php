<?php 

class Dumpjunk{

	public function __construct() {
		
		
	}

	public function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = UNICODE_SRC . $bookID . '/';
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*' . $bookID . '\/\d.*\.xhtml$/',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}

	public function extractJunk($bookID){
		
		$allXhtmlFiles = $this->getAllFiles($bookID);

		$junkWords = [];

		foreach($allXhtmlFiles as $xhtmlFile){

			$xhtmlFileContents = file_get_contents($xhtmlFile);

			$xhtmlFileContents = strip_tags($xhtmlFileContents);
			// new file normalizations
			$xhtmlFileContents = str_replace('.', '. ', $xhtmlFileContents);
			$xhtmlFileContents = preg_replace('/\s+/', ' ', $xhtmlFileContents);
			$xhtmlFileContents = preg_replace('/ /', "\n", $xhtmlFileContents);
			$xhtmlFileContents = str_replace('–', '-', $xhtmlFileContents);		
			
			$finalWords = explode("\n",$xhtmlFileContents);

			$tempArray = [];

			foreach($finalWords as $word){


				if(preg_match('/È|É|Ë|Ì|Ï|Ò|Ó|Õ|Ö|Ø|Œ|Ù|œ|Ú|Û|Ü|ß|â|μ|ä|å|æ|š|é|ë|%|&|ï|ñ|ò|ó|ô|‰|õ|ö|ù|û|ü|‹|Ÿ|›|ÿ|@|¢|£|¤|¥|©|ª|«|®|°|»|¿|À|Á|Â|Ã|Å|Æ|~|Ç|[a-zA-Z]/u', $word)){
			
					if($word != '&amp;'){				
						
						array_push($tempArray, $word);
					}						
				}				
				if(preg_match('/^[\s\W]?(ा|ि|ी|ु|ू|ृ|ॄ|ॅ|ॆ|े|ै|ॉ|ॊ|ो|ौ|्|ं|ः|ऽ)/u', $word)){
						
					if($word != '&amp;'){
						
						array_push($tempArray, $word);
					}		
				}
				if(preg_match('/(ा|ि|ी|ु|ू|ृ|ॄ|ॅ|ॆ|े|ै|ॉ|ॊ|ो|ौ|्){2}/u', $word)){

					if($word != '&amp;'){				
						
						array_push($tempArray, $word);
					}							
				}
				if(preg_match('/ंे/u', $word)){

					if($word != '&amp;'){				
						
						array_push($tempArray, $word);
					}							
				}
			}

			if($tempArray){

				array_push($junkWords, $xhtmlFile);
				$junkWords = array_merge($junkWords,$tempArray);
			}
		}

		if(file_exists(RAW_SRC . $bookID . '/' . $bookID . ".junk.txt")) unlink(RAW_SRC . $bookID . '/' . $bookID . ".junk.txt");

		if($junkWords){

			file_put_contents(RAW_SRC . $bookID . '/' . $bookID . ".junk.txt", implode("\n",$junkWords));
		}	
	}
}
?>
