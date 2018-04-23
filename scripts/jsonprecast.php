<?php

class Jsonprecast {

	public $publisher = 'Ramakrishna Math Nagpur';

	public function __construct() {
		
	}

	public function getCSVFiles() {

		$allFiles = [];
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_DIRECTORY . 'scripts'));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*\.csv$/',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}

	public function generateBookDetailsFromCSV($csvFiles){

		$allBooksDetails['books'] = [];
		$bookDetails = "";

		$jsonFilePath = JSON_PRECAST . 'book-details.json';

		foreach ($csvFiles as $csvFile) {

			$fileContents = file_get_contents($csvFile);

			$lines = preg_split("/\n/", $fileContents);
			array_shift($lines);

			foreach ($lines as $line) {
								
				$fields = preg_split('/!/', $line);
				$fields = array_filter($fields);

				if(empty($fields)) continue;

				$bookCode = $fields[1];

				$bookDetails[$bookCode]["language"] = (preg_match('/^H/', $fields[1]))? 'Hindi' : 'Marathi';
				$bookDetails[$bookCode]["identifier"] = "Nagpur_eBooks/" . $bookCode;
				$bookDetails[$bookCode]["isbn"] = (isset($fields[2]))? $fields[2] : '';
				$bookDetails[$bookCode]["title"] = (isset($fields[3]))? $fields[3] : '';
				$bookDetails[$bookCode]["creator"] = (isset($fields[6]))? $fields[6] : '';
				$bookDetails[$bookCode]["publisher"] = $this->publisher;
				$bookDetails[$bookCode]["pages"] = (isset($fields[7]))? $fields[7] : '';
				$bookDetails[$bookCode]["description"] = (isset($fields[8]))? $fields[8] : '';
				$bookDetails[$bookCode]["price_p"] = (isset($fields[9]))? $fields[9] : '';
				$bookDetails[$bookCode]["price_e"] = (isset($fields[10]))? $fields[10] : '';
				
				$bookDetails[$bookCode] = array_filter($bookDetails[$bookCode]);

			}

		}

		$allBooksDetails['books'] = $bookDetails;
		$jsonData = json_encode($allBooksDetails,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($jsonData) file_put_contents($jsonFilePath, $jsonData);
	}
}

?>