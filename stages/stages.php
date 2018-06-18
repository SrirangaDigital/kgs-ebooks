<?php

class Stages{

	public function __construct() {
		
	}

	public function processFiles($bookID) {

		$allFiles = $this->getAllFiles($bookID);

		foreach($allFiles as $file){
			
			$this->process($bookID,$file);		
		}
	}

	public function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = RAW_SRC . $bookID . '/Stage1/';
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*\.(TXT|CAP)$/i',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}

	public function process($bookID,$file) {

		// stage1.ven : Input text from ventura
		$rawVEN = file_get_contents($file);

		// Get ANSI text
		$rawVEN = $this->ventura2Text($rawVEN);

		// Form html from ventura tags
		$html = $this->formHTML($rawVEN);
		$html = $this->cleanupInlineElements($html);

		// stage2.html : Output html for conversion		
		$baseFileName = basename($file);

		if (!file_exists(RAW_SRC . $bookID . '/Stage2/')) {
			
			mkdir(RAW_SRC . $bookID . '/Stage2/', 0775);
			echo "Stage2 directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage2/' . preg_replace('/\.(txt|cap)$/i', '.html', $baseFileName);

		// $processedHTML = html_entity_decode($processedHTML, ENT_QUOTES);
		file_put_contents($fileName, $html);


		// Stage 3
		$unicodeHTML = $this->convert($html);

		// stage3.html : Output Unicode html with tags, english retained as it is
		if (!file_exists(RAW_SRC . $bookID . '/Stage3/')) {
			
			mkdir(RAW_SRC . $bookID . '/Stage3/', 0775);
			echo "Stage3 directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage3/' . preg_replace('/\.(txt|cap)$/i', '.html', $baseFileName);

		$unicodeHTML = html_entity_decode($unicodeHTML);
		
		file_put_contents($fileName, $unicodeHTML);
	}

	public function ventura2Text ($text) {

		$text = preg_replace("/\r\n/", "\n", $text);
		$text = preg_replace("/\n/", "", $text);
		$text = preg_replace("/(@[A-Z0-9\-]+ = )/", "\n\n$1", $text);

		$text = str_replace("<<", "", $text);
		$text = str_replace(">>", "", $text);

		$text = preg_replace_callback('/<(\d+)>/',
			function($matches) {

				return chr(intval($matches[1]) + 32);
			},
			$text);

		$text = mb_convert_encoding($text, 'UTF-8');

		$text = str_replace("\r\n", "\n", $text);
		return $text;
	}

	public function formHTML ($text) {

		$html = "<html>\n" . $text . "\n</html>\n";


		$html = preg_replace('/@ENG.*? = (.*)/', "<div><span class=\"en\">$1</span></div>", $html); // div
		$html = preg_replace('/@.*? = (.*)/', "<div>$1</div>", $html); // div
		$html = preg_replace('/(<B[A-Z0-9\.]*>.*?<D[A-Z0-9\.]*>)/', "<strong>$1</strong>", $html); //bold
		$html = preg_replace('/(<[A-Z0-9\.]*B[A-Z0-9\.]*>.*?<[A-Z0-9\.]*D[A-Z0-9\.]*>)/', "<strong>$1</strong>", $html); //bold

		$html = preg_replace('/<R>/', '<br />', $html); // break
		$html = str_replace('<B>', "", $html);
		$html = str_replace('<D>', "", $html);
		$html = preg_replace('/<[A-Z]*%[0-9\-]+>(.*?)<[A-Z]*%0>/', "$1", $html); // spacing
		$html = preg_replace('/<[A-Z0-9]*%[0-9\-]+>/', "", $html); // spacing

		$html = preg_replace('/<N>/', " ", $html); // space
		$html = preg_replace('/(<_>)+/', " ", $html); // space
		$html = preg_replace('/<->/', "", $html); // soft hyphen
		$html = str_replace('<+>', " ", $html); // no breaking space
		$html = str_replace('<~>', " ", $html); // no breaking space
		$html = str_replace('<|>', "", $html); // ottu spacer
		

		// English
		$html = preg_replace("/<F(49|20)[A-Z0-9]*>(.*?)<F255[A-Z0-9]*>/", "<span class=\"en\">$2</span>", $html);
		$html = preg_replace("/<P9M[A-Z0-9]*>(.*?)<P255[A-Z0-9]*>/", "<span class=\"en\">$1</span>", $html);
		$html = preg_replace("/<F(49|20)[A-Z0-9]*>([\hefi]+?)<\/div>/", "<span class=\"en\">$2</span></div>", $html);

		// Paragraphs
		$html = preg_replace("/<\\$\!SK>(.*?)<F14>/u", "<p>$1</p>", $html);
		$html = preg_replace("/<\\$\![A-z0-9]+>/", "", $html);

		// Remove all <F*
		$html = preg_replace("/<F\d+>/", "", $html);
		$html = preg_replace('/<[0-9A-Z\-\.]+>/', "", $html); // spacer

		$html = $this->cleanupInlineElements($html);

		// convert english spans
		$html = preg_replace_callback("/<span class=\"en\">(.*?)<\/span>/",
			function($matches){

				return $this->handlePunctuations($matches[1]);
			}, $html);

		$html = str_replace('&', "&amp;", $html); // html escape

		return $html;
	}

	public function cleanupInlineElements($html) {

		$html = preg_replace('/<span class="en">([^<\/span>]*?)<br \/>(.*?)<\/span>/', "<span class=\"en\">$1</span><br /><span class=\"en\">$2</span>", $html);
		$html = preg_replace('/<span class="en">([[:punct:]\hefi]+)<strong>/', "<strong><span class=\"en\">$1", $html);
		$html = str_replace('<p></p>', '', $html);
		$html = str_replace('<span class="en"></span>', '', $html);
		$html = str_replace('<strong><strong>', '<strong>', $html);
		$html = str_replace('</strong></strong>', '</strong>', $html);

		return $html;
	}

	public function handlePunctuations($text) {

		$text = (preg_match('/^[\he]+$/', $text)) ? str_replace('e', '।', $text) : $text;
		$text = (preg_match('/^[\hf]+$/', $text)) ? str_replace('f', '॥', $text) : $text;
		// $text = (preg_match('/^[\h=]+$/', $text)) ? str_replace('=', '=', $text) : $text;
		$text = (preg_match('/^[\hi]+$/', $text)) ? str_replace('i', 'ಽ', $text) : $text;
		$text = $text = str_replace('õ', '—', $text);

		$text = '<span class="en">' . $text . '</span>';
		return $text;
	}

	public function sanitizeText($text) {

		return htmlspecialchars($text);
	}

	public function convert ($html) {

		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$dom->loadXML($html);
		$xpath = new DOMXpath($dom);

		foreach($xpath->query('//text()') as $text_node) {

			if(preg_replace('/\s+/', '', $text_node->nodeValue) === '') continue; 

			if($text_node->parentNode->hasAttribute('class'))
				if($text_node->parentNode->getAttribute('class') == 'en')
					 continue;

			$text_node->nodeValue = $this->aakriti2Unicode($text_node->nodeValue);
		}

		$html = html_entity_decode($dom->saveXML());
		$html = preg_replace('/<span class="en">([[:punct:]।॥ಽ—\h]+)<\/span>/', "$1", $html);

		return $html;
	}

	public function aakriti2Unicode ($text) {

		// // ya group
		$text = str_replace('Ìâ°', 'ಯ', $text);
		$text = str_replace('ÌâÃ', 'ಯ', $text);
		$text = str_replace('Ìê°', 'ಯೆ', $text);
		$text = str_replace('Ìê³', 'ಯೊ', $text);
		$text = str_replace('Î°', 'ಯಿ', $text);

		// // ma group
		$text = str_replace('Àâ°', 'ಮ', $text);
		$text = str_replace('ÀâÃ', 'ಮ', $text);
		$text = str_replace('Àê°', 'ಮೆ', $text);
		$text = str_replace('Àê³', 'ಮೊ', $text);
		$text = str_replace('Æ°', 'ಮಿ', $text);
		
		// // jjha group
		$text = str_replace('pâ°ü°', 'ಝ', $text);
		$text = str_replace('pê°ü°', 'ಝೆ', $text);
		$text = str_replace('pê°ü³', 'ಝೊ', $text);
		$text = str_replace('î°ü°', 'ಝಿ', $text);

		$text = str_replace('n°', 'ಋ', $text);

		// Lookup ---------------------------------------------
		$text = str_replace('!', '!', $text);
		$text = str_replace('"', '್ಕ', $text);
		$text = str_replace('#', '್ಖ', $text);
		$text = str_replace('$', '್ಗ', $text);
		$text = str_replace('%', 'ಅ', $text);
		$text = str_replace('&', '್ಘ', $text);
		$text = str_replace("'", "’", $text);
		$text = str_replace('(', '(', $text);
		$text = str_replace(')', ')', $text);
		$text = str_replace('*', '್ಙ', $text);
		$text = str_replace('+', '್ಚ', $text);
		$text = str_replace(',', ',', $text);
		$text = str_replace('.', '.', $text);
		$text = str_replace('/', '/', $text);
		$text = str_replace('0', '೦', $text);
		$text = str_replace('1', '೧', $text);
		$text = str_replace('2', '೨', $text);
		$text = str_replace('3', '೩', $text);
		$text = str_replace('4', '೪', $text);
		$text = str_replace('5', '೫', $text);
		$text = str_replace('6', '೬', $text);
		$text = str_replace('7', '೭', $text);
		$text = str_replace('8', '೮', $text);
		$text = str_replace('9', '೯', $text);
		$text = str_replace(':', ':', $text);
		$text = str_replace(';', ';', $text);
		$text = str_replace('<', '', $text);  // removed
		$text = str_replace('=', '=', $text);
		$text = str_replace('>', '', $text); // removed
		$text = str_replace('?', '?', $text);
		$text = str_replace('@', '್ಜ', $text);
		$text = str_replace('A', 'ಆ', $text);
		$text = str_replace('B', '್ಝ', $text);
		$text = str_replace('C', '್ಞ', $text);
		$text = str_replace('D', '್ಟ', $text);
		$text = str_replace('E', 'ಇ', $text);
		$text = str_replace('F', '್ಠ', $text);
		$text = str_replace('G', '್ಡ', $text);
		$text = str_replace('H', '್ಢ', $text);
		$text = str_replace('I', 'ಉ', $text);
		$text = str_replace('J', '್ಣ', $text);
		$text = str_replace('K', '್ತ', $text);
		$text = str_replace('L', '್ಥ', $text);
		$text = str_replace('M', '್ದ', $text);
		$text = str_replace('N', 'ಊ', $text);
		$text = str_replace('O', 'ಏ', $text);
		$text = str_replace('P', '್ಧ', $text);
		$text = str_replace('Q', '್ನ', $text);
		$text = str_replace('R', '್ಪ', $text);
		$text = str_replace('S', '್ಫ', $text);
		$text = str_replace('T', '್ಬ', $text);
		$text = str_replace('U', 'ಎ', $text);
		$text = str_replace('V', '್ಭ', $text);
		$text = str_replace('W', '್ಮ', $text);
		$text = str_replace('X', '್ಯ', $text);
		$text = str_replace('Y', 'ಐ', $text);
		$text = str_replace('Z', '್ರ', $text);
		$text = str_replace('[', '್ಲ', $text);
		$text = str_replace("\\", '್ಳ', $text);
		$text = str_replace(']', '್ವ', $text);
		$text = str_replace('^', '್ಶ', $text);
		$text = str_replace('_', '್ಷ', $text);
		$text = str_replace('`', '‘', $text);
		$text = str_replace('a', 'ಒ', $text);
		$text = str_replace('b', '್ಸ', $text);
		$text = str_replace('c', '್ಹ', $text);
		$text = str_replace('d', 'ಕ್', $text);
		$text = str_replace('e', 'ಓ', $text);
		$text = str_replace('f', 'ಖ್', $text);
		$text = str_replace('g', 'ಗ್', $text);
		$text = str_replace('h', 'ಘ್', $text);
		$text = str_replace('i', 'ಔ', $text);
		$text = str_replace('j', 'ಙ', $text);
		$text = str_replace('k', 'ಚ್', $text);
		$text = str_replace('l', 'ಛ್', $text);
		$text = str_replace('m', 'ಜ', $text);
		$text = str_replace('n', 'n', $text); //to be handled as RRi
		$text = str_replace('o', 'ಈ', $text);
		$text = str_replace('p', 'ರ್', $text);
		$text = str_replace('q', 'ಞ', $text);
		$text = str_replace('r', 'ಟ', $text);
		$text = str_replace('s', 'ಟಿ', $text);
		$text = str_replace('t', 'ಠ್', $text);
		$text = str_replace('u', 'ಜ್', $text);
		$text = str_replace('v', 'ಡ್', $text);
		$text = str_replace('w', 'ಢ್', $text);
		$text = str_replace('x', 'ಣ', $text);
		$text = str_replace('y', 'ತ್', $text);
		$text = str_replace('z', 'ಥ್', $text);
		$text = str_replace('{', 'ದ್', $text);
		$text = str_replace('|', 'ಧ್', $text);
		$text = str_replace('}', 'ನ್', $text);
		$text = str_replace('~', 'ಪ್', $text);
		$text = str_replace('¡', 'ೞ', $text);
		$text = str_replace('¢', 'ಱ', $text);
		$text = str_replace('£', '£', $text); // ??
		// $text = str_replace('¤', '¤', $text); // Left Blank
		$text = str_replace('¦', '್ತ್ಯ', $text);
		$text = str_replace('§', '್ತೃ', $text);
		$text = str_replace('¨', '್ತ್ಯ', $text);
		$text = str_replace('©', '©', $text); // to be handled later as dirgha
		$text = str_replace('ª', 'ಂ', $text);
		$text = str_replace('«', 'ಭಿ', $text);
		$text = str_replace('¬', 'ದಿ', $text);
		$text = str_replace('-', '್ಕೃ', $text);
		$text = str_replace('®', '್ತ್ವ', $text);
		$text = str_replace('¯', '್ಕೃ', $text);
		$text = str_replace('°', 'ು', $text);
		$text = str_replace('±', 'ಬ', $text);
		$text = str_replace('²', 'ಬಿ', $text);
		$text = str_replace('³', 'ೂ', $text);
		$text = str_replace('´', 'ಘ್', $text);
		$text = str_replace('µ', 'ಶ್', $text);
		$text = str_replace('¶', 'ಲಿ', $text);
		$text = str_replace('·', 'ಲ', $text);
		$text = str_replace('¸', 'ಷಿ', $text);
		$text = str_replace('¹', 'ಣಿ', $text);
		$text = str_replace('º', 'ಧಿ', $text);
		$text = str_replace('»', 'ತಿ', $text);
		$text = str_replace('¼', 'ಥಿ', $text);
		$text = str_replace('½', 'ೃ', $text);
		$text = str_replace('¾', 'ನಿ', $text);
		$text = str_replace('¿', 'ನ್', $text);
		$text = str_replace('À', 'ವ್', $text);
		$text = str_replace('Á', 'ಫ್', $text);
		$text = str_replace('Â', 'ಟ್', $text);
		$text = str_replace('Ã', 'Ã', $text); // pre processing
		$text = str_replace('Ä', 'ಪಿ', $text);
		$text = str_replace('Å', 'ಭ್', $text);
		$text = str_replace('Æ', 'ವಿ', $text);
		$text = str_replace('Ç', 'ಣ್', $text);
		$text = str_replace('È', 'ಲ್', $text);
		$text = str_replace('É', 'ಸ್', $text);
		$text = str_replace('Ê', 'ಜಿ', $text);
		$text = str_replace('Ë', 'R', $text);
		$text = str_replace('Ì', 'Ì', $text); // pre processing
		$text = str_replace('Í', 'ಷ್', $text);
		$text = str_replace('Î', 'Î', $text); // pre processing
		$text = str_replace('Ï', 'ಬ್', $text);
		$text = str_replace('Ð', 'ಗಿ', $text);
		$text = str_replace('Ñ', 'ಕಿ', $text);
		$text = str_replace('Ò', '್', $text);
		$text = str_replace('Ó', 'ಛಿ', $text);
		$text = str_replace('Ô', 'ಳ್', $text);
		$text = str_replace('Õ', 'ಫಿ', $text);
		$text = str_replace('Ö', 'ಾ', $text);
		$text = str_replace('×', 'ಚಿ', $text);
		$text = str_replace('Ø', 'ಖಿ', $text);
		$text = str_replace('Ù', 'ಖ', $text);
		$text = str_replace('Ú', 'ಡಿ', $text);
		$text = str_replace('Û', 'ಢಿ', $text);
		$text = str_replace('Ü', 'ಘಿ', $text);
		$text = str_replace('Ý', 'ಹ್', $text);
		$text = str_replace('Þ', 'ಶ್ರೀ', $text);
		$text = str_replace('ß', '್ಪ್ರ', $text);
		$text = str_replace('à', '್ೞ', $text);
		$text = str_replace('á', '್ಱ', $text);
		$text = str_replace('â', 'ಅ', $text); // normalized later
		$text = str_replace('ã', 'ೄ', $text); 
		$text = str_replace('ä', 'ಚಿ', $text);
		$text = str_replace('å', '್ಟ್ರ', $text);
		$text = str_replace('æ', '್ಛ', $text);
		$text = str_replace('ç', '್ತ್ರ', $text);
		$text = str_replace('è', '್ತೖ', $text);
		$text = str_replace('é', '।', $text);
		$text = str_replace('ê', 'ೆ', $text);
		$text = str_replace('ë', '್ರೃ', $text);
		$text = str_replace('ì', '್ಸ್ರ', $text);
		$text = str_replace('í', '್ಚೖ', $text);
		$text = str_replace('î', 'ರಿ', $text);
		$text = str_replace('ï', 'ಠಿ', $text);
		$text = str_replace('ð', 'ಿ', $text);
		$text = str_replace('ñ', 'ೌ', $text);
		$text = str_replace('ò', 'ೂ', $text);
		$text = str_replace('ó', 'ು', $text);
		$text = str_replace('ô', 'ೖ', $text);
		$text = str_replace('õ', '—', $text);
		$text = str_replace('ö', '–', $text);
		$text = str_replace('÷', '÷', $text); // Left Blank
		$text = str_replace('ø', '್ಕ', $text);
		$text = str_replace('ù', 'ಳಿ', $text);
		$text = str_replace('ú', 'ಶಿ', $text);
		$text = str_replace('û', 'ಸಿ', $text);
		$text = str_replace('ü', 'ü', $text); // pre processing
		$text = str_replace('ý', 'ಹಿ', $text);
		$text = str_replace('þ', 'ಹಿ', $text);
		$text = str_replace('ÿ', 'ಃ', $text);
		$text = str_replace('¥', '-', $text);
		
		// Special cases

		// Swara
		$text = preg_replace('/್[ಅ]/u', '', $text);
		$text = preg_replace('/್([ಾಿೀುೂೃೄೆೇೈೊೋೌ್])/u', "$1", $text);
		
		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$swara = "ಅ|ಆ|ಇ|ಈ|ಉ|ಊ|ಋ|ಎ|ಏ|ಐ|ಒ|ಓ|ಔ";
		$vyanjana = "ಕ|ಖ|ಗ|ಘ|ಙ|ಚ|ಛ|ಜ|ಝ|ಞ|ಟ|ಠ|ಡ|ಢ|ಣ|ತ|ಥ|ದ|ಧ|ನ|ಪ|ಫ|ಬ|ಭ|ಮ|ಯ|ರ|ಱ|ಲ|ವ|ಶ|ಷ|ಸ|ಹ|ಳ|ೞ";
		$swaraJoin = "ಾ|ಿ|ೀ|ು|ೂ|ೃ|ೄ|ೆ|ೇ|ೈ|ೖ|ೊ|ೋ|ೌ|ಂ|ಃ|್";

		$syllable = "($vyanjana)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$text = str_replace('ಿ©', 'ೀ', $text);
		$text = str_replace('ೆ©', 'ೇ', $text);
		$text = str_replace('ೊ©', 'ೋ', $text);
		$text = preg_replace('/ೆ\h*ೖ/', 'ೈ', $text);

		$text = preg_replace("/($swaraJoin)\h*್($vyanjana)/u", "್$2$1", $text);

		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/್zzz/u", "್", $text);
		$text = preg_replace("/zzz([^z]*?)zzzR/u", "zzzರ್zzz" . "$1", $text);
		$text = preg_replace("/zzz([^z]*?)R/u", "zzzರ್" . "$1", $text);

		$text = str_replace("zzz", "", $text);

		// special cases
		$text = preg_replace('/\h+(' . $swaraJoin . ')/u', "$1", $text);
		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);
		$text = str_replace('ೈ', 'ೈ', $text);

		$text = str_replace('ಿ©', 'ೀ', $text);
		$text = str_replace('ೆ©', 'ೇ', $text);
		$text = str_replace('ೊ©', 'ೋ', $text);

		$text = str_replace('‘‘', '“', $text);
		$text = str_replace('’’', '”', $text);
		
		$text = str_replace('।', ' । ', $text);
		$text = str_replace('॥', ' ॥ ', $text);

		$text = preg_replace('/\h+/', ' ', $text);

		return $text;
	}
}

?>
